# Desenvolvimento local

## Estado desta entrega
Estrutura inicial, API de saúde, login e perfis. Não há migrações de negócio nem sincronização implementadas. Ver [Autenticação](AUTENTICACAO.md) para preparar a base e contas. Usar apenas dados fictícios. Não implantar para operação real.

## Requisitos
Node 22.12+ (ou versão LTS compatível), npm, PHP 8.3, Composer e extensões exigidas pelo CodeIgniter, incluindo intl e mbstring. MariaDB é necessário para login; o endpoint de saúde não verifica banco.

## Backend
Na pasta backend:
```sh
composer install
cp .env.example .env
php spark serve --host 127.0.0.1 --port 8080
```
Definir CI_ENVIRONMENT = development apenas no ambiente local. Não colocar credenciais no Git. A configuração de banco e as migrações serão adicionadas numa próxima entrega.

## Frontend
Noutro terminal, na pasta frontend:
```sh
npm ci
npm run dev
```
O Vite encaminha /api para localhost:8080. Verificar /api/v1/health através do frontend.

## Verificações
```sh
cd frontend
npm run lint
npm test
npm run build
```
No backend: composer test. O teste de saúde está em tests/app/HealthTest.php.

## Offline
Na pasta frontend, executar npm run build e npm run preview para testar a estrutura PWA. O preview não encaminha /api: testar autenticação em npm run dev com backend ativo ou numa origem configurada que sirva frontend e API.

A estrutura pode ser preparada online e aberta sem rede, mas login e recuperação da sessão requerem API disponível nesta etapa. Registo offline autenticado e sincronização ainda não foram implementados. O módulo demonstrativo db.ts continua coberto por teste de persistência, sem ser exposto na interface autenticada. Não usar dados reais.

## Origem do backend
Base oficial codeigniter4/appstarter, commit 59112b3d9474b0ac8a9f217e8efb5ea2cd01ea88. Licença original preservada em backend/LICENSE. Dependências Composer ainda precisam de resolução e lockfile antes de implantação reproduzível.

## Validação desta entrega
Frontend: compilação de produção, análise de código e teste de persistência IndexedDB passaram no ambiente de desenvolvimento. PHP/Composer não estavam disponíveis; execução do backend e teste de saúde pendentes de validação no CI. Não houve teste em aparelhos reais.
