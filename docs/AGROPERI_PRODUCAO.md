# Agroperi — equipas, produção e pagamentos

## Operação no campo

A aplicação passa a chamar-se Agroperi, incluindo manifest PWA e relatórios. O endereço atual mantém-se.

O administrador associa o utilizador responsável à equipa no campo “Responsável (utilizador)”. Utilizadores com field.record consultam e operam apenas equipas ativas que lhes estão atribuídas; field.manage permite a gestão global. Os responsáveis pesquisam trabalhadores ativos globalmente, com média e número de avaliações disponíveis na lista. Selecionar um novo participante associa-o à equipa ao guardar a atividade; diários anteriores preservam a identificação e composição histórica. O cadastro rápido de trabalhadores fica disponível para a própria equipa, sem conceder gestão dos restantes cadastros.

## Grupos temporários

Cada atividade pode ter grupos de colheita com nome, produção conjunta e participantes presentes. Cada participante pertence a um grupo ou trabalha individualmente. Grupos exigem pelo menos duas pessoas no fecho. A produção conjunta não é repetida nas quantidades individuais.

O grupo usa a tarifa padrão de pagamento do serviço, aplicável à data e à unidade original da atividade. O valor total em cêntimos é dividido igualmente; cêntimos restantes são atribuídos por ordem crescente do identificador dos participantes. A prévia identifica grupo, participantes e valores exatos antes de confirmar. Não há pagamento duplicado nem total perdido por arredondamento. Tarifas específicas por trabalhador continuam a ser usadas no trabalho individual.

## Fecho da atividade

Rascunhos continuam a poder ser guardados durante a atividade. No fim, todos os presentes precisam de entrada, saída, produção individual ou grupo e nota de 1 a 5. Comentários são opcionais. O fecho cria os direitos de pagamento com tarifa, unidade e valor congelados, regista autor/data e torna a atividade imutável. A confirmação inclui assinatura da prévia; alterações de valores obrigam a conferir novamente. Repetir a mesma confirmação não duplica os valores.

## Fecho por período e pagamentos

finance.manage permite escolher trabalhador e intervalo inclusivo de datas, consultar os direitos ainda não incluídos num fecho e confirmar. O período é livre, incluindo quinzenas. Atividades ainda em rascunho para a pessoa no período impedem o fecho. Direitos já incluídos não entram novamente, mesmo em períodos sobrepostos. Os intervalos não são fechados automaticamente e não há operações bancárias automáticas.

Pagamentos são registados com data, valor, referência e observação. Admitem pagamentos parciais, não permitem valor acima do saldo e são idempotentes. A produção, o fecho e o registo de pagamento são operações separadas. Valores usam aritmética decimal/cêntimos no servidor; limites máximos são explícitos. Não há cálculo fiscal, retenções, contratos ou adiantamentos nesta entrega.

## Desempenho

performance.view abre ranking por média, com número de avaliações, mínima/máxima e histórico de comentários. Só atividades fechadas entram na média. Responsáveis veem avaliações das suas equipas; gestores globais veem todas. Notas baixas não bloqueiam automaticamente pessoas nem alteram o pagamento. As observações e as datas permitem interpretar a média com contexto e amostra.

## Relatórios e instalação

Relatórios passam a reconhecer Fechado e a incluir grupo e produção conjunta, somada uma vez por grupo. Os valores financeiros ficam na área restrita de produção/pagamentos. Migração 2026-11-10-080000_CreateAgroperiClosures cria grupos, direitos de pagamento, fechos, itens e pagamentos; adiciona responsável de equipa e dados de fecho. Aplicar após backup; não reverter a migração numa base com movimentos financeiros.

## Validação

Compilação de produção e 32 testes do frontend passaram. Backend: 83 testes, 478 asserções, em MariaDB isolado. Incluídos: fecho sem nota rejeitado; rateio exato de 1001 cêntimos entre 3 pessoas; repetição de fecho/pagamento; bloqueio de edição após fecho; confirmação de valores alterados; períodos com rascunhos; pagamentos parciais e acima do saldo; âmbito de equipa do responsável; cadastro de trabalhadores autorizado; histórico de avaliações; produção de grupo contada uma vez.

Publicação: nova estrutura aplicada no Hestia após backup de arquivos e banco. Conferidos título/manifest/assets, saúde da API e proteção por autenticação dos novos endpoints. Não houve criação de lançamentos financeiros de teste na base de produção.
