# Preços por serviço e vigência

## Entrega
Tarifas de pagamento e cobrança separadas, em EUR, com início obrigatório e fim opcional. Preço padrão por serviço e exceções de pagamento por trabalhador. Cobrança também permite preço específico por cliente.

O administrador configura e consulta; os outros perfis não recebem acesso global a valores financeiros. A consulta por equipa será definida junto da operação de campo.

## Seleção e precedência
Para um serviço, finalidade e data:
1. Procurar tarifa específica do trabalhador (pagamento) ou cliente (cobrança).
2. Se não existir na data, usar a tarifa padrão do serviço.
3. Se não houver tarifa, devolver erro; nunca assumir zero.
4. Um preço zero explicitamente cadastrado é válido e não é substituído pelo padrão.

Os dias de início e fim são inclusivos. O fim pode ficar em aberto. Dentro do mesmo serviço/finalidade/destinatário, tarifas não anuladas não podem sobrepor períodos, mesmo que a unidade do serviço tenha mudado. Padrão e exceção podem coexistir: são âmbitos diferentes.

## Histórico
Preço, finalidade, destinatário, unidade e início não são editados. Para mudar valor, encerrar a tarifa anterior e criar outra a partir do dia seguinte. Encerramento apenas mantém ou encurta a vigência existente; não prolonga o período.

Anulação exige motivo e mantém a linha e a auditoria. A tarifa anulada deixa de ser selecionada nas simulações e pode ser substituída por uma nova linha. Não existe eliminação física nem reativação de tarifa anulada pela API.

A unidade de pagamento/cobrança é copiada da configuração atual do serviço ao criar a tarifa. Guardam-se ID, nome e código; alterações futuras do serviço ou unidade não mudam essa referência histórica. Novos valores exigem serviço, unidade e destinatário ativos; encerramento/anulação de tarifas antigas continua permitido com serviço inativo.

## Decimais e simulação
Valores e quantidades entram como strings decimais JSON, sem expoente, negativos ou separadores de milhares. Até oito algarismos inteiros e quatro casas decimais. Float JSON é rejeitado. A interface aceita ponto ou vírgula decimal e envia ponto.

O banco guarda DECIMAL(12,4) para preço unitário. A biblioteca Brick Math 0.14 calcula com precisão arbitrária. A simulação multiplica a quantidade pelo preço e arredonda o total da linha para duas casas com HALF_UP, sem Number/float no frontend. Esta é a regra inicial de simulação; validar a política de arredondamento antes dos lançamentos financeiros.

A simulação devolve tarifa, unidade, quantidade e resultado, mas não gera movimentos, pagamentos, recebimentos ou documentos fiscais. Ainda não há diário fechado nesta etapa: a preservação do preço em cada lançamento fechado será implementada nessa entrega. Não recalcular retroativamente um lançamento fechado apenas consultando a tabela de tarifas.

## API
Todos os endpoints exigem sessão e prices.manage; POST exige CSRF.

| Método | Rota | Função |
|---|---|---|
| GET | /api/v1/prices?service_id=ID&page=1 | Histórico paginado, incluindo tarifas anuladas |
| POST | /api/v1/prices | Criar tarifa |
| POST | /api/v1/prices/ID/end | Encerrar vigência; exige version e valid_to |
| POST | /api/v1/prices/ID/cancel | Anular; exige version e reason |
| POST | /api/v1/prices/preview | Selecionar tarifa e simular total |

Criação: service_id inteiro, kind payment/billing, worker_id/client_id opcionais, amount string, valid_from data AAAA-MM-DD e valid_to data ou null. Pagamento não aceita client_id; cobrança não aceita worker_id. A interface envia também unit_id como expectativa da unidade apresentada. Se a configuração mudar antes de guardar, a API devolve 409, sem criar tarifa; a interface atualiza a unidade e exige reintroduzir o valor. Pela API, omitir unit_id significa aceitar a unidade atual do serviço. Campos desconhecidos são rejeitados.

Simulação: mesmo serviço/finalidade/destinatário, date e quantity string. Não aceita preço fornecido pelo cliente. Sobreposição ou versão antiga gera 409; dados inválidos 422; tarifa não encontrada 404. Em falha de rede, consultar o histórico antes de repetir uma criação, pois ainda não há chave de idempotência para cadastros de tarifas.

## Concorrência, auditoria e implantação
Escritas de tarifas e auditoria usam a mesma transação. Em MariaDB, o serviço é bloqueado com SELECT FOR UPDATE antes de verificar sobreposições ou alterar a vigência; todas as operações de tarifa do serviço seguem esse bloqueio. As tabelas novas usam explicitamente InnoDB. SQLite pode devolver conflito de escrita, sem aceitar silenciosamente tarifas concorrentes.

Não há histórico financeiro real nesta versão. As migrações anteriores ainda não foram implantadas por este trabalho; a migração de cadastros foi ajustada para especificar InnoDB em instalações novas. Se uma base já tiver recebido as migrações antigas, verificar o motor das tabelas antes de instalar tarifas: editar o ficheiro de migração não altera tabelas existentes.

Preparar base exclusiva de desenvolvimento e executar php spark migrate --all após composer install. Composer lockfile ainda precisa ser gerado e versionado antes da implantação reproduzível. Não houve mudança no Hestia.

## Limites
Sem lançamento diário, produção coletiva, pausas, mínimos diários, impostos, folha salarial legal ou integração fiscal. Operação e consulta de preços exigem internet nesta etapa. Preços históricos no diário offline serão enviados como provisórios e validados no servidor na etapa de sincronização.
