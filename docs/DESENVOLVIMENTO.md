# Desenvolvimento local

## Estado desta entrega
Estrutura inicial, API de saúde e rascunho local de demonstração. Não há autenticação, migrações de negócio, contas de utilizadores ou sincronização implementadas. Usar apenas dados fictícios. Não implantar para operação real.

## Requisitos
Node 22.12+ (ou versão LTS compatível), npm, PHP 8.3, Composer e extensões exigidas pelo CodeIgniter, incluindo intl e mbstring. MariaDB será necessário na etapa de persistência central; o endpoint de saúde não verifica banco.

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

## Demonstração offline
Na pasta frontend, executar npm run build e npm run preview. Abrir online primeiro para preparar o cache; depois interromper a rede, reabrir e guardar uma nota fictícia. O Service Worker não é ativado no modo dev.
O preview serve apenas o frontend e não encaminha /api. Não indica disponibilidade do backend.

O rascunho é único por navegador e não isolado por conta nesta demonstração. IndexedDB guarda apenas data e observações; não há fila de envio nem transmissão de dados. Limpar dados do navegador elimina o rascunho. Não depender da demonstração para trabalho real.
Ícones finais em PNG e validação de instalação/abertura offline em Android e iPhone ainda fazem parte da entrega PWA completa.

## Origem do backend
Base oficial codeigniter4/appstarter, commit 59112b3d9474b0ac8a9f217e8efb5ea2cd01ea88. Licença original preservada em backend/LICENSE. Dependências Composer ainda precisam de resolução e lockfile antes de implantação reproduzível.

## Validação desta entrega
Frontend: compilação de produção, análise de código e teste de persistência IndexedDB passaram no ambiente de desenvolvimento. PHP/Composer não estavam disponíveis; execução do backend e teste de saúde pendentes de validação no CI. Não houve teste em aparelhos reais.
