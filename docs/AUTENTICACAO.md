# Autenticação e perfis

## Implementado nesta etapa
CodeIgniter Shield com sessões: login, logout, consulta da própria identidade e listagem paginada de utilizadores autorizada por users.view. Perfis admin, supervisor e worker. Não há cadastro público ou login por link.

API na mesma origem do frontend. Cookie de sessão HttpOnly, SameSite=Lax e Secure em produção. CSRF em sessão obrigatório para métodos de escrita; o cliente obtém token antes de cada envio. Renovação de sessão no login/logout. Respostas da API sem cache. Senhas e tokens não são persistidos no navegador.

Cada pedido protegido valida autenticação, conta ativa, bloqueio e permissão. A interface ocultar um botão não substitui a autorização no servidor. Permissões de equipa e acesso aos dados individuais devem ser aplicadas nos módulos de negócio futuros.

## Rotas
| Método | Rota | Acesso |
|---|---|---|
| GET | /api/v1/auth/csrf | Público; devolve apenas token CSRF da sessão |
| POST | /api/v1/auth/login | Público com CSRF; limitado a 10 tentativas/minuto por IP |
| GET | /api/v1/auth/me | Sessão válida e conta ativa |
| POST | /api/v1/auth/logout | Sessão válida e CSRF |
| GET | /api/v1/users | Permissão users.view; apenas administrador por padrão |

Credenciais inválidas e conta inativa/bloqueada produzem mensagem genérica. O limite por IP usa cache de ficheiros partilhado do servidor; configurar proxies de confiança apenas quando aplicável e verificar o IP efetivo na implantação. Não utilizar cache dummy em produção.

## Preparar base e contas
Na pasta backend, instalar dependências, configurar .env e uma base MariaDB exclusiva desta aplicação. Executar primeiro em desenvolvimento/homologação:

```sh
composer install
php spark migrate --all
php spark shield:user create -n administrador -e admin@example.test -g admin
php spark shield:user activate -n administrador
```

O comando solicita a senha; não passá-la como argumento nem guardá-la no Git. Substituir o email fictício. Nunca executar estes comandos na base de outro sistema. As migrações de autenticação e settings são fornecidas pelos pacotes oficiais.

Para outras contas, usar supervisor ou worker em -g. Sem grupo explícito, o padrão é worker. Ativar cada conta após criação. A gestão visual de contas e a recuperação de senha ficam para uma próxima etapa. Não há conta ou senha padrão.

## Offline e limites desta entrega
O ecrã de login precisa de ligação. A abertura offline da estrutura PWA não equivale a uma sessão autorizada. Esta etapa substitui o formulário fictício inicial pela entrada autenticada; o código de persistência demonstrativa permanece separado e não é apresentado como módulo operacional.

Não foram implementados cache de identidade, armazenamento por utilizador, fila de operações nem sincronização. Registo offline autenticado e proteção da troca de utilizador serão entregues juntos; não utilizar dados reais nesta versão. O logout não elimina rascunhos automaticamente.

## Verificações
Frontend: teste de CSRF e transporte de sessão, tratamento de 401, resposta inválida, compilação e análise de código.
Backend: credenciais válidas/inválidas, falta de CSRF, conta inativa, sessão desativada, logout, limite de tentativas, acesso anónimo e separação entre os três perfis. Testes usam SQLite em memória; validação das migrações em MariaDB e aparelhos reais continua necessária antes da implantação.
