# Padrão RD: 70% e sistema visual — plano de implementação

> **Para quem executar:** seguir uma missão por vez; começar pelos testes que demonstram a lacuna; só marcar um critério depois de uma execução independente do Pulse. Não usar agentes paralelos nesta tarefa.

**Objetivo:** levar o Padrão RD de 143/282 (50,7%) para uma rota verificável de 198/282 (70,2%), enquanto a experiência deixa de parecer decorativa e passa a usar uma linguagem sóbria, clara e operacional.

**Arquitetura:** manter Laravel como autoridade de dados e regras, Inertia/React como interface e SQLite/MariaDB como bancos compatíveis. Cada missão trabalha na worktree `feat/pulse-delivery-70`, é integrada na branch observada `ciclo-04-consolidacao`, recebe testes ligados ao requisito e só então entra na auditoria isolada do Operon Pulse.

**Tecnologias:** PHP 8.4+, Laravel 13, React 19, TypeScript, Vite, SQLite, MariaDB de teste, PHPUnit, Vitest, Playwright e Pint.

## Base confirmada

- Medição atual do Pulse: **143/282 pontos, 50,7%, 48/85 critérios**.
- Auditoria atual: `04e84945-7d93-4abe-b77d-a2be39567735`, sem falhas; revisão monitorada `e88bb42a0dc5c37b73d001eda8c44adf425810e3`.
- O escopo, pesos e versão V1 já foram aprovados. Esta execução não remove, adia nem repesa critérios para aumentar o número.
- “70%” aqui significa 198 pontos com evidência vigente. Teste local, alegação do agente e uma tela bonita não alteram o número até a auditoria do Pulse registrar a prova.
- Critérios humanos, integrações externas e aceites reais continuam explicitamente pendentes quando não houver evidência apropriada.

## Rota mensurável até 70,2%

| Ordem | Missão | Critério(s) | Pontos | Acumulado possível |
| --- | --- | --- | ---: | ---: |
| 1 | Anexos privados por caso | `rd-49e7ed13bc80` | +3 | 146/282 · 51,8% |
| 2 | Documentos e aceite rastreáveis | G1, G2, G4, G6 e canais | +17 | 163/282 · 57,8% |
| 3 | Produção operacional | F1–F7 | +16 | 179/282 · 63,5% |
| 4 | Comparação de cotações | `rd-42a3f939922e` | +3 | 182/282 · 64,5% |
| 5 | Cadeia de bloqueios e decisões | `rd-4a06da12e862` | +3 | 185/282 · 65,6% |
| 6 | Consolidação do briefing | A8 | +5 | 190/282 · 67,4% |
| 7 | Jornadas decisórias responsivas | `rd-d6834650a575` | +5 | 195/282 · 69,1% |
| 8 | Edição, lote, filtros e atalhos | `rd-3134fc123695` | +3 | **198/282 · 70,2%** |

Os totais são uma rota de trabalho, não uma antecipação de validação. Se uma missão expuser uma dependência, regressão ou requisito humano, ela permanece “precisa revalidar” e a próxima missão não recebe seus pontos.

## Direção visual: sistema de controle sóbrio

A revisão visual não ganha pontos sozinha. Ela será aplicada em cada tela que esta rota tocar e depois consolidada nas páginas principais.

- **Hierarquia:** uma superfície-base por área, blocos de dados em listas e tabelas quando a leitura é comparativa, e cards somente para decisões ou estados resumidos.
- **Composição:** menos ornamentos, menos gradientes, menos blur e menos sombras; respiro consistente, títulos precisos, metadados discretos e ações agrupadas por contexto.
- **Paleta:** grafite, branco quente e cinzas neutros; cor é reservada para estado, alerta e ação. A paleta não depende do verde para sugerir “pronto”.
- **Forma:** cantos menores e coerentes, bordas finas, camadas claras e densidade de informação adequada ao uso operacional.
- **Movimento:** somente feedback curto e acessível; respeitar `prefers-reduced-motion`.
- **Qualidade iOS/macOS:** navegação calma, cabeçalho funcional, controles com rótulos explícitos, foco visível, leitura boa em 390 px, 1024 px e desktop.

Antes da primeira página nova, registrar capturas de Dashboard, oportunidade, documentos e produção como referência. Ao concluir cada bloco de interface, comparar os mesmos tamanhos de tela e corrigir overflow, contraste, foco e ordem de leitura.

## Missão 1 — anexos privados por caso (+3)

