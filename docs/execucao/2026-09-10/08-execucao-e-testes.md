# Pacotes de execução
## Contrato das tarefas
Escrever teste comportamental quando há regra/risco real; implementar; verificar; registrar evidência. Manter testes de estados e não apenas nomes de funções.

| ID | Dependências | Arquivos / domínio | Comportamento e teste de aceite |
|---|---|---|---|
| M1-01 | Nenhuma | docs/execucao, git diff, tests | Dossiê e baseline preservam trabalho existente |
| M1-02 | M1-01 | CommercialStageTransitionService, NextActionService, OperationalQueueService | Criar/atribuir lead, mesma próxima atividade em três telas, retorno explicado |
| M1-03 | M1-02 | Clients, Pipeline, Agenda, TodayQueue | Formulários preservados, filtros/drawer, persistência após login |
| M1-04 | M1-01 | ContextAudioUploadController, ResumableAudioUpload, upload.ts | Upload interrompido retoma por usuário/caso; conflito de digest recusado |
| M1-05 | M1-04 | ContextComposer, CaseContextService, jobs | Aceite parcial, fontes e versão; IA indisponível mantém texto |
| M1-06 | M1-05 | ViabilityLifecycleService, CaseJourneyService, Feasibility | Express/Completo, entrega, aceite, conclusão sem Gestão coerentes |
| M2-01 | M1-06 | SupplierSourcing, SupplierOperations, migrations, Suppliers | Necessidade/consulta/cotação/revisão; seleção não contrata |
| M2-02 | M2-01 | BudgetIntake, BudgetRevisionService, Money, Budget | Total e unitário corretos, importação única, validade e revisão |
| M2-03 | M2-02 | DocumentRevisions, DocumentWorkspace | Finalidades independentes, PDF snapshot e fonte alterada |
| M2-04 | M2-03 | Compartilhamento documental, página externa | Token hash, validade, revogação, privacidade, aceite idempotente |
| M2-05 | M2-04 | DocumentController, registros de entrega/assinatura | Assinatura externa com evidência; usuário sem autoridade recusado |
| M2-06 | M2-02 | AssistantActions, AssistantPanel | Prévia confirmada cria rascunho; não aprova/envia |
| M3-01 | M2-05 | ProductionController, Production, serviço operacional | Escopo aprovado vira prévia idempotente; confirmação cria tarefas, com dependências, edição e bloqueios |
| M3-02 | M3-01 | TechnicalValidation, ProductionOperations, auditoria | Visita técnica, medidas e evidência; alteração invalida confirmação e tarefa técnica só conclui após reconfirmação |
| M3-03 | M3-02 | PostEventController, PostEvent | Ocorrências, previsto × realizado, avaliações, aprendizados e checklist/pendências no encerramento; reabertura auditada |
| M3-04 | M3-03 | History, Help, Team, indicadores | Auditoria humana e métricas reais sem feedback misturado |
| M3-05 | Todas | tests/e2e, scripts/deploy | Três breakpoints, recuperação, SQLite/MariaDB, pacote e manual |

## Estado desta execução

| Tarefa | Estado verificável | Evidência |
|---|---|---|
| M1-04 | Em verificação | `ContextAudioUploadTest`, `PrepareContextAudioJobTest`, upload retomável e preparação local |
| M1-05 | Funcional incompleto | fluxo de revisão consolidada existente; chamada OpenAI real ainda não validada |
| M1-06 | Funcional incompleto | Viabilidade e entregáveis existentes; jornada completa ainda pendente |
| M2-01 | Em verificação | `SupplierNeedTest`, necessidade vinculada ao orçamento e seleção sem contratação |
| M2-02 | Em verificação | `QuoteSelectionTest`, importação idempotente e orçamento por revisão |
| M2-03 | Em verificação | PDF por snapshot e editor de versões em `DocumentRevisionTest` |
| M2-04 | Em verificação | `DocumentShareLinkTest`, token hash, validade, revogação e decisão externa |
| M2-05 | Em verificação | assinatura externa de contrato com evidência e bloqueio por status |
| M3-01 | Em verificação | `ProductionOperations` prepara prévia do orçamento aprovado, confirma sem duplicação, registra fase, responsável, dependência, bloqueio e edição concorrente |
| M3-02 | Em verificação | `technical_validations` persistente, reconfirmação auditada e bloqueio de tarefas técnicas até status confirmado |
| M3-03 | Em verificação | Pós-evento registra ocorrências/extras, previsto × realizado, avaliações, pendências formais e encerramento/reabertura idempotentes |
| M3-04 | Em verificação | Histórico humano com métricas derivadas de casos, revisões, tarefas e atrasos; distribuição por pessoa e auditorias relacionadas sem misturar feedback |
| M3-05 | Em verificação | testes de navegador e release Hostinger passaram localmente; MariaDB, integrações externas e publicação continuam gates externos |

## Jornadas finais
- [ ] Lead → Viabilidade entregue → encerramento sem Gestão.
- [ ] Lead → orçamento → proposta aceita → Gestão → produção → pós.
- [ ] Mudança técnica → reconfirmação.
- [ ] Escopo mudou → orçamento/documento em revisão.
- [ ] IA indisponível → manual.
- [ ] Envio falhou → arquivo e tentativa preservados.
- [ ] Upload retomado sem duplicação.
- [ ] Edição concorrente → recuperação.
- [ ] Aprovação sem autoridade bloqueada.
- [ ] Refresh/login preservam registros.
- [ ] Desktop 1440×900, tablet 1024×768, celular 390×844.

## Comandos finais
composer quality
npm run quality
npm run test:e2e
scripts/ci/test-mariadb.sh
scripts/deploy/build-hostinger-release.sh
scripts/deploy/verify-hostinger-release.sh

Executar MariaDB somente em banco dedicado de testes. Registrar ausência de servidor/acesso como não verificado, nunca como aprovação.
