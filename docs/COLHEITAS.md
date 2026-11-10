# Operação por colheita

A operação passa a usar cliente → contrato → colheita → atividades diárias. As equipas antigas permanecem no histórico, sem cadastro de equipa na navegação nova.

## Administração

Em **Contratos e colheitas**, criar o contrato com cliente, local, serviço, unidades e preços independentes de cobrança e remuneração. A conversão opcional indica unidades do cliente esperadas por unidade dos trabalhadores (ex.: kg por caixa). Criar a colheita a partir desse contrato e atribuir o controlador. As condições são copiadas para preservar os valores históricos; novas condições comerciais devem originar outro contrato/colheita.

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

A migração é aditiva, preserva o histórico e torna opcional a equipa dos diários. Fazer backup da base e aplicação antes de aplicar as migrações. O funcionamento autenticado sem internet e a sincronização de fotografias continuam como etapa separada: esta versão requer ligação ao servidor para gravar.
