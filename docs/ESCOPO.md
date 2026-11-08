# Escopo e regras de negócio

## Objetivo
Centralizar a gestão operacional agrícola em Portugal numa aplicação adaptada a telemóveis, tablets e computadores.

## Perfis
- Administrador: gestão completa de cadastros, preços, pagamentos e relatórios.
- Chefe de equipa/controlador: registo de presenças, produção, avaliação e fecho, conforme permissões definidas.
- Trabalhador: consulta apenas dos próprios dias e valores, se ativada.

## Módulos da primeira versão
1. Trabalhadores: identificação, contactos, histórico, adiantamentos, pagamentos e saldo.
2. Equipas: composição e responsável.
3. Clientes e explorações: locais e histórico de serviços.
4. Serviços: categorias e unidades configuráveis (hora, dia, caixa, árvore, hectare e outras).
5. Preços: remuneração de trabalhadores separada do preço cobrado ao cliente.
6. Diário de campo: data, cliente, exploração, serviço, responsável, trabalhadores, horários, quantidades, avaliações e observações.
7. Validação e fecho: confirmação dos registos e atualização das contas.
8. Conta do trabalhador: bruto acumulado, pagamentos, adiantamentos e saldo a pagar.
9. Conta do cliente: serviços apurados, recebimentos e saldo a receber.
10. Relatórios: individuais e por período, PDF/Excel, painel diário/semanal/mensal.
11. Administração: utilizadores, permissões, histórico de alterações e cópias de segurança.

## Regras propostas para implementação (a validar)
- Estados do diário: rascunho → validado → fechado.
- Só registos fechados geram movimentos financeiros.
- Fechar novamente o mesmo registo não pode duplicar valores.
- Guardar quantidade, unidade, preço aplicado e resultado do cálculo no fecho; mudanças futuras de preço não alteram o histórico.
- Corrigir um dia fechado exige autorização e registo de motivo; usar estorno e novo movimento, sem apagar o histórico.
- Usar precisão decimal nos cálculos monetários e definir explicitamente arredondamentos.
- Não confundir valores apurados para cobrança com emissão de faturas fiscais.
- Cada perfil acede apenas aos dados autorizados; o trabalhador nunca consulta a conta de outro.
- Datas de trabalho e apresentação de horários seguem Europe/Lisbon; moeda proposta EUR.

## Fórmulas base propostas
- Remuneração: quantidade elegível × preço do trabalhador.
- Valor do serviço ao cliente: quantidade faturável × preço do cliente.
- Saldo do trabalhador: créditos de trabalho + ajustes − adiantamentos − pagamentos.
- Saldo do cliente: serviços apurados + ajustes − recebimentos.
- Diferença operacional: valor de serviços ao cliente − remuneração dos trabalhadores. Não representa lucro líquido sem contabilizar os restantes custos.

Quantidade individual, produção coletiva, pausas, horas extra, mínimos diários e arredondamentos ainda precisam de decisão.

## Fora do escopo inicial proposto
Aplicações nativas separadas, integração com máquinas, geolocalização, folha salarial legal, emissão fiscal certificada e integrações contabilísticas. Só incluir mediante definição específica.

## Requisitos transversais
Interface simples; base centralizada; validação no servidor; controlo de acesso; auditoria; proteção de dados; backups automáticos e teste de recuperação; expansão para novas equipas e clientes.
