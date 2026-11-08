# Arquitetura aprovada

## Decisão
Aprovada pelo responsável em 08/11/2026:
- Frontend: React, TypeScript e Vite.
- Aplicativo: PWA com Service Worker.
- Dados locais: IndexedDB através de Dexie.
- Backend: PHP com CodeIgniter 4.
- Base central: MariaDB.
- Hospedagem inicial: Hestia, com HTTPS e frontend/API na mesma origem.
- Organização: uma empresa, uma aplicação backend modular e uma base central.

Versões exatas serão fixadas nos ficheiros de dependências e lockfiles durante a preparação do projeto, após verificar compatibilidade.

## Estrutura prevista
- frontend/: interface, armazenamento local e sincronização.
- backend/: aplicação CodeIgniter, API, migrações e testes.
- docs/: requisitos, decisões e contratos de integração.
- .github/workflows/: verificações automáticas.

## Módulos do backend
Identidade e permissões; trabalhadores e equipas; clientes e explorações; serviços e preços; diário de campo; sincronização; financeiro; relatórios; auditoria.

Controladores tratam HTTP e autenticação. Serviços de aplicação implementam casos de uso e transações. Regras de domínio concentram cálculos e validações. A persistência guarda entidades e movimentos sem delegar decisões financeiras à interface.

## API e autenticação
API JSON versionada em /api/v1. A interface e a API partilham a origem.
Usar sessões com cookies HttpOnly, Secure em produção e proteção CSRF nas operações de escrita. Nunca guardar credenciais ou tokens de sessão no IndexedDB.
A primeira autenticação e preparação do aparelho exigem ligação.
Uma sessão expirada não elimina registos locais: exige reautenticação para sincronizar. O servidor revalida permissões em todos os pedidos.

## Fluxo offline
1. Preparar o aparelho online, descarregando apenas cadastros autorizados e necessários.
2. Guardar cada alteração de campo e a respetiva operação de envio numa transação local.
3. Atribuir identificador único à operação e conservar versão base do registo.
4. Enviar operações pendentes quando houver ligação e a aplicação estiver ativa; oferecer envio manual.
5. O servidor valida e aplica cada operação atomicamente, guardando a resposta e a chave de idempotência na mesma transação.
6. Uma repetição devolve o resultado anterior sem reaplicar a operação. Reutilizar a chave com outro conteúdo deve ser rejeitado.
7. Conflitos de versão e erros de validação permanecem visíveis e resolvíveis; não descartar pendências.
8. Confirmar localmente a operação só após resposta de sucesso. Aplicar alterações recebidas e avançar o cursor de sincronização na mesma transação local.

Prever paginação, cursor de alterações gerado pelo servidor, exclusões lógicas e sincronização incremental. Não usar o relógio do aparelho como autoridade de ordenação.
Dados locais ficam separados por utilizador; troca de conta não pode expor registos anteriores. Política de retenção e eliminação de pendências ainda precisa de definição.

## Cache e atualizações
O Service Worker guarda a estrutura estática da aplicação. Dados operacionais autorizados ficam no IndexedDB; respostas privadas da API não entram num cache genérico.
Atualizações devem preservar a fila de envio e migrar o esquema local. Não forçar recarregamento durante edição sem guardar.
Funcionamento com a aplicação fechada não é requisito de sincronização garantida.
Testar Android e iPhone em aparelhos reais, incluindo reinício offline e atualização da aplicação com pendências.

## Consistência financeira
Fecho e operações financeiras serão online conforme a arquitetura aprovada.
Guardar modalidade, unidade, quantidade, preço e regra aplicados.
Preço individual do trabalhador pode diferir do preço padrão do serviço; remuneração e cobrança ao cliente são independentes.
Usar decimais e transações no servidor, unicidade por origem do movimento e correções por estorno auditável.
Montantes apresentados offline são provisórios até validação. Divergências de preços não são aceites silenciosamente.

## Implantação
Compilar frontend e instalar dependências de produção fora do servidor sempre que possível. Publicar artefactos de versão identificada.
Configurar o document root para conteúdo público; aplicação, segredos e ficheiros privados ficam fora da raiz pública.
Usar cron para rotinas periódicas com bloqueio contra execução sobreposta. Não exigir Node persistente, Redis ou trabalhadores permanentes na primeira versão.
Separar desenvolvimento/homologação de produção. Preparar backup, recuperação e reversão de versão, incluindo impacto das migrações.

## Condições antes da implantação
A inspeção inicial encontrou 1 CPU, aproximadamente 2 GB de RAM e apenas 3,2 GB livres no disco.
Resolver capacidade de disco, verificar extensões PHP, serviço MariaDB efetivo, domínio, HTTPS, permissões, backups e carga dos sistemas existentes.
A listagem da versão do cliente MariaDB não comprova disponibilidade nem configuração do serviço de base de dados.
Aprovação de arquitetura não significa implantação realizada.

## Primeira entrega de implementação
Estrutura frontend/backend, configuração de desenvolvimento, exemplos de ambiente sem segredos, endpoint de saúde sem informação sensível, migrações iniciais, autenticação/perfis e primeiro teste de persistência offline.
Contratos detalhados, limites offline, versões mínimas de dispositivos e regras de produção coletiva continuam pendentes.
