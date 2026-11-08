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

## Definições confirmadas
- Operação de uma única empresa.
- Registo offline obrigatório na primeira versão.

## Funcionamento offline proposto (a validar)
- Preparar o dispositivo com autenticação e sincronização inicial online; offline não significa primeiro acesso sem internet.
- Disponibilizar previamente apenas cadastros necessários às equipas autorizadas.
- Guardar rascunhos e alterações localmente, com fila persistente que sobreviva a reinícios.
- Mostrar estado de cada registo: pendente, sincronizado, conflito ou erro; indicar última sincronização.
- Sincronizar ao recuperar ligação enquanto a aplicação estiver ativa, com opção manual. Não depender exclusivamente de sincronização em segundo plano.
- Usar identificadores de operação e controlo de versão para evitar duplicação e detetar edições simultâneas; nunca sobrescrever silenciosamente conflitos.
- Proposta: validação definitiva, fecho financeiro, pagamentos e recebimentos exigem ligação e validação no servidor. Registos offline são provisórios até sincronizar.
- Se a sessão expirar, preservar a fila e exigir nova autenticação antes do envio. O servidor revalida permissões, mesmo para registos criados offline.
- Definir comportamento em troca de utilizador, saída, perda do dispositivo e armazenamento cheio; não eliminar pendências silenciosamente nem expô-las a outro utilizador.
- Armazenamento local não substitui backup: informar falhas e limitações, incluindo perda de dados se o armazenamento do navegador for apagado antes da sincronização.

## Remuneração configurável
Requisito confirmado: a modalidade varia e deve ser configurável.
- Permitir configurar por serviço a unidade de pagamento: hora, dia, caixa, árvore, hectare ou outra unidade cadastrada.
- Manter unidade e preço de remuneração independentes da unidade e preço de cobrança ao cliente.
- Proposta: cada lançamento identifica serviço, modalidade, unidade, quantidade e preço aplicado. Um trabalhador pode ter vários lançamentos no mesmo dia, sem somar modalidades automaticamente sobre o mesmo trabalho.
- Guardar a versão da regra e do preço utilizada; alterações de cadastro não recalculam registos fechados.
- Disponibilizar as regras necessárias no dispositivo antes do uso offline; divergências encontradas na sincronização devem ficar visíveis para validação, sem substituição silenciosa dos valores.
- Pagamento por produção coletiva exige regra de distribuição explícita antes da implementação.
- Ainda a validar: preços específicos por trabalhador/função/equipa, proporcionalidade de diárias, pausas, horas extra, mínimos, arredondamento e eventual combinação de modalidades.
