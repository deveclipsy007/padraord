# Padrão RD OS — Controle de execução
Data: 10/09/2026. Execução inline, sem multiagentes.

## Resultado
Um caso preserva contexto e responsabilidade desde a entrada até o encerramento. Aprovação da Viabilidade, aceite da entrega e contratação da Gestão são decisões distintas.

## Leitura
1. 01-visao-e-requisitos.md
2. 02-auditoria.md
3. 03-jornada-e-responsabilidades.md
4. 04-ux-e-identidade.md
5. 05-dominio-dados-e-apis.md
6. 06-inteligencia-e-marta.md
7. 07-propostas-e-aceite.md
8. 08-execucao-e-testes.md
9. 09-operacao-e-publicacao.md

## Metas
- [~] Meta 1: cliente, lead, atribuição, contexto, revisão e Viabilidade (em verificação; áudio retomável e contexto preservado validados).
- [~] Meta 2: necessidades, fornecedores, orçamento, proposta, link e contrato (em verificação; cotação, assinatura e link externo validados).
- [~] Meta 3: produção, pós-evento, acabamento e validação (regras persistentes, métricas reais e release local verificados; jornadas integradas e gates externos pendentes).

Estados: pendente → em execução → em verificação → concluída.
Meta ativa: 1 → 2 → 3 em execução incremental. Não marcar concluída com base apenas em existência de tela/rota.
Cada evidência deve registrar comando/jornada, resultado, data e limitação.
Integrações reais e publicação são verificações separadas de implementação local.

## Critério final
Todas as tarefas de 08-execucao-e-testes.md executadas; jornadas completas em três breakpoints; persistência, permissões, recuperação e isolamento comprovados; nenhum dado real substituído por cenário de demonstração.
