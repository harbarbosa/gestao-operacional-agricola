# Plano de desenvolvimento

## Etapa 0 — Definição
Validar regras de remuneração e cobrança, papéis, fecho, duração offline e dispositivos, arquitetura e critérios da primeira versão.
Entrega: decisões documentadas e exemplos de cálculo aprovados.

## Etapa 1 — Fundação
Preparar projeto, ambientes, base de dados, migrações, autenticação, permissões, estrutura PWA proposta, armazenamento local, protocolo de sincronização e verificações automáticas.
Entrega: entrada no sistema com perfis e isolamento de acesso verificados.

## Etapa 2 — Cadastros
Trabalhadores, equipas, clientes, explorações, serviços, unidades e preços.
Entrega: cadastros utilizáveis no diário, com validações e histórico de preços.

## Etapa 3 — Operação de campo
Diário com registo offline, presenças, horários, produção, avaliações, sincronização, conflitos, validação e fecho.
Entrega: registar um dia sem internet, reiniciar a aplicação, sincronizar e fechar online sem perda ou duplicação; conflitos são apresentados para resolução.

## Etapa 4 — Financeiro
Contas individuais, adiantamentos, pagamentos, recebimentos, saldos e correções auditáveis.
Entrega: saldos conciliados com movimentos e exemplos de referência.

## Etapa 5 — Gestão e relatórios
Painel e exportação PDF/Excel por trabalhador, cliente e período.
Entrega: exportações e indicadores conciliados com os registos fechados.

## Etapa 6 — Piloto e disponibilização
Testes com dados fictícios, validação em telemóvel com perda e recuperação de rede, reinício offline, sessão expirada e edições concorrentes, revisão de permissões, backups/restauro, documentação de instalação e piloto com equipa reduzida.
Entrega: fluxo completo aceite e procedimento de recuperação comprovado.

## Sequência
Etapa 0 → 1 → 2 → 3 → 4 → 5 → 6. Proteção de dados, auditoria e testes acompanham todas as etapas.

## Critério de conclusão de uma tarefa
- Critérios de aceitação cumpridos.
- Validações e permissões aplicadas no servidor.
- Testes relevantes ao risco executados.
- Nenhum segredo ou dado pessoal real incluído no código.
- Documentação atualizada e pull request revisto.

## Calendário
Sem prazo ou orçamento fechado. Estimar após validar a etapa 0; não tratar esta sequência como compromisso de datas.
