# Interface — Controlo Agrícola

Interface baseada na referência visual fornecida: navegação lateral verde-escura, seleção verde-lima, cartões claros e tabelas compactas. Abaixo de 761 px a navegação principal passa para a parte inferior; os restantes cadastros estão disponíveis no início em “Gerir operação”.

O painel consulta os totais reais de trabalhadores e equipas registados e a primeira página dos diários. Os totais incluem registos inativos porque o endpoint atual não filtra estado. Os trabalhos recentes apresentam até cinco registos, com os estados reais Rascunho e Cancelado. Não há dados fictícios na interface.

Valores financeiros e gráfico semanal estão explicitamente assinalados como pendentes da implementação de validação e apuramento. Não há indicação de gravação local nem envio para validação: as funções ainda não existem. Login, cadastros, preços e diários mantêm a API atual e os bloqueios durante gravações. Navegação limitada às permissões da sessão.

Verificação: compilação de produção e 12 testes existentes passaram. Revisão visual num navegador e testes em aparelhos reais ainda não realizados.

## Formulários em modal

Novos registos e edições de todos os cadastros, criação de tarifas e edição de diários abrem em janela modal. Encerramento/anulação de tarifas e cancelamento de diários usam o mesmo componente. Os filtros e simulação permanecem na página. O modal usa dialog nativo com foco contido, fundo inativo, título acessível, Escape e botão Fechar; ao fechar, o foco volta ao controlo de origem. Durante pedidos de gravação, o fecho é bloqueado. Não fecha por clique no fundo. Em telemóveis ocupa o ecrã inteiro com deslocamento interno. Erros de validação permanecem dentro do modal e a janela só fecha após gravação bem-sucedida ou cancelamento explícito.

## Seletores com pesquisa integrada

As referências a cadastros usam um combobox comum. O próprio campo aceita pesquisa por nome e consulta a API com debounce de 250 ms. Não existe caixa de pesquisa separada. A seleção mostra o nome confirmado e mantém o identificador do registo; texto livre não cria uma associação. Filtros por cliente/equipa e inclusão de inativos são preservados. Há estados de carregamento, erro e ausência de resultados; teclas de direção, Enter e Escape; validação dos campos obrigatórios; e indicação de até 25 resultados. Seletores de opções fixas (finalidade e avaliação) continuam nativos.

## Ações das listagens

Ações representadas por ícones com descrição ao passar o rato e rótulo acessível: lápis para editar, olho para consultar, bandeira para encerrar vigência e lixeira para remover/anular/cancelar. Cadastros sem vínculos podem ser excluídos, com confirmação em modal. Cadastros com ligações de entrada ou saída, incluindo tarifas anuladas e diários cancelados, são apenas desativados. Permissões, CSRF, versão, transação e auditoria são verificados no servidor. Tarifas e diários mantêm anulação/cancelamento para preservar o histórico e a idempotência. Não é necessário migrar o banco.

Verificação desta alteração: compilação e 12 testes do frontend; 60 testes do backend com 334 asserções numa base MariaDB exclusiva e isolada. Cobertura inclui exclusão sem vínculos, desativação com ligações de entrada/saída, histórico de tarifa anulada, conflito de versão, permissões e auditoria. Publicação verificada por HTTP e saúde da API; teste visual de navegador não executado.

## Cadastro rápido a partir do seletor

Quando a pesquisa não tem correspondência exata nas opções carregadas, aparece “Cadastrar novo” no final da lista para utilizadores com catalog.manage. Abre o mesmo editor de cadastros por cima do formulário atual, com o nome preenchido. Um local herda o cliente filtrado e um trabalhador herda a equipa filtrada; estes vínculos de contexto ficam bloqueados para manter a nova opção compatível com o seletor. Outros campos obrigatórios, como código de unidade e unidades do serviço, continuam a ser exigidos. Pode abrir outros cadastros rápidos a partir das referências internas. Cada formulário tem identificadores únicos.

Ao guardar com sucesso, apenas o modal de cadastro rápido fecha e o seletor anterior recebe o identificador/nome devolvidos pela API. O formulário anterior permanece montado. Cancelar não altera a seleção, erros mantêm o editor aberto, Escape afeta somente o modal superior e o fecho fica bloqueado durante a gravação. Eventos de submissão dos modais internos não submetem o formulário anterior.

Verificação: compilação e 20 testes de frontend passaram, incluindo fluxos de cadastro rápido em DOM simulado. Não houve mudança de backend nesta etapa.

## Listagem geral de serviços e preços

A área abre com todas as tarifas, incluindo históricas e anuladas, paginadas em 25 registos. O serviço já não é obrigatório para consultar. A tabela identifica o serviço de cada tarifa. Acima da listagem há pesquisa por serviço/cliente/trabalhador/unidade e filtros por serviço, finalidade (pagamento/cobrança) e estado (todos/não anuladas/anuladas). “Filtrar” consulta o servidor e volta à primeira página; “Limpar filtros” regressa à listagem geral.

Criação e simulação ficam em modais com seleção do serviço dentro do formulário. O filtro da listagem pode preencher inicialmente esse serviço. Mantidos cadastro rápido, preços com precisão decimal, vigências, simulação e ações por ícones. Nenhuma migração necessária. Verificação: compilação e 23 testes de frontend, mais 18 testes de preços no backend com 128 asserções numa base MariaDB isolada.

## Localização portuguesa

Datas apresentadas como dd/mm/aaaa, horas HH:mm; auditoria e cabeçalho usam Europe/Lisbon. Campos de telefone aplicam máscara +351 912 345 678 e preservam prefixos internacionais explicitamente introduzidos. Quantidades e tarifas usam espaços de milhares e vírgula decimal; o payload conserva decimais canónicos sem ponto flutuante. Valores monetários são apresentados em EUR com símbolo €. Inputs de data/hora declaram pt-PT; o seletor nativo pode seguir a configuração regional do dispositivo.

## Novas áreas

Utilizadores e permissões e Relatórios estão disponíveis na navegação web e nos atalhos móveis, de acordo com as permissões da sessão. Consultar UTILIZADORES.md e RELATORIOS.md.

## Textos auxiliares

Removidos comentários explicativos de implementação/desenvolvimento das telas, notas extensas sobre cálculos, instruções redundantes dos formulários e metadados do relatório na tela (linhas/emissão/limite). PDF e Excel deixam de incluir a nota explicativa. Preservados estados reais, campos, valores, regras, validações, mensagens de ação e confirmações. O relatório mantém os dados e totais originais.
