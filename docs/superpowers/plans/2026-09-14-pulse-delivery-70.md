# Padrão RD — Implementation Plan para a faixa de 65–70%

> Execução nesta tarefa com as skills writing-plans, executing-plans e test-driven-development. Sem multiagentes, por instrução de Yohann.

**Goal:** concluir resultados reais do escopo aprovado, medir o comportamento e levar o escopo verificado de 39,7% à faixa de 65–70%, sem alterar os pesos.

**Architecture:** evoluir o Laravel/Inertia/React existente; serviços transacionais com revisão otimista, rotas autenticadas, migrações aditivas e UI operacional. Desenvolver em worktree, integrar entregas verificadas à branch ciclo-04-consolidacao monitorada pelo Pulse e executar novamente o executor independente.

**Tech Stack:** PHP 8.4+, Laravel 13, React/TypeScript, SQLite local e banco SQL compatível de teste, PHPUnit, Vitest e Playwright.

## Base e regras

- Base: ae15ee1; 85 critérios, 282 pontos, 112 pontos comprovados (39,7%).
- Baseline do Pulse: 297 casos, sem falha, 73,7s. Baseline PHP na worktree: 214 testes/1.409 asserções, 6,692s.
- Não alterar requisitos/pesos nem retirar critérios difíceis. Associar testes específicos aos resultados completos; homologar apenas pelo executor do Pulse.
- Testes novos começam por falha que demonstra o comportamento ausente; cobrir casos adversos, concorrência, autorização, isolamento e persistência.
- Nenhum e-mail real, aceite comercial de cliente, pagamento, deploy público ou chamada paga de IA durante os testes. Esses fluxos terão adaptadores/ambientes de teste e permanecem dependentes de evidência real quando o critério a exigir.
- A ligação critério→testes será revisada a cada entrega sem mudar o denominador; resultados antigos incompatíveis serão revalidados.

## 1. Cadastro comercial completo

Impacto condicionado a comprovação: +3 pontos; acumulado possível 115/282 = 40.7%.

**Arquivos:** `app/Http/Controllers/FoundationController.php`, `app/Services/QualificationService.php`, `app/Models/Contact.php`, `resources/js/Pages/ClientProfile.tsx`, `tests/Feature/ClientContactRolesTest.php`. Migrações próprias em `database/migrations/2026_09_14_*` e rotas em `routes/web.php`.

- [ ] **Completar cadastro empresarial, faturamento e papéis de contato** — 3 pontos; `rd-b11634d52499`. A1/A2: endereços e condições de faturamento, segmentação, contato principal único e decisor obrigatório.

- [ ] Escrever as asserções de aceitação e reproduzir a falha antes de implementar.
- [ ] Implementar serviço, validação/autorização, migração e UI completa, preservando padrões existentes.
- [ ] Rodar testes focados, casos adversos e regressões da área; revisar diff.
- [ ] Commit da entrega, integração à branch monitorada, registro de alegação e associação das novas verificações.
- [ ] Executar Pulse e conferir critérios/artefatos; atualizar esta lista e o relatório com resultados observados.

## 2. Briefing como entidade de evento

Impacto condicionado a comprovação: +16 pontos; acumulado possível 131/282 = 46.4%.

**Arquivos:** `app/Models/EventBrief.php`, `app/Services/EventBriefService.php`, `app/Http/Controllers/EventBriefController.php`, `resources/js/Pages/Briefing.tsx`, `resources/js/components/EventBriefEditor.tsx`, `tests/Feature/EventBriefTest.php`, `tests/Feature/BriefRequirementsTest.php`, `tests/Feature/BriefProgramTest.php`, `tests/Feature/BriefSourcesTest.php`. Migrações próprias em `database/migrations/2026_09_14_*` e rotas em `routes/web.php`.

- [ ] **Concluir o briefing tipado e a migração dos campos legados** — 5 pontos; `rd-9f6e4e904d4a`. A4/A8: entidade de evento tipada, campos obrigatórios, revisão concorrente, migração sem perda e consolidação do schema.
- [ ] **Registrar requisitos por área com classificação e origem** — 5 pontos; `rd-8218b5105aae`. A5/B4: hipóteses não viram necessidades automaticamente; confirmação por item e enums fechados.
- [ ] **Editar programação com limites de horário e alertas de sobreposição** — 3 pontos; `rd-35c12d1db9b5`. A6: blocos dentro da data, timeline e reordenação acessível no celular.
- [ ] **Ligar campos aprovados ao instante do áudio e preservar a origem** — 3 pontos; `rd-c4df2ebc6aac`. A7: player sincronizado, fontes por campo e retenção de gravações ligadas a briefing aprovado.

