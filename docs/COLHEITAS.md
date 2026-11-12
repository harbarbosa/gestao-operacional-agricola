# Operação por contrato

A operação usa cliente → contrato → trabalhos diários. Contrato e colheita são apresentados como um único registo. As equipas antigas permanecem no histórico, sem cadastro de equipa na navegação nova.

## Administração

Em **Contratos**, criar o contrato com cliente, local, serviço, unidades e preços independentes de cobrança e remuneração. A conversão opcional indica unidades do cliente esperadas por unidade dos trabalhadores (ex.: kg por caixa). Informar a data de início no mesmo formulário; não é necessário atribuir controlador. As condições são copiadas para preservar os valores históricos; novas condições comerciais devem originar outro contrato/colheita.

Em **Trabalhos**, cada dia é associado à colheita. O controlador seleciona participantes, cadastra nome completo e telefone de novos trabalhadores, regista produção individual ou grupos temporários com divisão igual e avalia cada trabalhador presente. Guardar o rascunho e reabrir a atividade para adicionar fotografias à atividade, trabalhador ou grupo. **Encerrar dia** envia para validação; não encerra a colheita nem mostra valores. O administrador valida ou devolve para correção. Apenas atividades validadas entram no apuramento financeiro.

## Entregas e conversão

Registar cada entrega com a quantidade reconhecida pelo cliente e as quantidades provenientes de cada atividade validada. O servidor bloqueia produção já entregue, quantidades excessivas, atividades de outra colheita e datas incompatíveis. A comparação usa apenas a produção associada à entrega. A diferença é informativa e não altera remunerações automaticamente. Uma carga misturada não permite atribuir a diferença a um trabalhador específico.

## Fluxo financeiro

Pagamentos aos trabalhadores, recebimentos do cliente e outras despesas são lançados durante a colheita, mesmo antes do encerramento. Um pagamento com quantidade identifica trabalhador e atividade. Um recebimento com quantidade identifica a entrega. Os valores devem respeitar a tarifa e a quantidade; operações usam UUID para não duplicar em reenvios. Também existem adiantamentos sem quantidade: afetam caixa e saldos, não os totais de produção liquidada. Valores incorretos não devem ser compensados com produção fictícia.

O painel separa produção validada, entregue, pesada, paga e liquidada; apurado, pago, recebido, despesas, saldo de caixa, margem e saldos pendentes. Quantidades de unidades diferentes não são somadas. O detalhe mostra histórico individual por dia, produção partilhada, valores apurados e pagos. Pagamentos podem ocorrer em qualquer data, inclusive após o encerramento da colheita.

## Encerramento

O administrador fecha a colheita no final, após validar os dias e associar a produção às entregas. Recebimentos e pagamentos pendentes não impedem esse encerramento operacional e continuam disponíveis no fluxo. As atividades encerradas não podem ser editadas; os rascunhos ficam protegidos por versão. Os registos anteriores continuam em **Fechos anteriores**.

## Segurança e publicação

Controladores têm acesso às colheitas atribuídas. Preços, alocações financeiras, movimentos e resumos monetários não são enviados nas respostas operacionais. Fotos ficam fora da pasta pública e são servidas após autenticação e verificação de acesso. JPG/PNG/WebP até 5 MB.

A migração é aditiva, preserva o histórico e torna opcional a equipa dos diários. Fazer backup da base e aplicação antes de aplicar as migrações. A operação do controlador funciona offline após preparação; consultar [Preparação e sincronização](OFFLINE.md).

## Simplificação de novembro de 2026

A interface usa apenas **Contrato**: o formulário cria de uma vez as condições comerciais e a operação, sem atribuição de controlador. Contratos antigos são preservados; condições antigas ainda sem operação ganham automaticamente um registo operacional. O controlador identifica-se ao abrir o trabalho diário e pode escolher qualquer contrato ativo, mas só consulta/edita os seus próprios trabalhos. A administração mantém a visão completa.

O controlador entra diretamente em **Hoje**, com **Histórico** e **Conta** na navegação. Produção e nota ficam nos cards; observações ficam nos detalhes; não são pedidos horários. O cadastro rápido continua a pedir nome completo e telefone. **Guardar** mantém o dia editável; **Encerrar dia** guarda e envia para validação. Na tela offline, fotografias podem ser guardadas no dispositivo e enviadas após o trabalho.

A seleção e os cards mostram média, número de avaliações e histórico recente (até 20). A média abaixo de 3/5 gera alerta, nunca bloqueio automático. Rascunhos e cancelamentos não contam; avaliações de dias enviados ou validados contam, excluindo presenças sem nota. A administração bloqueia/desbloqueia na ficha de histórico do trabalhador, com motivo obrigatório e auditoria. O servidor impede associar trabalhador bloqueado e encerrar/validar um dia que o mantenha como presente. Atividades já validadas, produção e pagamentos existentes são preservados.
