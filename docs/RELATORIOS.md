# Relatórios operacionais

Permissão reports.view: consultar e exportar trabalhos de toda a empresa. Administradores recebem esta permissão; pode ser atribuída adicionalmente. Seletores usam endpoints próprios, apenas com identificador/nome/estado, sem exigir escrita nos cadastros.

Filtros combináveis: trabalhador, cliente, serviço/tipo de trabalho, equipa, local, datas inicial/final inclusivas e estado. Pesquisa inclui registos associados a cadastros inativos para preservar consulta histórica. Datas no calendário do trabalho; auditoria em UTC convertida para Europe/Lisbon na apresentação e exportação, incluindo horário de verão.

Detalhe por participante: diário, data, cliente, exploração, equipa, responsável, serviço, trabalhador, presença, entrada, saída, saída no dia seguinte, quantidade individual e unidade, produção do cliente e unidade, avaliação, observações individuais e do diário, cancelamento, datas/autores de criação e última alteração e versão.

Resumos: diários, trabalhadores, presenças/ausências e produção por unidade. A quantidade do cliente é agregada uma vez por diário; nas linhas por participante, não deve ser somada. Filtrar trabalhador restringe produção individual à pessoa; quantidade cliente refere-se aos diários em que participou. Totais incluem os estados selecionados. Não há apuramento financeiro nesta etapa. Permanência bruta entre entrada/saída não desconta intervalos e não corresponde a horas pagas.

Exportações CSV UTF-8 com BOM e separador ponto e vírgula; Excel XLSX com folhas Trabalhos e Resumo, números e cabeçalho fixo; PDF paginado com detalhe completo. Exportam todos os resultados dos filtros aplicados, independentemente da página, com limite explícito de 10 000 linhas. Datas dd/mm/aaaa; horas 24h; valores com vírgula decimal. CSV neutraliza fórmulas introduzidas nos textos; XLSX escreve texto sem fórmulas.

A listagem mostra 50 linhas por página. Alterações de filtros só se aplicam ao clicar Gerar relatório. Exportação conserva os filtros aplicados. Não requer migração adicional além da gestão de utilizadores. XLSX necessita PHP ZipArchive (verificado no Hestia).

Validação: 75 testes de backend, 436 asserções, numa base MariaDB isolada; 30 testes do frontend e compilação de produção. Conferência estrutural e visual de PDF; Excel lido por openpyxl, com duas folhas e quantidades numéricas. Após ajuste visual das exportações, o teste específico passou novamente. Não houve ensaio visual da aplicação num navegador nesta entrega.
