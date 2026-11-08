# Gestão Operacional Agrícola

Aplicação para gerir equipas agrícolas em Portugal, do registo do trabalho no campo ao apuramento de valores a pagar aos trabalhadores e a receber dos clientes.

## Estado
Fundação técnica em desenvolvimento: frontend PWA, API de saúde, autenticação por sessão e perfis. Cadastros operacionais e tarifas com vigência disponíveis; locais e diário de campo em rascunho disponíveis; validação, fechamento e sincronização ainda não implementados. Requisitos derivados do documento fornecido pelo responsável do projeto.

## Documentação
- [Escopo e regras de negócio](docs/ESCOPO.md)
- [Plano de desenvolvimento](docs/PLANO.md)
- [Decisões pendentes](docs/DECISOES.md)

## Primeira versão
Autenticação e permissões; trabalhadores e equipas; clientes e explorações; serviços e preços; registo diário; validação e fecho; contas de trabalhadores e clientes; relatórios PDF/Excel e painel de gestão.

## Processo de desenvolvimento
Cada funcionalidade deve partir de uma issue com critérios de aceitação, ser implementada numa branch própria e revista por pull request. A conclusão exige validação dos critérios, testes relevantes e atualização da documentação.

Não publicar credenciais, dados pessoais de trabalhadores, dados reais de clientes ou cópias de bases de dados neste repositório. Usar dados fictícios nos exemplos e testes.

## Executar o projeto
Consultar [Desenvolvimento local](docs/DESENVOLVIMENTO.md).

## Autenticação
Consultar [Preparação de contas e perfis](docs/AUTENTICACAO.md).

## Cadastros
Consultar [Cadastros operacionais](docs/CADASTROS.md).

## Preços
Consultar [Tarifas, vigências e simulação](docs/PRECOS.md).

## Diário de campo
Consultar [Locais e registos de campo](docs/DIARIO_CAMPO.md).
