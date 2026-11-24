# Agroperi — modo simplificado

O modo simplificado é o padrão. A atividade guarda o nome e a descrição livre da unidade; cada participação guarda `payment_rate`. Não é necessário criar clientes, contratos, serviços ou unidades. O controlador só consulta os seus diários, define o preço individual, regista produção/avaliações/fotografias e encerra offline.

A validação administrativa cria registos históricos em `agri_earnings`, com preço e quantidade atribuída ao trabalhador. Nos grupos, reparte-se a quantidade a quatro casas decimais (desempate pelo ID), multiplicando cada parcela pelo preço do respetivo trabalhador e arredondando a euros/cêntimos. O grupo não cria outra remuneração adicional. Registos validados não são editáveis.

Os fechos por trabalhador/período e pagamentos parciais reutilizam `ProductionBook`, com proteção de versões, UUIDs e mapeamento único dos rendimentos. A consulta e o PDF usam o mesmo endpoint `/api/v1/simple/workers/{id}/statement`; a versão sem trabalhador apresenta o total. Produção por unidade é apresentada separadamente. O demonstrativo separa saldo anterior, apuramento do período, pagamentos do período e saldo à data final.

A migração é aditiva, exceto a flexibilização de referências para permitir os novos diários sem cadastros. Não remove registos antigos. O demonstrativo também consulta os apuramentos e pagamentos anteriores dos contratos, preservados nas tabelas originais. O pagamento desses fechos antigos requer reativar o módulo legado; os novos fechos são feitos em Pagamentos.

Para reativar os módulos antigos, definir `agroperi.legacyEnabled = true` no ambiente e iniciar sessão novamente. O código e tabelas continuam presentes. A configuração não concede permissões novas. No modo simplificado os menus antigos e os endpoints de manutenção de clientes, contratos, unidades, serviços, preços e financeiro de contratos ficam desativados.

A sessão offline, filas de fotografias, UUIDs de sincronização e rascunhos anteriores são preservados. Os rascunhos simplificados guardam nome, unidade e preço individual no dispositivo e no servidor. A consulta administrativa e PDFs exigem ligação e permissão financeira.