- [ ] Escrever as asserções de aceitação e reproduzir a falha antes de implementar.
- [ ] Implementar serviço, validação/autorização, migração e UI completa, preservando padrões existentes.
- [ ] Rodar testes focados, casos adversos e regressões da área; revisar diff.
- [ ] Commit da entrega, integração à branch monitorada, registro de alegação e associação das novas verificações.
- [ ] Executar Pulse e conferir critérios/artefatos; atualizar esta lista e o relatório com resultados observados.

## 3. Financeiro operacional

Impacto condicionado a comprovação: +17 pontos; acumulado possível 148/282 = 52.4%.

**Arquivos:** `app/Services/EventFinance.php`, `app/Http/Controllers/EventFinanceController.php`, `resources/js/Pages/EventFinance.tsx`, `tests/Feature/PaymentPlansTest.php`, `tests/Feature/EventFinanceTest.php`. Migrações próprias em `database/migrations/2026_09_14_*` e rotas em `routes/web.php`.

- [ ] **Definir parcelas e condições que fecham ao centavo** — 5 pontos; `rd-b148e6323bee`. C1: gatilhos, total exato e imutabilidade do plano aceito; seção na proposta.
- [ ] **Gerir recebíveis com baixas parciais e cancelamento justificado** — 3 pontos; `rd-3ae801fea93d`. C2: prévia confirmável, saldo e atraso derivados corretamente.
- [ ] **Aprovar pagamentos com origem rastreável e autoridade** — 3 pontos; `rd-35ae981b7b3b`. C3: contas a pagar vinculadas a orçamento/cotação e aprovação necessária.
- [ ] **Conferir previsto e realizado por categoria e justificar desvios** — 3 pontos; `rd-e59afe03e71a`. C4: fechamento bloqueado por desvio relevante sem justificativa.
- [ ] **Calcular margem realizada e identificar resultado provisório** — 3 pontos; `rd-f0fbec9a57e8`. C5: totais derivados dos itens; margem final somente com pós-evento encerrado.

- [ ] Escrever as asserções de aceitação e reproduzir a falha antes de implementar.
- [ ] Implementar serviço, validação/autorização, migração e UI completa, preservando padrões existentes.
- [ ] Rodar testes focados, casos adversos e regressões da área; revisar diff.
- [ ] Commit da entrega, integração à branch monitorada, registro de alegação e associação das novas verificações.
- [ ] Executar Pulse e conferir critérios/artefatos; atualizar esta lista e o relatório com resultados observados.

## 4. Documentos e aceite rastreáveis

Impacto condicionado a comprovação: +19 pontos; acumulado possível 167/282 = 59.2%.

**Arquivos:** `app/Services/DocumentRevisions.php`, `app/Services/DocumentSharing.php`, `app/Services/DocumentComposition.php`, `app/Http/Controllers/DocumentController.php`, `app/Http/Controllers/CaseAttachmentController.php`, `resources/js/Pages/DocumentWorkspace.tsx`, `tests/Feature/DocumentCompositionTest.php`, `tests/Feature/AtomicAcceptanceTest.php`, `tests/Feature/CaseAttachmentsTest.php`. Migrações próprias em `database/migrations/2026_09_14_*` e rotas em `routes/web.php`.

- [ ] **Compor propostas com todas as seções, exclusões e fontes** — 5 pontos; `rd-1178fc8b109a`. G1/G3: conteúdo estruturado e templates por finalidade; versão antiga preservada.
- [ ] **Congelar conteúdo e apresentação da proposta liberada** — 3 pontos; `rd-9868cc450c0a`. G2: JSON, renderização e ativos públicos congelados; prévia e publicado usam o mesmo documento.
- [ ] **Liberar contratos com cláusulas e plano de pagamento válidos** — 3 pontos; `rd-0a71f3bbc0b5`. G4: cadastro, evento, orçamento e pagamento consistentes; assinatura impede alteração sem reabertura.
- [ ] **Gerenciar anexos privados com tipos, quotas e acesso por caso** — 3 pontos; `rd-49e7ed13bc80`. G5: vínculos a módulos, validações e pagamentos; rota autorizada e nenhum caminho privado exposto.
- [ ] **Criar projeto e onboarding no mesmo aceite comercial** — 5 pontos; `rd-d14b396dac6a`. G6: transação atômica, versão/hash exatos, outbox e recibo idempotente.

