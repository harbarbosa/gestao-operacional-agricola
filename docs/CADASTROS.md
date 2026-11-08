# Cadastros operacionais

## Entrega
Listagem com pesquisa por nome e paginação (25 itens), criação, edição e desativação de trabalhadores, equipas, clientes, unidades e serviços. Sem eliminação física pela API.

- Trabalhadores: nome, contactos opcionais, equipa opcional e observações.
- Equipas: nome, nome do responsável e observações. O nome do responsável é informativo; não concede acesso a uma conta.
- Clientes: nome, pessoa de contacto, email, telefone e observações.
- Unidades: nome e código único configurável (por exemplo hora, dia, caixa, arvore ou hectare).
- Serviços: nome, observações, unidade de pagamento ao trabalhador e unidade de cobrança ao cliente independentes.

Unidades são cadastradas pela interface antes de criar serviços. Não há valores ou cálculos financeiros nesta etapa. Tarifas com vigência, preços individuais por trabalhador e explorações agrícolas pertencem às próximas entregas da issue #3.

## Acesso
Permissão catalog.manage atribuída apenas ao administrador. Chefes de equipa e trabalhadores não consultam estes cadastros globais. A consulta por equipa autorizada será implementada na operação de campo; não conceder acesso global para contornar esse trabalho.

## API
Prefixo /api/v1/catalog/{recurso}; recursos workers, teams, clients, units e services.
- GET /: lista; parâmetros q e page.
- GET /{id}: consulta um registo.
- POST /: cria.
- PUT /{id}: substitui campos editáveis e exige version atual.

Métodos de escrita exigem sessão, permissão e CSRF. Campos desconhecidos são rejeitados. Erros de campo retornam 422; registo inexistente 404; versão antiga 409. is_active usa booleano JSON, referências usam inteiro ou null quando permitido. Não enviar IDs, datas ou versão na criação.

A edição devolve version incrementada. Se houver conflito, os dados atuais não são sobrescritos: recarregar a listagem e reabrir o formulário. Desativação preserva relações anteriores; não permite atribuir uma referência inativa a um novo registo. Nome não é identificador único de uma pessoa.

## Persistência e auditoria
Migração App cria tabelas agri_* com chaves estrangeiras para equipa/unidades e índice único de código de unidade. Cada alteração gera registo de auditoria com ator, data UTC, estado anterior e posterior na mesma transação. Auditoria não tem endpoint público nesta etapa. Evitar dados sensíveis desnecessários nas observações.

Preparar numa base exclusiva de desenvolvimento/homologação com php spark migrate --all. Não executar em bases de outros projetos. Reverter esta migração elimina os dados dos cadastros; backup e plano de reversão são necessários antes de produção.

## Limites
Cadastros requerem internet nesta entrega. Não há sincronização ou cache destes dados no aparelho. Não há ligação entre ficha de trabalhador e conta de acesso ainda. Uma equipa atual por trabalhador; histórico de composição e participação diária será definido no diário de campo. Não representa folha salarial ou faturação fiscal.
