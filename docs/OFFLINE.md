# Agroperi no campo sem internet

## Preparação

1. Aceder por HTTPS no telemóvel e iniciar sessão com uma conta de controlador enquanto há internet.
2. Em **Hoje**, tocar em **Preparar para o campo** e aguardar a confirmação.
3. Instalar pelo menu do navegador (Instalar aplicação / Adicionar ao ecrã inicial).
4. Antes de cada saída, usar **Atualizar preparação** para obter contratos, bloqueios e avaliações atuais.

A preparação guarda contratos, trabalhadores (incluindo alertas e histórico recente) e os próprios trabalhos recentes/rascunhos. Valores financeiros, palavras-passe e tokens não fazem parte da preparação. Não descarrega fotografias antigas do servidor. O acesso local de uma conta previamente autenticada expira após sete dias sem validação online.

## Durante o trabalho

**Novo trabalho** seleciona contrato e data. Os cards permitem produção e notas de 1 a 5; o formulário de participante não pede entrada/saída. Grupos têm produção conjunta e seleção de participantes. Novos trabalhadores são cadastrados com nome completo e telefone, incluindo sem rede. As fotografias podem ser guardadas antes do primeiro envio do trabalho (JPG/PNG/WebP até 5 MB).

As alterações são gravadas no IndexedDB do dispositivo, com confirmação de gravação. Se o armazenamento falhar ou ficar sem espaço, aparece um erro; não tratar esse erro como uma gravação concluída. O navegador pode eliminar dados se o utilizador limpar o armazenamento da aplicação: manter os dados da aplicação até concluir os envios.

**Encerrar dia** exige avaliações e guarda a intenção de envio. Não significa aprovação administrativa: o dia só fica submetido no servidor após a sincronização confirmada. Os pendentes continuam visíveis. Fotografias guardadas no dispositivo podem ser consultadas sem rede.

## Sincronização

Com a aplicação aberta, a reconexão tenta sincronizar; há também o botão **Sincronizar** e uma verificação periódica enquanto estiver aberta. Não depende de execução em segundo plano quando a aplicação estiver fechada.

Ordem: cadastrar novos trabalhadores, gravar o trabalho, enviar fotografias e submeter o encerramento. Cada operação tem UUID e recibo transacional no servidor para repetir pedidos cuja resposta se perdeu sem duplicar dados. Novos trabalhadores usam identificadores locais mapeados para os do servidor. Se houver alterações durante um envio, a revisão mais recente permanece pendente.

O servidor verifica novamente a sessão, o proprietário do trabalho, as permissões, bloqueios, contrato ativo e versão. Uma nota em falta, trabalhador bloqueado ou alteração administrativa não é ignorada por ter sido feita a preparação antes.

## Conflitos e sessões

Dados locais não substituem automaticamente uma alteração do servidor. **Rever versão no servidor** apresenta as duas produções. A aplicação dos dados locais exige confirmação explícita e só é permitida sobre um rascunho, usando a versão consultada; data e contrato do servidor são preservados. Também é possível guardar uma cópia JSON dos dados e descarregar as fotografias locais. Trabalhos já encerrados não são reabertos automaticamente.

Se a sessão expirar, entrar novamente com a mesma conta e sincronizar. A saída da conta não apaga os pendentes: ficam separados por utilizador e acessíveis após autenticação nessa conta. Uma conta diferente não envia os dados da anterior. A desativação/remoção de permissões é aplicada pelo servidor quando houver ligação.

## Implementação e validação

- Base local: `agroperi-field-v1`, separada por utilizador.
- Preparação: `GET /api/v1/field/offline` (até 10 000 trabalhadores/contratos e 1 000 trabalhos próprios recentes/rascunhos, com erro explícito em caso de excesso).
- Operações: `POST /api/v1/field/offline/apply`, recibos `agri_offline_receipts` por ator e UUID.
- Fotografias: endpoint existente aceita UUID e guarda fotografia/recibo na mesma transação, com validação de imagem e acesso.
- Service worker guarda apenas a aplicação e os ícones; não guarda respostas autenticadas da API em cache HTTP.