- [ ] Escrever as asserções de aceitação e reproduzir a falha antes de implementar.
- [ ] Implementar serviço, validação/autorização, migração e UI completa, preservando padrões existentes.
- [ ] Rodar testes focados, casos adversos e regressões da área; revisar diff.
- [ ] Commit da entrega, integração à branch monitorada, registro de alegação e associação das novas verificações.
- [ ] Executar Pulse e conferir critérios/artefatos; atualizar esta lista e o relatório com resultados observados.

## 5. Produção operacional

Impacto condicionado a comprovação: +16 pontos; acumulado possível 183/282 = 64.8%.

**Arquivos:** `app/Services/ProductionOperations.php`, `app/Services/ProductionPlanning.php`, `app/Http/Controllers/ProductionController.php`, `resources/js/Pages/Production.tsx`, `tests/Feature/ProductionPlanningTest.php`. Migrações próprias em `database/migrations/2026_09_14_*` e rotas em `routes/web.php`.

- [ ] **Escalar equipe e responsáveis detectando conflitos** — 5 pontos; `rd-633ed935f8d0`. F1/F2: múltiplos responsáveis, funções, horários, taxas, alimentação, transporte e confirmação.
- [ ] **Conferir montagem e devolução com checklist e fotos** — 3 pontos; `rd-40a7ac37faf5`. F3/F4: áreas, evidências, duração e devolução necessária ao encerramento.
- [ ] **Exibir produção em calendário e linha do tempo com dependências** — 5 pontos; `rd-777ec0f712d7`. F5: modos reais, caminho crítico e calendário por fase.
- [ ] **Gerar ordem de serviço e registrar recebimento do fornecedor** — 3 pontos; `rd-7988d8dcc556`. F6/F7: escopo técnico ligado ao local e ordem derivada de necessidade e cotação.

- [ ] Escrever as asserções de aceitação e reproduzir a falha antes de implementar.
- [ ] Implementar serviço, validação/autorização, migração e UI completa, preservando padrões existentes.
- [ ] Rodar testes focados, casos adversos e regressões da área; revisar diff.
- [ ] Commit da entrega, integração à branch monitorada, registro de alegação e associação das novas verificações.
- [ ] Executar Pulse e conferir critérios/artefatos; atualizar esta lista e o relatório com resultados observados.

## 6. Comparar cotações

Impacto condicionado a comprovação: +3 pontos; acumulado possível 186/282 = 65.9%.

**Arquivos:** `app/Services/SupplierSourcing.php`, `app/Http/Controllers/SupplierWorkspaceController.php`, `resources/js/Pages/Suppliers.tsx`, `tests/Feature/QuoteComparisonTest.php`. Migrações próprias em `database/migrations/2026_09_14_*` e rotas em `routes/web.php`.

- [ ] **Comparar cotações equivalentes e exportar a decisão** — 3 pontos; `rd-42a3f939922e`. L4: normalizar pacote/unidade, alertar validade e revisão, explicitar diferenças de escopo.

- [ ] Escrever as asserções de aceitação e reproduzir a falha antes de implementar.
- [ ] Implementar serviço, validação/autorização, migração e UI completa, preservando padrões existentes.
- [ ] Rodar testes focados, casos adversos e regressões da área; revisar diff.
- [ ] Commit da entrega, integração à branch monitorada, registro de alegação e associação das novas verificações.
- [ ] Executar Pulse e conferir critérios/artefatos; atualizar esta lista e o relatório com resultados observados.

## Benchmark e fechamento

- [ ] Executar cenários de consulta e mutação com fixtures locais em base separada, com 5 aquecimentos e pelo menos 30 amostras; registrar mediana, p95, número de consultas e erros.
- [ ] Comparar as rotas existentes antes/depois com a mesma quantidade de registros; medir fluxos novos e registrar volume dos dados.
- [ ] Rodar PHPUnit, Vitest, tipagem, Pint, build e Playwright nos três tamanhos. Conferir instalação limpa e atualização de schema sem perda.
- [ ] Checar preservação do banco original, ausência de segredos no diff e worktrees/artefatos identificados.
- [ ] Pulse deve concluir lote com provas atuais; confirmar percentual real e dados persistidos após reinício.
- [ ] Documentar pendências restantes para 100%, incluindo aceites humanos e integrações externas.

**Meta planejada:** 186/282 = 65.9%, se todos os resultados acima e os 112 pontos anteriores forem comprovados na mesma revisão. A projeção não antecipa evidência.
