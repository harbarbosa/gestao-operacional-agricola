# Decisões e pontos pendentes

## Confirmado pelo responsável
- Uma única empresa.
- Registo de trabalho offline obrigatório desde a primeira versão.
- Suporte a Android e iPhone.
- Forma de remuneração variável e configurável; não fixar uma única modalidade.
- Preços individuais por trabalhador para o mesmo serviço.
- Arquitetura aprovada: React/TypeScript/Vite PWA, IndexedDB/Dexie, CodeIgniter 4 e MariaDB. Ver [arquitetura](ARQUITETURA.md).
- Sincronização com aplicação aberta e fecho financeiro online.

As restantes propostas ainda precisam de validação.

| Tema | Proposta inicial | O que confirmar |
|---|---|---|
| Plataforma | Web responsiva instalável (PWA) para Android e iPhone (aprovado) | Versões mínimas e navegadores suportados |
| Operação offline | Obrigatória na primeira versão (confirmado) | Duração sem ligação e dispositivos partilhados |
| Organização | Uma empresa (confirmado) | Sem arquitetura multiempresa no escopo inicial |
| Tecnologia | React/TypeScript/Vite, PWA, Dexie, CodeIgniter 4 e MariaDB (aprovado) | Fixar versões compatíveis e verificar extensões PHP |
| Idioma/moeda | Português de Portugal / EUR | Outros idiomas e moedas? |
| Horários | Europe/Lisbon | Pausas, turnos noturnos e horas extra |
| Remuneração | Modalidade e unidade configuráveis (confirmado): hora, dia, produção e outras unidades | Preços individuais confirmados; regras por função/equipa e combinação ainda a validar |
| Produção coletiva | Registo separado da individual | Como distribuir quantidades e valores? |
| Cobrança ao cliente | Quantidade própria por serviço | A unidade cobrada pode diferir da unidade paga? |
| Validação | Fluxo rascunho/validado/fechado | Quem valida e quem fecha? |
| Correções | Estorno com motivo e auditoria | Quem autoriza e quais períodos ficam bloqueados? |
| Financeiro | Controlo operacional | Há impostos, descontos ou integrações fiscais no escopo? |
| Acesso do trabalhador | Opcional | Ativar na primeira versão? |
| Infraestrutura | Hestia como hospedagem inicial prevista; desenvolvimento e produção separados | Confirmar versões disponíveis, recursos, domínio, HTTPS, tarefas agendadas e backups |
| Retenção | Definir antes do uso real | Quais dados de identificação são necessários e por quanto tempo? |

## Validação em dispositivos
Executar os mesmos cenários em Android e iPhone, no navegador e no modo instalado proposto:
- Preparação online e abertura sem rede.
- Registo de presenças e produção offline.
- Fecho e reabertura da aplicação com preservação das pendências.
- Recuperação da ligação e sincronização com a aplicação aberta.
- Reenvio sem duplicação e conflito entre dois dispositivos.
- Sessão expirada e falha de armazenamento com aviso claro e preservação das pendências quando possível.

Não considerar sincronização com a aplicação fechada como requisito garantido. Definir versões mínimas e testar em aparelhos reais antes do piloto.

## Próxima sessão de definição
Usar três exemplos: trabalhador pago por hora, trabalhador pago por produção individual e equipa com produção coletiva. Para cada um, definir quantidade paga, quantidade cobrada ao cliente, preço, pausas, arredondamento, fecho e eventual correção.

## Hospedagem inicial prevista
O responsável indicou Hestia como primeira opção de hospedagem. Esta escolha de hospedagem é provisória; a tecnologia da aplicação está aprovada em ARQUITETURA.md.

Antes da implantação, verificar versões e serviços disponíveis, capacidade do servidor, suporte a processos da aplicação, base de dados, tarefas agendadas, domínio/HTTPS e procedimento de backup e restauro. Não há autorização implícita para alterar o servidor ou publicar a aplicação nesta etapa.