**Critério:** `rd-49e7ed13bc80` — gerenciar anexos privados com tipos, quotas e acesso por caso.

**Resultado esperado:** um usuário autenticado envia PDF, PNG, JPEG, WebP ou TXT para um caso; escolhe módulo e, quando aplicável, liga o arquivo a requisito, validação técnica, pagamento ou local daquele mesmo caso. O sistema mostra nome, tipo, tamanho, vínculo, estado e consumo de quota; o download só funciona pela rota autorizada do caso; arquivamento preserva bytes e trilha de auditoria.

1. Acrescentar primeiro uma asserção de contrato de apresentação em `tests/Feature/CaseAttachmentsTest.php`: a resposta Inertia expõe somente o DTO necessário (`id`, nome, tipo MIME, tamanho, módulo, vínculo, estado, horários e URL autorizada), nunca caminho de storage ou hash interno. Confirmar a falha.
2. Ajustar `CaseAttachments::list()` para montar esse DTO explicitamente, normalizar o vínculo e manter o caminho/hashes exclusivamente no servidor.
3. Criar um painel de anexos reutilizável em `resources/js/components/CaseAttachmentsPanel.tsx`, usando formulário multipart do Inertia, seletor de módulo, vínculo compatível, quota legível, download, arquivar e restaurar. O painel deve usar estrutura plana e operacional da direção visual.
4. Passar os props tipados por `DocumentsHub.tsx` e manter textos/rótulos acessíveis; anexos arquivados continuam presentes no histórico, mas são visualmente distintos.
5. Escrever antes da UI uma jornada Playwright que cria um caso, envia um TXT de prova, confirma que aparece sem caminho interno, arquiva, restaura e baixa pelo endpoint do próprio caso. A expectativa deve falhar enquanto o painel não existir.
6. Verificar: `php artisan test --filter CaseAttachmentsTest`; `./vendor/bin/pint --dirty`; `npm run quality`; Playwright desktop/tablet/mobile focado; migração limpa SQLite e MariaDB. Só então integrar e solicitar auditoria do Pulse.

## Registro da Missão 1 — 15 de setembro

- Implementação concluída na worktree: armazenamento privado, verificação MIME/extensão, quota por caso, hash de integridade, vínculo a briefing/validação/pagável/local, download autorizado, arquivamento/restauração e auditoria.
- A interface de Documentos agora apresenta uma lista de anexos operacional, com quota, vínculo, estado e ações. A inspeção em tablet encontrou uma ação fora da grade; o teste de limites reproduziu a falha e a grade foi corrigida.
- Evidência local: `CaseAttachmentsTest` passou com 4 testes e 82 asserções; a suíte PHP completa passou com 253 testes e 1.716 asserções; `npm run quality` e a jornada Playwright em desktop/tablet/mobile passaram.
- MariaDB não foi aprovado: o script parou com `Connection refused` em `127.0.0.1:3306`; não existe servidor ou contêiner local configurado. A migração é aditiva e passou em SQLite, mas essa evidência externa continua pendente.
- A implementação foi integrada por fast-forward na branch monitorada em `7f72ae9` (`feat: add private case attachments`).
- O Pulse recebeu a revisão de escopo r5 sem mudar critérios, pesos ou denominador: os quatro casos de `CaseAttachmentsTest.php` foram associados ao G5. A jornada Playwright continua como regressão de interface separada, sem ser usada para atribuir estes pontos.
- A auditoria isolada da r5 concluiu em 145,7 s com 49 critérios comprovados, zero falhas e zero avisos. O G5 recebeu evidência válida e a medição passou de 143/282 (50,7%) para 146/282 (51,7%, conforme apresentação do Pulse).
- MariaDB continua pendente por indisponibilidade local (`Connection refused` em `127.0.0.1:3306`) e não foi usado como evidência para a promoção do critério.

## Missão 2 — documentos, congelamento, contrato e aceite (+17)

**Critérios:** `rd-1178fc8b109a`, `rd-9868cc450c0a`, `rd-0a71f3bbc0b5`, `rd-d14b396dac6a`, `rd-37450a561e41`.

