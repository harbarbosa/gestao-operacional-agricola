# Implantação inicial no Hestia

Aplicação: https://agricola.hblabs.com.br

## Estado
Publicada a versão inicial com autenticação, cadastros, tarifas e diário em rascunho. Registro offline, permissões por equipe e fechamento financeiro ainda não estão implementados. Não tratar como operação completa.

Base funcional: commit 8e2670faa0a1d76b822d0121ba12ad83913d8771. As dependências PHP foram resolvidas e fixadas em backend/composer.lock; backend/public/.htaccess foi adaptado para servir o frontend e a API na mesma origem.

Frontend compilado fora do servidor com npm ci e npm run build. Artefato tar.gz SHA-256: c85bc95ab7073c9b6f28a2f7c84198046b0d9830231143a115ca54cb31a4f4aa.

## Organização no servidor
Domínio Hestia exclusivo, banco e usuário MariaDB próprios. A versão inicial está em private/releases/8e2670faa0a1 dentro do diretório do domínio; somente backend/public é document root.

.env e configuração operacional ficam fora do diretório público, com permissões restritas. Credenciais não são armazenadas no Git. Sessões persistem em private/shared/sessions. Foram criadas 21 tabelas e uma conta administradora inicial.

O template Apache específico agricola deriva do padrão, retirando a inclusão global que mapeava a raiz para phpMyAdmin. Os templates de outros domínios não foram alterados. Arquivos estáticos da versão pública recebem acesso de leitura/travessia; a pasta shared permanece privada.

Includes específicos do Nginx aplicam no-cache a index.html, sw.js, registerSW.js e manifest.webmanifest. API envia no-store. HTTP redireciona para HTTPS; certificado Let's Encrypt emitido para o domínio.

Renovação verificada diariamente às 01:37 no fuso do servidor. O script deploy/renew_cert.py é instalado em /usr/local/sbin como arquivo de root, e /etc/cron.d/agricola-cert-renew chama-o somente para este domínio. Não executa código gravável pelo usuário da aplicação como root. Renova quando faltam menos de 30 dias; o teste inicial confirmou certificado válido por mais de 30 dias.

## Migrações
O PHP CLI do servidor carrega pcntl, mas bloqueia pcntl_sigprocmask. O comando spark migrate desta versão do framework tenta chamar essa função. Não foi alterada a lista de funções bloqueadas.

Usar o inicializador de console direto, já executado na implantação:
```sh
php deploy/migrate.php /caminho/da/versao/backend
```

Executar como usuário Hestia do projeto, após preparar .env e backup. A migração opera na base configurada nessa versão; conferir o destino antes de executar.

## Backup e restauração
Backup local diário às 03:17 no fuso do servidor (America/Sao_Paulo), com retenção de sete dias. Script deploy/backup.py é instalado em private/shared; configuração deployment.json e database-client.cnf são privados e não fazem parte do repositório.

Exportação usa single-transaction e gzip. O script recusa novos backups se houver menos de 1 GiB disponível. O primeiro arquivo foi criado e restaurado em uma base temporária: 21 tabelas e a conta inicial foram verificadas, e a base de teste foi removida. A base da aplicação não foi usada como destino do teste.

Backups são locais: não cobrem perda completa do servidor. Cópia externa ainda deve ser definida.

## Verificações executadas
- Nginx e Apache: configuração válida e recarregamento sem interrupção planejada.
- HTTPS com validação do certificado, tela inicial e health HTTP 200.
- Login real, cookie Secure/HttpOnly/SameSite=Lax e acesso autenticado aos cadastros e diário.
- Dados inválidos rejeitados e escrita sem CSRF bloqueada.
- Logout seguido de 401 na consulta de sessão.
- .env e .git inacessíveis; URLs de diretórios privados não devolvem seu conteúdo.
- Service Worker com no-cache.
- Migrações e teste de restauração do backup.
- CI de main com lockfile e roteamento passou na execução 42490763081.

Não houve validação em aparelhos Android/iPhone reais nesta implantação. A persistência/sincronização offline ainda é uma entrega futura.

## Atualizações e recuperação
Antes de nova versão: gerar backup, conferir espaço, compilar o frontend fora do servidor e instalar dependências com composer install --no-dev usando o lockfile. Preparar nova pasta de versão, configurar .env/sessões, aplicar migrações compatíveis e alterar document root pelo Hestia.

Conservar a versão anterior até concluir os testes. Reverter arquivos não reverte automaticamente migrações de banco; avaliar compatibilidade ou restaurar backup. Esta é a primeira versão publicada, sem versão anterior da aplicação para rollback.

A instalação ocupa aproximadamente 17 MiB. O disco do servidor permanece com 94% de uso, cerca de 3,1 GiB livres; ampliar capacidade ou planejar limpeza de arquivos antigos antes de aumentar a operação.
