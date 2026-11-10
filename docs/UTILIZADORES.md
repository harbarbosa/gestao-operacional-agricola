# Utilizadores e permissões

Área acessível por users.view; alterações exigem users.manage. Administradores recebem ambas. Cadastro e edição em modal: nome de utilizador (30 caracteres), email, palavra-passe (mínimo 8 caracteres / máximo 72 bytes), perfil, estado e permissões adicionais. Email e nome únicos. Não há envio automático de credenciais.

Perfis: administrador, chefe de equipa e trabalhador. A matriz do perfil define permissões herdadas, assinaladas sem possibilidade de remoção individual. Para personalizar, usar outro perfil e atribuir permissões adicionais da lista autorizada. Gestão de utilizadores implica consulta. A conta em uso não pode alterar o próprio perfil/permissões/estado. Outro gestor não pode remover o último administrador ativo. As escritas são serializadas e protegidas por versão, sessão e CSRF.

Contas são desativadas, não apagadas, preservando ligações e autoria. Podem ser reativadas pela edição. Alterações de email, palavra-passe, estado ou permissões invalidam sessões anteriores. Auditoria preserva campos autorizados e indicador de alteração de palavra-passe, nunca palavra-passe ou hash.

Migração: 2026-11-09-180000_CreateUserManagement, com agri_user_versions e agri_user_audit. A linha zero da tabela de versões é o bloqueio administrativo. Contas anteriores recebem versão implícita 1 até serem alteradas. O módulo gere acessos existentes; não implementa novos fluxos operacionais para trabalhador/chefe de equipa.