1. Mapear lacunas entre composição atual e cada critério; não associar a mesma prova a resultados diferentes sem demonstrar cada expectativa.
2. Criar testes de composição por seções, exclusões e fontes; congelar JSON, render e ativos da versão liberada; testar que revisão posterior não muda versão publicada.
3. Cobrir contrato com dados obrigatórios, cláusulas, plano de pagamento e reabertura auditada.
4. Implementar aceite transacional: versão/hash exatos, projeto/onboarding/outbox idempotentes; injetar adaptador local, sem enviar comunicação real.
5. Preparar contrato de canais atrás de configuração explícita, sem ativar integração externa; demonstrar compatibilidade por testes.
6. Validar no navegador a proposta, o contrato e o aceite em desktop, tablet e celular, aplicando a linguagem visual revisada aos espaços de leitura e decisão.

## Missão 3 — produção operacional (+16)

**Critérios:** `rd-633ed935f8d0`, `rd-40a7ac37faf5`, `rd-777ec0f712d7`, `rd-7988d8dcc556`.

1. Escrever falhas de aceitação para escala/conflitos, checklist de montagem e devolução, cronograma com dependências e ordem de serviço.
2. Implementar serviços transacionais, vínculos a local e fornecedor, e evidências preservadas por caso.
3. Trocar qualquer grade ornamental por calendário/linha do tempo legíveis, com estados, dependências e próximo bloqueio claros.
4. Rodar testes de conflito, recuperação, persistência e as três larguras de viewport; medir a duração da suíte antes de classificar frequência automática.

## Missão 4 — comparação de cotações (+3)

**Critério:** `rd-42a3f939922e`.

1. Redigir testes de normalização de unidade/pacote, validade, revisão e diferença explícita de escopo.
2. Criar comparação exportável com decisão, justificativa e histórico sem alterar a cotação original.
3. Verificar layout de tabela responsiva e leitura por teclado.

## Missão 5 — bloqueios e fila de decisões (+3)

**Critério:** `rd-4a06da12e862`.

1. Modelar bloqueador, dependências, impacto e responsável, incluindo remoção/reabertura sem apagar histórico.
2. Expor fila ordenada por bloqueador crítico, itens destravados, importância e esforço; separar ganho direto de trabalho desbloqueado.
3. Testar transições, cadeia circular, prioridade e estado após reinício.

## Missão 6 — consolidação do briefing (+5)

**Critério:** resultado A8 do escopo aprovado.

1. Auditar migrações e campos legados com fixture representativa; escrever teste de atualização sem perda.
2. Consolidar dados tipados e revisão sem transportar suposições como requisitos confirmados.
3. Testar SQLite e MariaDB, concorrência e a jornada completa de briefing.

## Missão 7 — jornadas decisórias nos três tamanhos (+5)

**Critério:** `rd-d6834650a575`.

1. Definir três jornadas observáveis: criar/qualificar oportunidade, transformar briefing em decisão e fechar decisão documental/operacional.
2. Automatizar as jornadas em 1440 px, 1024 px e 390 px, incluindo foco, leitura, overflow e persistência.
3. Corrigir os problemas de estrutura encontrados; a aprovação visual humana continua separada do teste automatizado.

## Missão 8 — edição, lote, filtros e atalhos (+3)

**Critério:** `rd-3134fc123695`.

1. Escolher superfícies reais onde edição no lugar e lote reduzem passos, sem criar controles genéricos sem caso de uso.
2. Testar filtros combináveis, seleção em lote, desfazer quando aplicável e atalhos que não sequestram campos de texto.
3. Conferir foco, atalhos no macOS, responsividade e persistência.

## Procedimento obrigatório ao fim de cada missão

1. Rodar primeiro as verificações focadas e depois a regressão apropriada.
2. Formatar, testar tipo e gerar build antes de qualquer alegação.
3. Revisar diff, segredos, migrações e compatibilidade SQLite/MariaDB.
4. Commitar somente a missão coesa na worktree e integrar por fast-forward na branch `ciclo-04-consolidacao`.
5. Registrar a alegação e o mapeamento critério→testes no Pulse; executar o lote isolado. Se a árvore mudar durante a execução, descartar o resultado.
6. Atualizar a medição do painel somente a partir do resultado da auditoria e registrar o que ainda impede o próximo avanço.

## Benchmark e encerramento da rota

- Para cada fluxo novo, medir a suíte com dados locais representativos depois da correção funcional; registrar duração e falhas, sem inventar metas de desempenho.
- Antes de alegar 70%, rodar PHPUnit relevante, `npm run quality`, Playwright nos três tamanhos, migrações limpas SQLite/MariaDB e a auditoria isolada do Pulse.
- Fazer capturas antes/depois das quatro telas-base. A comparação deve demonstrar melhor hierarquia e menor excesso visual sem reduzir a informação operacional.
