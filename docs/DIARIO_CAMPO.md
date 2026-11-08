# Locais e diário de campo em rascunho

## Entrega inicial
- Cadastro de locais/explorações vinculados a clientes: nome, localidade, observações e estado ativo.
- Diário com data, cliente, local, equipa, serviço e responsável informativo da equipa.
- Participantes com presença/ausência explícita, entrada/saída, saída no dia seguinte, quantidade individual, avaliação opcional de 1 a 5 e observações.
- Quantidade total para cobrança ao cliente separada da quantidade individual dos trabalhadores.
- Criação, consulta, edição do conteúdo e cancelamento auditável. Lista paginada, filtro por dia e consulta de diários cancelados.

Acesso inicial apenas do administrador, via field.manage. Não atribuir acesso global aos chefes de equipa como atalho: autorização por equipa e contas ligadas às equipas ainda precisam de implementação. Trabalhador e supervisor continuam sem acesso a estes endpoints.

## Estados e limites
Só existem draft e canceled. Não há validação, fechamento, preços aplicados ou movimentos financeiros nesta entrega. Rascunho aceita participantes incompletos, por exemplo entrada sem saída, ou até nenhum participante; uma etapa posterior exigirá os dados completos antes do fechamento.

Esta versão é online. O formulário não persiste localmente nem se sincroniza sozinho. Não equivale à entrega da operação offline obrigatória, que continua pendente na issue #8.

## Presenças, unidades e horários
Novo participante deve ser trabalhador ativo da equipa escolhida. Um participante já registado pode ser corrigido no rascunho mesmo se depois mudar de equipa ou ficar inativo. Guardam-se o nome individual e os nomes/unidades do cabeçalho; alterações futuras nos cadastros não reescrevem esses dados históricos.

Ausência não admite horários, quantidade, avaliação ou indicação de turno noturno. Não listar um trabalhador não constitui automaticamente uma falta. Os horários são relógios locais de Portugal e não geram duração, horas extra ou remuneração automaticamente. Entrada sem saída é permitida no rascunho; saída sem entrada é rejeitada. Turno noturno exige indicação explícita, com sequência até 24 horas pelo relógio local. Ajustes de horário de verão, pausas e cálculo de horas serão definidos antes do fechamento financeiro.

Quantidades opcionais são strings decimais, até oito inteiros e quatro casas, sem valores negativos. A unidade individual vem do serviço para pagamento; a unidade total vem do serviço para cobrança. Não há conversão nem soma automática entre essas duas quantidades. Produção coletiva e critérios de rateio continuam pendentes.

## Cabeçalho e correções
Data, equipa, cliente, local e serviço ficam imutáveis após a criação. Para corrigir o contexto, cancelar com motivo e criar novo diário. Conteúdo editável: participantes, horários, quantidades e observações. Toda edição exige version atual; conflito devolve 409 sem sobrescrever.

Na criação, cliente e local devem corresponder. A versão do serviço apresentada ao operador deve continuar atual; se mudar, o servidor rejeita a gravação antes de reinterpretar quantidades. A interface oferece revisão explícita das unidades e limpeza de quantidades. Os dados não são automaticamente convertidos.

Cancelamento preserva participantes e auditoria, impede novas edições e não gera estornos porque ainda não existem movimentos financeiros. Não há eliminação física de diários pela API. Alterações nos participantes mantêm o estado anterior na auditoria.

## Reenvio e transações
Cada criação exige UUID v4 request_id. Reenvio com o mesmo UUID, ator e conteúdo normalizado devolve o diário existente, sem duplicar diário ou auditoria. Mesmo UUID com conteúdo diferente devolve 409. Depois de cancelar, repetir a criação original continua a devolver o diário cancelado; não o reabre.

A interface preserva o UUID enquanto o formulário permanecer aberto. Em falha de rede, repetir sem alterar os dados ou consultar a listagem antes de criar outro diário. Reabrir um formulário novo gera outro UUID: isto ainda não é uma fila offline persistente.

Cabeçalho, participantes e auditoria usam a mesma transação. Em MariaDB/InnoDB, criação bloqueia o serviço para conferir a versão; edição bloqueia o próprio diário. O índice único do UUID também protege contra duplicação concorrente. Não se impõe unicidade de equipa/data/serviço: podem existir trabalhos distintos no mesmo dia. Validação de sobreposição de horários entre diários fica para a fase de validação/fechamento.

## API
Todas as rotas exigem field.manage e sessão; métodos de escrita exigem CSRF.

| Método | Rota | Operação |
|---|---|---|
| GET | /api/v1/work-logs?page=1&date=AAAA-MM-DD | Lista com filtro opcional de dia |
| GET | /api/v1/work-logs/ID | Cabeçalho e participantes |
| POST | /api/v1/work-logs | Criar rascunho, ou reconhecer reenvio |
| PUT | /api/v1/work-logs/ID | Substituir conteúdo editável, com version |
| POST | /api/v1/work-logs/ID/cancel | Cancelar, com version e reason |

Criação: request_id, work_date, client_id, site_id, team_id, service_id, service_version, attendees; notes e billing_quantity opcionais. Cliente é conferido com o local, nunca aceite sem validação. No máximo 100 participantes por diário. Cada trabalhador aparece uma vez por diário.

PUT aceita apenas version, attendees, notes e billing_quantity. Participante aceita worker_id, present, time_in, time_out, exit_next_day, payment_quantity, score e notes. Campos de dinheiro, estado e nomes históricos não são aceites do cliente. Ausência de quantidade usa null; ausência de horário usa null. Avaliação usa inteiro ou null.

Locais usam o cadastro /api/v1/catalog/sites. A lista aceita client_id; a lista de trabalhadores aceita team_id. São filtros de conveniência do administrador, não uma autorização por equipa.

## Preparação
Executar composer install e php spark migrate --all numa base exclusiva de desenvolvimento/homologação. A migração cria agri_sites, agri_work_logs, agri_work_log_attendees e agri_work_log_audit em InnoDB no MariaDB. Reverter elimina estes dados e exige backup antes de uso real. Não houve implantação no Hestia.
