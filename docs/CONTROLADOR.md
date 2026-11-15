# Operação do controlador

A área de campo usa Hoje, Histórico e Conta. Hoje mostra os trabalhos em curso (incluindo os de datas anteriores) e os concluídos do dia. Cada contrato mantém a sua própria unidade de produção.

Novo trabalho apresenta contrato, cliente/local/unidade de consulta e data DD/MM/AAAA. Criar trabalho guarda um único rascunho no dispositivo e abre os trabalhadores. A edição dos dados fica em Detalhes do trabalho e conserva as restrições do servidor depois do primeiro envio.

Os cards abrem a produção individual com gravação automática a cada alteração, avaliação 1–5 e comentário. Quantidades e avaliações não ficam nos cards. Histórico, fotografias e remoção ficam nos detalhes. Retirar alguém com lançamentos exige confirmação; fotos pendentes dessa associação ficam locais e são excluídas do envio. Fotos já enviadas são preservadas.

Na aba Grupos, a associação substitui produção individual e outra associação apenas após confirmação. A regra atual do servidor é repartição igual, com precisão de quatro casas e resíduos por ordem numérica de trabalhador. Parcelas de trabalhadores ainda sem identificador definitivo são provisórias até sincronizar. Os totais contam cada grupo uma vez, sem somar novamente as parcelas individuais. Quantidades individuais e de grupo são mutuamente exclusivas no mesmo diário, conforme a regra existente.

Rever e encerrar mostra presentes, produção e avaliações. Avaliações em falta ligam diretamente ao trabalhador. Exige todos os presentes avaliados, quantidades válidas, grupos com pelo menos dois presentes e ausência de trabalhadores bloqueados conhecidos. O servidor revalida todas as regras e permissões no envio. Não foram acrescentadas exigências de comentário nem de horários.

O encerramento offline guarda a intenção localmente. A confirmação de submissão é apresentada somente após o servidor responder; depois aguarda conferência administrativa. A sessão offline e os recibos idempotentes existentes foram preservados. A faixa distingue gravação, pendências, falhas e sincronização confirmada e inclui fotografias e trabalhadores pendentes na contagem.

## Verificação

Testes unitários cobrem contagens sem duplicação, resíduos iguais aos do servidor, datas portuguesas, recuperação offline, fotografias pendentes, sincronização idempotente e reabertura sem substituir participantes. A validação visual foi realizada com dados/API de teste isolados, a 390 px, com verificação adicional a 320 px e na web. Percorreu criação, pesquisa/cadastro sem telefone com fotografia, produção e avaliação, grupos, revisão, reabertura/encerramento offline simulado e envio automático ao recuperar a ligação. Nenhum registo de produção foi criado para testar.

A câmara física, o teclado virtual e a suspensão/reabertura pelo sistema operativo devem ser confirmados num telemóvel real. Não há envio garantido com a aplicação fechada.
