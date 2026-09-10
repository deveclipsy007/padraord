# Padrão RD OS — Plano de Conclusão até 10/10

Data: 10/09/2026. Documento de trabalho da equipe. Complementa `docs/execucao/2026-09-10/` (dossiê de execução) e `docs/visao-produto-auditoria-roadmap-2026-09-01.md` (visão de produto).

## Contexto

O sistema (Laravel 13 + PHP 8.4, React 19 + TypeScript + Inertia 3, Vite 8, Tailwind 4) foi auditado com execução real das suítes: **PHPUnit 169/169** (1.219 asserções), **Vitest 12/12**, **TypeScript 0 erros**, **Playwright 27/27** em três breakpoints. Inventário: 127 rotas, 52 tabelas, 29 migrations, ~393 KB de PHP e ~314 KB de TS/TSX.

Durante a própria auditoria entrou o assistente de conversa (`app/AI/AssistantChat.php`, migration `assistant_chat_turns`), levando a suíte a **177/177** (1.257 asserções).

## Executado até agora (branch `ciclo-04-consolidacao`)

| Commit | Entrega | Item |
|---|---|---|
| `f51fa01` | Ciclo 04 inteiro sob versionamento, verificado | I1 |
| `fe62287` | Dossiê de execução e este plano | — |
| `712be04` | Autorização da transcrição migrada de `.env` para a administração; quatro motores visíveis; `SESSION_DRIVER` do e2e corrigido | B1 (parte), H2 (parte) |
| `4a3220d` | Recuperação de senha completa, com revogação de sessões e limites | **E1** |
| `0c2def0` | `padraord:doctor` e correção do falso verde de e-mail | I7 (parte) |

**Estado das suítes: 196 testes PHP (1.343 asserções), 12 Vitest, TypeScript sem erros, 48 Playwright em três breakpoints, Pint limpo.**

Descoberta que mudou a prioridade: a transcrição — requisito central — estava **inalcançável pela interface**. `ContextAudioReviewController`, `BriefingAudioController`, `ContextAudioUploadController` e `TranscribeBriefing` exigiam `AI_AUDIO_VALIDATED` e `AI_AUDIO_PRICE_MICROS_PER_MINUTE`, que só existiam no `.env` e vinham `false`/`0`. Corrigido.

A engenharia de base é sólida — transações com lock, fingerprint de caso, versionamento por snapshot, idempotência, auditoria, salvaguardas de IA acima da média do mercado. **Nota de auditoria: 6,5/10, ≈35% pendente.**

O que segura a nota não é qualidade de código. São três coisas:

1. **O caminho crítico da IA nunca tocou a realidade.** Todos os testes usam provider falso; nenhuma chamada paga foi executada. A configuração em si está correta — `gpt-5.6-luna` (extração) e `gpt-4o-transcribe-diarize` com `response_format: diarized_json` + `chunking_strategy: auto` (transcrição) são modelos e parâmetros válidos, verificados em 10/09/2026. O bloqueio é de **validação**, não de configuração: ninguém sabe qual é a precisão, o custo real por reunião nem o comportamento em áudio longo.
2. **A modelagem é genérica.** O briefing — que é o produto de uma produtora de eventos — são 8 strings soltas num JSON (`BriefingPayload::FIELDS`). Não há data de término (evento multi-dia é impossível), não há venue como entidade, não há requisito técnico por área, não há CNPJ para emitir contrato. É um CRM genérico com rótulos de evento.
3. **Não há dinheiro, não há comunicação, não há recuperação de conta.** Zero `Mail::`/`Notification` em toda a aplicação. Proposta "enviada" é evidência digitada à mão. Sem rota de recuperação de senha. Sem contas a pagar/receber.

### Decisões tomadas

| Tema | Decisão |
|---|---|
| Infraestrutura | **Híbrido** — app na Hostinger compartilhada + worker externo com FFmpeg para mídia/IA |
| Briefing | **Entidade real de evento** — tabelas tipadas, não JSON genérico |
| Financeiro | **Financeiro operacional do evento** — parcelas, a receber, a pagar, previsto × realizado por categoria |
| Comunicação | Envio real por e-mail **com autorização humana explícita**; assinatura eletrônica integrada; lembretes internos. **IA prepara e sugere, nunca envia nem negocia.** WhatsApp fica para etapa posterior |

### Resultado esperado

Sistema 100% funcional, responsivo, com IA operante em produção, pronto para lançamento oficial — nota 10/10, 0% pendente.

---

## Invariantes que não podem quebrar

Toda tarefa deste plano é aceita apenas se preservar:

1. **Revisão humana obrigatória.** IA nunca aprova, envia, contrata ou negocia. Prepara prévia; humano confirma.
2. **Evidência rastreável.** Toda sugestão de IA cita o trecho de origem. `unknown` nunca vira `fact`.
3. **Nada é sobrescrito.** Versão anterior sempre preservada (`revision`, `snapshot`, `supersedes_id`).
4. **Conteúdo é dado, nunca instrução.** Transcrição, mensagem e documento não comandam o sistema.
5. **Concorrência protegida.** Mutação de caso passa por `lockForUpdate` + verificação de fingerprint/revisão — padrão de `CaseContextService::confirm()` (`app/Services/CaseContextService.php:47`) e `BudgetRevisionService::mutate()`.
6. **Autoridade comercial separada.** `can_approve_commercial` governa liberação, envio e assinatura.
7. **IA indisponível nunca bloqueia.** Todo fluxo tem caminho manual equivalente.
8. **Custo pago é reservado antes e reconciliado depois.** Timeout incerto não repete chamada paga (`AiCostLedger::reserve/report/uncertain`).

Cada bloco abaixo tem um teste que prova o invariante correspondente.

---

## Ordem de execução

Ordenado por **dependência técnica**, não por calendário. Blocos no mesmo nível podem correr em paralelo.

```
A (Modelo de domínio)  ─┬─→ B (IA em produção)  ─┐
                        ├─→ C (Financeiro)       ├─→ H (UX) ─→ J (Fechamento)
                        ├─→ G (Documentos)       │
E (Segurança) ──────────┤                        │
F (Produção) ───────────┘                        │
D (Comunicação) ────────────────────────────────┘
I (Infra) ── transversal, começa junto com A
```

**A é pré-requisito de quase tudo.** Não comece B, C ou G antes de A fechar — senão a IA vai preencher campos que serão migrados, e o financeiro vai pendurar em colunas que vão sumir.

---

# BLOCO A — Modelo de domínio de eventos

**Objetivo:** parar de ser um CRM genérico. O sistema passa a conhecer evento, local, requisito técnico e programação como entidades reais.

**Arquivos-chave:** `database/migrations/`, `app/Models/Opportunity.php`, `app/AI/BriefingContext.php`, `app/AI/BriefingPayload.php`, `app/Services/CaseContextService.php`

---

## A1 — `clients`: empresa de verdade

Hoje: `name`, `industry`, `notes`, `archived_*`. Não dá para emitir contrato nem nota com isso.

**Migration `add_client_business_identity`:**

| Campo | Tipo | Observação |
|---|---|---|
| `legal_name` | varchar | Razão social |
| `tax_id` | varchar, índice único | CNPJ ou CPF, guardado só com dígitos |
| `tax_id_type` | enum `cnpj\|cpf\|estrangeiro` | |
| `state_registration` | varchar nullable | IE, aceita "ISENTO" |
| `municipal_registration` | varchar nullable | |
| `billing_email` | varchar nullable | Separado do contato comercial |
| `billing_address` | json | `{cep, logradouro, numero, complemento, bairro, cidade, uf}` |
| `default_payment_terms_days` | int, default 30 | |
| `segment` | enum | `corporativo\|social\|institucional\|cultural\|esportivo\|religioso\|governo\|terceiro_setor` |
| `tier` | enum | `prospect\|ativo\|recorrente\|inativo` |
| `website`, `instagram` | varchar nullable | |

- [ ] Validação de CNPJ/CPF (dígito verificador) em `app/Rules/TaxId.php` — nova regra, sem pacote externo
- [ ] Consulta de duplicidade por `tax_id` reusando o padrão de `SupplierController::duplicates`
- [ ] UI: `ClientProfile.tsx` ganha aba "Dados cadastrais" com máscara e validação inline
- [ ] **Teste:** cliente sem `tax_id` não pode ter contrato liberado; `tax_id` duplicado é recusado apontando o registro existente

## A2 — `contacts`: papel real na decisão

- [ ] `is_primary` (bool), `is_decision_maker` (bool), `department` (varchar), `whatsapp` (varchar), `preferred_channel` (enum `email|whatsapp|telefone`)
- [ ] Regra: no máximo um `is_primary` por cliente (índice parcial / validação no serviço)
- [ ] `opportunity_qualifications.decision_maker_contact_id` passa a exigir contato com `is_decision_maker = true`
- [ ] **Teste:** qualificar caso apontando contato que não é decisor é recusado com mensagem explicativa

## A3 — `venues`: local como entidade reutilizável

Produtora reutiliza local. Hoje o local é string em `opportunities.location` e `technical_validations.reference`.

**Nova tabela `venues`:**

| Grupo | Campos |
|---|---|
| Identidade | `name`, `venue_type` (enum `hotel\|centro_convencoes\|casa_eventos\|teatro\|galpao\|area_externa\|espaco_cliente\|clube\|restaurante\|outro`), `tax_id` nullable |
| Endereço | `address` (json), `city`, `state`, `latitude`, `longitude` nullable |
| Capacidade | `capacity_seated`, `capacity_standing`, `capacity_cocktail`, `capacity_auditorium` |
| Físico | `floor_area_m2`, `ceiling_height_m`, `column_notes`, `floor_load_kg_m2` |
| **Acesso de carga** | `door_width_m`, `door_height_m`, `has_loading_dock`, `has_freight_elevator`, `elevator_capacity_kg`, `load_in_notes` |
| Energia | `power_available_kva`, `power_phases`, `has_generator_area`, `power_notes` |
| Operação | `noise_curfew_time`, `load_in_window`, `load_out_window`, `parking_spots`, `has_kitchen`, `catering_policy` (enum `livre\|exclusivo\|lista_aprovada`) |
| Governança | `restrictions` (text), `rules_document_path`, `contact_name`, `contact_phone`, `contact_email`, `notes`, `status` (enum `ativo\|inativo`), `revision` |

- [ ] `opportunities.location` (string) migra para `venue_id` + `location_note`; string antiga vira `venues` quando reconhecível, senão fica em `location_note`
- [ ] `technical_validations` ganha `venue_id` FK
- [ ] Página `Venues.tsx` + `VenueProfile.tsx` com histórico de eventos realizados no local
- [ ] **Teste:** validação técnica em venue com `door_width_m` menor que a maior medida declarada gera alerta bloqueante, não silencioso

## A4 — `event_briefs`: o coração do sistema

**Substitui `opportunities.briefing_data` (JSON) e `BriefingPayload::FIELDS` (8 strings).**

**Nova tabela `event_briefs`:**

**Identidade do evento**
- `opportunity_id` FK, `revision` int, `status` enum `draft|awaiting_review|approved|superseded`, `schema_version`
- `event_name`
- `event_type` enum: `congresso|convencao|seminario|workshop|lancamento|convencao_vendas|confraternizacao|premiacao|feira|ativacao_marca|show|festival|casamento|formatura|aniversario|coquetel|jantar|coletiva_imprensa|treinamento|outro`
- `event_format` enum: `presencial|hibrido|online`
- `edition` varchar nullable (ex.: "3ª edição")
- `is_recurring` bool, `previous_opportunity_id` FK nullable — **evento que se repete puxa o histórico do anterior**

**Tempo** — resolve a impossibilidade atual de evento multi-dia
- `starts_at`, `ends_at` (datetime, não date)
- `setup_starts_at`, `teardown_ends_at` (datetime) — montagem e desmontagem são operação, não detalhe
- `timezone` default `America/Sao_Paulo`
- `date_confidence` enum `confirmada|provavel|janela|indefinida`
- `alternative_dates` json

**Local**
- `venue_id` FK nullable, `venue_status` enum `definido|em_prospeccao|cliente_define|indefinido`
- `city`, `state` (usados quando venue ainda não existe)
- `venue_requirements` text (o que o local precisa ter, quando ainda vai ser buscado)

**Público**
- `audience_expected_min`, `audience_expected_max`, `audience_confidence` enum
- `audience_profile` text, `audience_segments` json (`["clientes","imprensa","colaboradores","autoridades"]`)
- `has_vip` bool, `vip_notes` text
- `accessibility_requirements` json (`["rampa","libras","audiodescricao","banheiro_pcd"]`)

**Objetivo e mensagem**
- `objective` text
- `success_criteria` json — itens mensuráveis, não texto solto
- `key_message` text, `tone` enum `sobrio|celebrativo|tecnico|premium|jovem|institucional|intimista`
- `brand_notes` text, `brand_assets` json

**Investimento**
- `budget_declared_cents`, `budget_range_min_cents`, `budget_range_max_cents`
- `budget_confidence` enum `confirmado|estimado|sem_teto|nao_informado`
- `budget_includes_taxes` bool, `payment_expectation` text

**Restrições, riscos e prazos**
- `constraints` json — itens tipados `{type, description, severity}`, não string
- `risks` json — `{description, likelihood, impact, mitigation}`
- `deadlines` json — `{label, date, is_hard}`

**Referências**
- `references` json — `{kind: imagem|video|link|documento, url_or_path, note}`

**Governança**
- `completeness_score` int (0-100, calculado), `missing_critical` json
- `approved_by`, `approved_at`
- `source_context_entry_ids` json

**Checklist:**
- [ ] Migration `create_event_briefs` com todos os campos e índices (`opportunity_id`, `revision`)
- [ ] Model `EventBrief` com casts e enums PHP nativos em `app/Enums/`
- [ ] Migration de dados: `opportunities.briefing_data` → `event_briefs` (mapeando os 8 campos antigos), preservando `briefing_revision`
- [ ] `opportunities.briefing_data` marcada como legado read-only por um ciclo, com teste que garante que nada novo escreve nela
- [ ] `EventBriefService` com `mutate()` seguindo o padrão de `BudgetRevisionService::mutate()` — lock, revisão esperada, snapshot
- [ ] `completeness_score` calculado por serviço, com peso por criticidade (data, local, público, objetivo, investimento pesam mais)
- [ ] **Teste:** aprovar brief com `missing_critical` não vazio é recusado; brief aprovado rejeita alteração silenciosa; revisão concorrente recupera com mensagem

## A5 — `brief_requirements`: escopo técnico por área

O diferencial de produtora. Hoje escopo é uma string.

**Nova tabela `brief_requirements`:**
- `event_brief_id` FK, `sequence`
- `area` enum: `palco|som|iluminacao|video_led|projecao|cenografia|mobiliario|climatizacao|energia|estrutura|tenda|piso|catering|bar|staff|seguranca|brigada|limpeza|transporte|hospedagem|brindes|sinalizacao|credenciamento|fotografia|filmagem|live_streaming|traducao_simultanea|entretenimento|decoracao|flores|licencas|outro`
- `requirement` text, `quantity` decimal, `unit` varchar
- `priority` enum `obrigatorio|desejavel|opcional`
- `status` enum `identificado|confirmado|descartado|substituido`
- `source` enum `cliente|reuniao_ia|equipe|historico|venue`
- `classification` enum `fact|hypothesis|conflict|unknown` — mesma taxonomia da IA
- `evidence_segment_ids` json — **aponta para o trecho da gravação**
- `case_context_entry_id` FK nullable
- `confirmed_by`, `confirmed_at`, `revision`

- [ ] Migration + model + enum `RequirementArea` com `label()` em português
- [ ] `brief_requirements` alimenta `supplier_needs` (hoje criada à mão) — ação "gerar necessidades a partir dos requisitos", com prévia confirmável
- [ ] `brief_requirements` alimenta a prévia de escopo de produção, complementando `ProductionOperations::prepareFromApprovedScope()`
- [ ] UI: agrupamento por área com badge de classificação e link "ouvir trecho"
- [ ] **Teste:** requisito com `classification = hypothesis` não vira `supplier_need` sem confirmação humana explícita

## A6 — `brief_program_blocks`: programação do evento

- [ ] Nova tabela: `event_brief_id`, `sequence`, `starts_at`, `ends_at`, `title`, `description`, `location_note`, `responsible_area`, `attendees_estimate`, `source`, `evidence_segment_ids`
- [ ] Validação: bloco fora da janela `starts_at`/`ends_at` do brief é recusado; sobreposição gera alerta, não bloqueio
- [ ] UI: timeline vertical editável, com arrastar para reordenar no desktop e ordenação por botão no mobile
- [ ] **Teste:** programação sobreposta é sinalizada; bloco fora da data do evento é recusado

## A7 — Vínculo permanente brief ↔ gravação

Hoje o purge apaga o áudio após `retention_days` (padrão 30) e preserva só a transcrição — `routes/console.php:68`. O requisito de vincular brief à gravação original vale 30 dias.

- [ ] `case_context_entries` ganha: `title` (nome da reunião), `meeting_date`, `meeting_kind` (enum `kickoff|alinhamento|visita_tecnica|negociacao|apresentacao|pos_evento`), `retain_forever` bool, `participants` json
- [ ] **Nova tabela `brief_field_sources`**: `event_brief_id`, `field_path` (ex.: `audience_expected_max`, `requirements.12`), `case_context_entry_id`, `segment_ids` json, `extracted_at`, `confirmed_by`, `confirmed_at`
  → cada campo do brief aponta para o trecho exato da reunião que o originou
- [ ] `context:purge-expired-audio` passa a **nunca** apagar áudio de entry com `retain_forever = true` ou vinculado a brief aprovado; registra o motivo da preservação em `AuditLog`
- [ ] UI: no brief, cada campo preenchido por IA mostra ícone de origem → abre player no timestamp exato
- [ ] **Teste:** purge com brief aprovado vinculado não remove o áudio e registra auditoria; campo confirmado mantém `brief_field_sources` mesmo após nova revisão do brief

## A8 — Resolver duplicidades e dívidas do schema

- [ ] **`opportunities.stage` × `commercial_stage`**: dois enums paralelos (13 e 8 estados). Consolidar em `stage` usando `OpportunityStage`; `CommercialStage` vira subconjunto derivado ou é removido. Migration de dados + remoção da coluna redundante
- [ ] **`opportunities.client_name`, `contact_name`, `contact_email`**: denormalização em conflito com `client_id`/`contact_id`. Passar a derivar por relação; manter as colunas apenas como snapshot histórico imutável, com teste que garante que ninguém mais escreve nelas
- [ ] **`opportunities.location`** → `venue_id` + `location_note` (A3)
- [ ] **`opportunities.briefing_data`** → `event_briefs` (A4)
- [ ] **`attachments`**: tabela órfã, nenhum código a usa. Ativar no bloco G4 ou remover — não deixar meio-termo
- [ ] **`briefing_audio`** (fluxo legado) × `context_audio_assets` (fluxo atual): decidir migração ou aposentadoria explícita, com rota de leitura preservada para histórico
- [ ] **Teste:** `DatabasePortabilityTest` estendido cobre as novas tabelas em SQLite e MariaDB

---

# BLOCO B — IA em produção real

**Objetivo:** sair de "arquitetura pronta, nunca ligada" para "operando com custo, precisão e evidência medidos".

**Arquivos-chave:** `app/AI/`, `app/Jobs/`, `app/Contracts/MediaPreparationProvider.php`, `config/ai.php`

---

## B1 — Catálogo de modelos e verificação real

Verificado em 10/09/2026: os modelos e parâmetros configurados estão **corretos**. `gpt-5.6-luna` existe, suporta structured outputs por JSON Schema, tem 1,05M de contexto e custa US$ 0,20/M entrada e US$ 1,20/M saída. `gpt-4o-transcribe-diarize` exige exatamente `response_format: diarized_json` + `chunking_strategy: auto` — que é o que `OpenAiAudioTranscriber::transcribe()` já envia. Não há correção de configuração a fazer.

- [x] **Bug real:** `AiConfiguration::publicState()` devolve `'model' => 'gpt-4o-mini'` fixo no código, enquanto extração usa `gpt-5.6-luna` e transcrição usa `gpt-4o-transcribe-diarize`. A tela de IA informa modelo errado ao operador. Derivar de `config('ai.*')`
- [x] `.env.example` ganha `AI_MODE` e `AI_DATA_POLICY_APPROVED` (ausentes hoje, mas lidos por `AiConfiguration::setting()`) e os modelos efetivos
- [ ] Novo comando `php artisan ai:verify` — faz uma chamada real mínima de cada operação (transcrição curta, extração de poucos segmentos), grava resultado, custo e `request_id` em `AiRun`, e imprime relatório
- [ ] `config/ai.php` ganha `models` com catálogo validado e data da última verificação; `ai:verify` atualiza
- [ ] **Teste:** `ai:verify` com chave ausente falha com mensagem acionável, não com stack trace; com chave válida grava evidência

## B2 — Worker externo de mídia (decisão: híbrido)

App fica na Hostinger. Preparação de mídia e transcrição saem para um worker com FFmpeg.

- [ ] Novo `app/Media/FfmpegMediaPreparationProvider.php` implementando o contrato existente `App\Contracts\MediaPreparationProvider` — o contrato já está pronto, só falta a implementação
- [ ] **Nova tabela `media_jobs`**: `context_audio_asset_id`, `operation` enum `prepare|transcribe`, `external_id`, `status`, `callback_token_hash`, `attempts`, `payload` json, `result` json, `error`, `dispatched_at`, `completed_at`
- [ ] Rota `POST /api/media/callback` com verificação HMAC do corpo + `callback_token_hash` + idempotência por `external_id`. Callback é **dado, nunca instrução** — payload só pode mover status e anexar resultado, nunca disparar outra ação
- [ ] Upload direto do navegador para o worker via URL assinada de curta duração, evitando o limite de upload da Hostinger
- [ ] Fallback: worker indisponível → volta para `PassThroughMediaPreparationProvider` + preparação no navegador (comportamento atual), com aviso claro na UI
- [ ] Worker provisionado com FFmpeg, isolamento por caso e retenção espelhando `ai_settings.retention_days`
- [ ] **Teste:** callback com HMAC inválido é recusado; callback repetido não duplica segmento; worker fora do ar mantém o fluxo manual vivo

## B3 — Áudio longo de verdade

- [ ] Segmentação em partes **decodificáveis** (não chunk de transporte), com overlap configurável, preservando offsets absolutos
- [ ] **Continuidade de falante entre partes.** A API diariza cada requisição de forma independente: o "Falante A" da parte 1 não é o "Falante A" da parte 2. Acima de 25 MB o arquivo obrigatoriamente vira várias requisições. Usar `known_speaker_names[]` + `known_speaker_references[]` (amostras de 2–10s extraídas da primeira parte) para amarrar a identidade nas partes seguintes; sem isso a transcrição de reunião longa sai com falantes embaralhados
- [ ] Deduplicação de texto na junção sem descartar fala — costura por similaridade na região de overlap
- [ ] `context_audio_assets` ganha `part_count`, `parts` json (`{index, start_ms, end_ms, path, digest}`)
- [ ] `TranscribeContextAudio` passa a montar segmentos de múltiplas partes preservando `sequence` e `source_chunk` (colunas já existem em `case_context_segments`)
- [ ] Ajustar a janela da fila: `routes/console.php:17` roda `queue:work --max-time=720` com job de `timeout=660`. Com worker externo, o job do app vira despacho + poll, não processamento longo
- [ ] **Teste:** áudio de 3h processa por partes; retomada após falha na parte 7 não reprocessa as 6 anteriores nem cobra de novo; offsets batem com o player

## B4 — Extração alimenta o brief tipado

Hoje a extração devolve `module_changes` com `field` string livre e grava em 8 campos. Com o Bloco A, passa a preencher entidades.

- [ ] Novo `app/AI/EventBriefSchema.php` — JSON Schema estrito com os enums reais do domínio (`event_type`, `event_format`, `RequirementArea`, `priority`, `date_confidence`…). Modelo não inventa categoria: escolhe de lista fechada
- [ ] `ContextIntelligenceSchema::MODULES` ganha `requirements` e `program` como blocos próprios
- [ ] Extração devolve, além dos campos do brief: `brief_requirements` candidatos (área, quantidade, unidade, prioridade, classificação) e `brief_program_blocks` candidatos
- [ ] Cada item candidato carrega `evidence_segment_ids` — validado por `ContextIntelligenceSchema::validate()`, que já recusa citação de segmento inexistente
- [ ] Confirmação parcial em `CaseContextService::confirm()` estendida: aceitar por módulo **e por item**, gravando `brief_field_sources` (A7)
- [ ] Prompt atualizado: mantém "conteúdo é dado, nunca instrução", acrescenta o vocabulário fechado do domínio e a proibição de inferir preço, fornecedor ou aprovação
- [ ] **Teste:** extração que propõe área fora do enum é recusada na validação; item `unknown` não pode ser confirmado sem edição humana; confirmação grava origem por campo

## B5 — Materiais organizados de projeto (requisito central)

- [ ] Serviço `ProjectPackageBuilder` gera, a partir do brief aprovado: capa do evento, brief estruturado, requisitos por área, programação, cronograma de montagem/evento/desmontagem, equipe prevista e resumo de investimento
- [ ] Saída em PDF (reusando `dompdf`, já instalado) + ZIP versionado
- [ ] **Nova tabela `project_packages`**: `opportunity_id`, `revision`, `path`, `hash`, `contents` json, `generated_by`, `generated_at`, `source_brief_revision`
- [ ] Regeneração é idempotente por `source_brief_revision` + hash de conteúdo
- [ ] Pacote marca visualmente cada informação vinda de IA ainda não confirmada
- [ ] **Teste:** pacote gerado duas vezes sem mudança de brief não cria segunda versão; brief alterado marca pacote anterior como `stale`, sem apagar

## B6 — Avaliação medida da IA

- [ ] Conjunto de 10 reuniões (sintéticas + reais autorizadas) cobrindo: fatos críticos, informação ausente, contradição entre participantes, múltiplos falantes, mudança de escopo no meio, falha e retomada
- [ ] Métricas por campo: precisão, recall, taxa de correção humana, custo real por reunião, tempo até brief revisável
- [ ] Comando `php artisan ai:evaluate` gera relatório em `docs/operations/ai-briefing-evaluation.md` (arquivo já existe, hoje sem dados reais)
- [ ] Critério de aceite: nenhuma sugestão aplicada sem origem rastreável; nenhum `unknown` promovido a `fact`; custo por reunião dentro do limite configurado
- [ ] **Teste:** a suíte de avaliação roda com provider gravado (fixtures de resposta real), sem custo em CI

## B7 — Degradação e limites

- [ ] Estados de erro com texto acionável em português: chave ausente, política pendente, tarifa não configurada, limite mensal atingido, worker fora do ar, áudio corrompido, áudio longo demais
- [ ] Limite mensal atingido → fluxo manual completo, com aviso e link para configuração
- [ ] `MeteredAiProvider` e `AiCostLedger` expostos numa tela de consumo: gasto do mês, por operação, por caso
- [ ] **Teste:** com IA em `manual`, todo o fluxo de brief funciona pelo teclado, ponta a ponta

---

# BLOCO C — Financeiro operacional do evento

**Objetivo:** o dinheiro do evento dentro do sistema, ligado ao orçamento aprovado. Não é ERP contábil.

**Arquivos-chave:** `app/Services/Money.php`, `app/Services/BudgetRevisionService.php`, `app/Models/Budget.php`, `app/Models/PostEventReport.php`

---

## C1 — Condições de pagamento na proposta

- [ ] **Nova tabela `payment_plans`**: `opportunity_id`, `document_id` nullable, `revision`, `total_cents`, `currency` default BRL, `installments_count`, `notes`, `status` enum `draft|proposed|accepted|superseded`
- [ ] **Nova tabela `payment_plan_installments`**: `payment_plan_id`, `sequence`, `amount_cents`, `percentage_bps`, `trigger` enum `assinatura|dias_antes_evento|dias_apos_evento|entrega|data_fixa|marco`, `trigger_offset_days`, `due_date` (calculada ou fixa), `description`
- [ ] Soma das parcelas obrigatoriamente igual ao total — validação com `Money::ratio` para não perder centavo no rateio percentual
- [ ] Plano vira seção da proposta (Bloco G1), não texto solto
- [ ] **Teste:** parcelas em percentual somando 100% batem o total ao centavo; plano aceito rejeita alteração; alteração exige nova revisão

## C2 — Contas a receber

- [ ] **Nova tabela `receivables`**: `opportunity_id`, `payment_plan_installment_id` nullable, `due_date`, `amount_cents`, `status` enum `previsto|faturado|recebido|parcial|atrasado|cancelado`, `received_at`, `received_amount_cents`, `method` enum `pix|transferencia|boleto|cartao|dinheiro|outro`, `evidence`, `invoice_number`, `notes`
- [ ] Geração a partir do plano aceito, com prévia confirmável (nunca cria sozinho)
- [ ] Baixa parcial suportada; status `atrasado` derivado por data, não gravado manualmente
- [ ] **Teste:** baixa maior que o saldo é recusada; baixa parcial mantém saldo correto; cancelar recebível com baixa exige justificativa e auditoria

## C3 — Contas a pagar por fornecedor

- [ ] **Nova tabela `payables`**: `opportunity_id`, `supplier_id`, `supplier_quote_id` nullable, `budget_item_id` nullable, `description`, `due_date`, `amount_cents`, `status` enum `previsto|aprovado|pago|parcial|atrasado|cancelado`, `paid_at`, `paid_amount_cents`, `method`, `evidence`, `invoice_number`, `invoice_path`, `approved_by`, `approved_at`
- [ ] Origem rastreável: pagável nasce do item de orçamento aprovado ou da cotação selecionada (`supplier_quote_selections` já existe)
- [ ] Aprovação de pagamento exige autoridade — reusar `can_approve_commercial` ou nova ability `approve-payment`
- [ ] **Teste:** pagável sem origem em orçamento aprovado ou cotação selecionada é recusado; pagar sem aprovação é bloqueado

## C4 — Previsto × realizado por categoria

Hoje `post_event_reports` tem `planned_total_cents` e `actual_total_cents` — dois números para um evento inteiro.

- [ ] **Nova tabela `event_cost_results`**: `opportunity_id`, `category` (mesmo vocabulário de `budget_items.category`), `planned_cents`, `actual_cents`, `variance_cents` (derivado), `variance_reason`, `recorded_by`
- [ ] Populada a partir de `budget_items` (previsto) e `payables` pagos (realizado), com ajuste manual justificado
- [ ] `PostEventReport` passa a exibir desvio por categoria, não só total
- [ ] **Teste:** desvio acima de limite configurável exige `variance_reason` para fechar o pós-evento

## C5 — Margem real do evento

- [ ] Serviço `EventProfitability` reusando `Money::breakdown()` (`app/Services/Money.php`) — não reimplementar cálculo
- [ ] Expõe: receita contratada, custo previsto, custo realizado, margem prevista, margem realizada, taxa de gestão e administração efetivas
- [ ] Bloqueio explícito: margem realizada só é final com pós-evento encerrado
- [ ] **Teste:** margem calculada bate com a soma de `Money::breakdown` de cada item; evento sem pós-evento fechado marca margem como provisória

## C6 — Visão financeira

- [ ] Painel por caso: plano de pagamento, a receber, a pagar, previsto × realizado, margem
- [ ] Painel consolidado: fluxo previsto por mês, atrasados, eventos com margem abaixo do alvo
- [ ] Reusar `OperationalMetrics` (`app/Services/OperationalMetrics.php`) como padrão de cálculo derivado, não gravado
- [ ] **Teste:** indicadores derivam de dados reais; caso arquivado não entra no consolidado

---

# BLOCO D — Comunicação e assinatura, com autorização humana

**Objetivo:** o sistema executa e registra; a equipe autoriza. IA prepara, nunca envia.

**Arquivos-chave:** `app/Services/DocumentRevisions.php`, `app/Http/Controllers/DocumentController.php`, `config/mail.php`

---

## D1 — Envio real de e-mail

Hoje: zero `Mail::` ou `Notification` na aplicação. `MAIL_MAILER=log`. Envio é evidência digitada em `DocumentRevisions::sent()`.

- [ ] Configurar transporte real (SMTP autenticado ou Resend/Postmark) com credenciais fora do repositório
- [ ] **Nova tabela `mail_logs`**: `opportunity_id`, `document_id` nullable, `to` json, `cc` json, `subject`, `body_hash`, `provider_message_id`, `status` enum `queued|sent|delivered|bounced|failed`, `authorized_by`, `authorized_at`, `sent_at`, `delivered_at`, `opened_at`, `error`, `attempt`
- [ ] Fluxo obrigatório: rascunho → revisão → **tela de autorização de envio** mostrando destinatário, assunto, anexo e link → confirmação por quem tem autoridade → sistema envia → registra
- [ ] `DocumentRevisions::sent()` passa a aceitar registro automático do envio real, mantendo o caminho de evidência manual como fallback quando o e-mail falha
- [ ] Falha de envio preserva o documento e a tentativa — nunca perde estado (jornada já prevista no checklist do dossiê)
- [ ] Templates em `resources/views/mail/` com identidade RD, versão texto e HTML
- [ ] **Teste:** envio sem autorização explícita é recusado; usuário sem `can_approve_commercial` não autoriza; falha de provedor mantém documento em `reviewed` e registra tentativa; reenvio não duplica `mail_log`

## D2 — Assinatura eletrônica integrada

- [ ] Novo contrato `app/Contracts/SignatureProvider.php` (espelhando o padrão de `AudioTranscriber`/`MediaPreparationProvider`)
- [ ] Implementação para provedor brasileiro (Clicksign, D4Sign, ZapSign ou Autentique) + `NullSignatureProvider` para desenvolvimento
- [ ] **Nova tabela `signature_requests`**: `document_id`, `provider`, `external_id`, `status` enum `draft|sent|viewed|signed|refused|expired|cancelled`, `signers` json, `sent_at`, `signed_at`, `signed_document_path`, `signed_document_hash`, `webhook_events` json
- [ ] Webhook com verificação de assinatura do provedor + idempotência; atualiza `documents.signed_at` e cria `external_signature_records` (tabela já existe)
- [ ] Registro manual de assinatura externa **permanece** válido — `DocumentRevisions::externalSignature()` continua funcionando
- [ ] Envio para assinatura exige autoridade comercial, igual ao envio de proposta
- [ ] **Teste:** webhook forjado é recusado; webhook repetido não duplica registro; contrato assinado rejeita nova versão sem reabertura explícita

## D3 — Lembretes e notificações internas

- [ ] `notifications` (tabela padrão Laravel) + canais `database` e `mail`
- [ ] Gatilhos: tarefa vencendo/atrasada, cotação perto de `valid_until`, proposta enviada sem resposta há N dias, validação técnica pendente antes da montagem, parcela a receber/pagar vencendo, brief aprovado sem orçamento, caso sem próxima ação
- [ ] Reusar `NextActionService` e `OperationalQueueService::items()` como fonte da verdade do que está pendente — não criar segunda lógica
- [ ] Preferências por usuário: canal e frequência (imediato, resumo diário, desligado)
- [ ] Central de notificações no shell (`resources/js/layout.tsx`), com contador e marcação de lida
- [ ] Agendamento pelo `Schedule` já existente em `routes/console.php`
- [ ] **Teste:** lembrete não dispara duas vezes para o mesmo fato; usuário com canal desligado não recebe; resumo diário agrupa sem repetir

## D4 — Fronteira da IA, escrita e testada

- [ ] Regra explícita no código e no prompt: IA pode preparar documento, sugerir próxima ação e redigir rascunho de e-mail. **Não pode** enviar comunicação externa, aceitar proposta, contratar fornecedor, aprovar pagamento ou assumir compromisso
- [ ] `AssistantActions` auditado contra essa lista; qualquer ação de efeito externo passa por prévia + confirmação humana
- [ ] **Teste dedicado:** nenhuma rota de efeito externo é alcançável a partir de um job de IA sem `AssistantPreview` confirmada por usuário com autoridade

## D5 — WhatsApp: preparado, não implementado

- [ ] Definir o contrato `ChannelProvider` de forma que WhatsApp entre depois sem refatorar envio, registro e autorização
- [ ] Não implementar nesta fase — decisão registrada

---

# BLOCO E — Segurança e contas

**Objetivo:** conta de usuário utilizável e defensável em produção.

---

- [x] **E1 — Recuperação de senha.** Tabela `password_reset_tokens` existe; rota não. Implementar solicitação, e-mail, token single-use com expiração, rate limit por e-mail e por IP, e invalidação de sessões após troca
- [ ] **E2 — Verificação de e-mail** para usuário criado pela equipe, com primeiro acesso guiado (`users.email_verified_at` já existe)
- [ ] **E3 — Política de senha**: comprimento mínimo, checagem contra lista de senhas comuns, troca obrigatória no primeiro acesso, bloqueio após tentativas (o throttle de login já existe em `routes/web.php:37`)
- [ ] **E4 — 2FA (TOTP)** opcional, **obrigatório** para quem tem `can_approve_commercial` — quem autoriza envio, assinatura e pagamento
- [ ] **E5 — Papéis reais.** `users.role` é string livre com dois valores efetivos; `Ability` tem 6 casos sem matriz. Criar mapa explícito papel → abilities (`admin`, `diretor`, `produtor`, `comercial`, `financeiro`, `operacao`, `leitura`) e cobrir com `AuthorizationMatrixTest` estendido
- [ ] **E6 — Sessão e acesso**: expiração configurável, "sair de todos os dispositivos", registro de acesso (IP, user agent, data) e revisão de sessões ativas
- [ ] **E7 — Dados sensíveis**: revisar `ai_settings.api_key` (hoje texto na tabela) para armazenamento cifrado; conferir que nenhum caminho privado, chave ou nota interna vaza em resposta Inertia
- [ ] **Teste:** token de recuperação usado duas vezes é recusado; usuário sem 2FA configurado não consegue autorizar envio; matriz de permissão cobre todas as rotas de efeito

---

# BLOCO F — Produção e equipe

**Objetivo:** produção de evento com equipe, local e execução física — não uma lista de tarefas.

**Arquivos-chave:** `app/Services/ProductionOperations.php`, `resources/js/pages/Production.tsx`

---

- [ ] **F1 — Múltiplos responsáveis.** `production_tasks.assigned_to` é um único usuário. Nova `task_assignments` (`production_task_id`, `user_id`, `role`, `assigned_by`, `assigned_at`), preservando `assigned_to` como responsável principal
- [ ] **F2 — Escala de equipe.** Nova `crew_assignments`: `opportunity_id`, `user_id` nullable, `supplier_id` nullable, `person_name` (freelancer sem cadastro), `role` enum (`coordenador|produtor|assistente|tecnico_som|tecnico_luz|tecnico_video|cenotecnico|montador|staff|recepcao|seguranca|brigadista|limpeza|motorista|outro`), `call_time`, `end_time`, `rate_cents`, `meal_included`, `transport_included`, `status` enum `previsto|confirmado|presente|ausente|cancelado`, `confirmed_at`, `notes`
- [ ] **F3 — Tarefa com contexto físico.** `production_tasks` ganha `venue_area` (onde no local), `checklist` json, `evidence_photos` json, `estimated_duration_minutes`
- [ ] **F4 — Checklist de montagem e desmontagem** com registro fotográfico e responsável por item; desmontagem exige conferência de devolução de material
- [ ] **F5 — Visualizações reais.** `Production.tsx` já tem os três modos (`list|timeline|calendar`) mas todos renderizam a mesma lista. Implementar linha do tempo com dependências visíveis (`dependency_id` já existe) e calendário por fase
- [ ] **F6 — Validação técnica ligada ao venue** (`venue_id` de A3), comparando medidas declaradas com as do local e alertando divergência
- [ ] **F7 — Ordem de serviço** por fornecedor, gerada da necessidade + cotação selecionada, com confirmação de recebimento
- [ ] **Teste:** escala com pessoa alocada em dois eventos no mesmo horário gera conflito explícito; item de desmontagem sem conferência bloqueia encerramento; tarefa com dependência não concluída não avança (regra já coberta por `ProductionOperationsTest`, estender)

---

# BLOCO G — Documentos e materiais de projeto

**Objetivo:** proposta de produtora de eventos, não documento genérico de três seções.

**Arquivos-chave:** `app/Services/DocumentRevisions.php`, `resources/views/documents/proposal.blade.php`, `resources/js/pages/DocumentWorkspace.tsx`

---

## G1 — Estrutura real de proposta

Hoje `documents.content.sections` tem três chaves: `objective`, `scope`, `conditions`.

- [ ] **Nova tabela `document_sections`**: `document_id`, `sequence`, `key`, `title`, `body`, `is_visible_to_client` bool, `source` json (de onde veio: brief, orçamento, viabilidade)
- [ ] Seções padrão de proposta: `apresentacao`, `entendimento_da_necessidade`, `conceito`, `escopo_detalhado_por_area`, `programacao`, `cronograma`, `equipe`, `investimento`, `condicoes_de_pagamento`, `validade_da_proposta`, `o_que_nao_esta_incluso`, `proximos_passos`
- [ ] **`o_que_nao_esta_incluso` é obrigatória** — é o que evita conflito de escopo depois
- [ ] Seções alimentadas por brief aprovado, orçamento aprovado e plano de pagamento, com marcação de origem e alerta de fonte alterada (padrão `stale` já implementado em `DocumentRevisions::current()`)
- [ ] Migration de dados: as três seções antigas viram `entendimento_da_necessidade`, `escopo_detalhado_por_area` e `condicoes_de_pagamento`
- [ ] **Teste:** proposta sem `o_que_nao_esta_incluso` preenchida não é liberada; seção com fonte alterada marca documento como `stale` sem apagar a versão anterior

## G2 — Templates

- [ ] **Nova tabela `document_templates`**: `name`, `type`, `purpose`, `sections` json, `is_active`, `created_by`, `revision`
- [ ] Templates por tipo de evento (congresso, confraternização, ativação…) e por finalidade (viabilidade, gestão)
- [ ] **Teste:** alterar template não altera documento já gerado

## G3 — Contrato liberável

Hoje a liberação contratual está declarada fora de escopo no próprio código (`resources/js/pages/DocumentWorkspace.tsx:22`).

- [ ] Modelo de contrato com cláusulas versionadas, campos preenchidos do cliente (`tax_id`, `legal_name`, endereço — vindos de A1), do evento (datas, local, público) e do plano de pagamento
- [ ] Liberação exige: cliente com dados cadastrais completos, orçamento aprovado, plano de pagamento aceito e autoridade comercial
- [ ] Integra com assinatura eletrônica (D2)
- [ ] **Teste:** contrato com cliente sem `tax_id` não é liberado; contrato assinado rejeita edição sem reabertura auditada

## G4 — Anexos e materiais

- [ ] Ativar a tabela órfã `attachments`: upload real, escopo por caso e módulo, allowlist de mime, limite de tamanho, quota por caso, storage privado
- [ ] Vínculo com `brief_requirements` (foto de referência), `technical_validations` (planta, desenho), `venues` (regulamento), `payables` (nota fiscal)
- [ ] Nunca servir arquivo por caminho direto — sempre por rota autorizada, como já é feito em `ContextAudioReviewController::audio`
- [ ] **Teste:** anexo de outro caso não é acessível; mime fora da allowlist é recusado; caminho privado nunca aparece na resposta

## G5 — Pacote de projeto

- [ ] Ver B5 — `ProjectPackageBuilder` e `project_packages`

---

# BLOCO H — UX, responsividade e acessibilidade

**Objetivo:** visualmente atraente, eficiente, de fácil utilização — nos três breakpoints, com identidade própria.

**Arquivos-chave:** `resources/js/`, `resources/css/`, `tests/e2e/`

---

## H1 — Legibilidade do código de interface

O front está escrito com linhas de milhares de caracteres — página inteira em 3 a 5 linhas físicas. Funciona, mas trava revisão e manutenção.

- [ ] Prettier + ESLint com largura de linha definida, aplicados a `resources/js/`
- [ ] Reformatação em commit separado, sem mudança de comportamento — os 27 testes e2e e os 12 de Vitest são a rede de segurança
- [ ] Quebrar as páginas maiores (`Feasibility.tsx`, `Production.tsx`, `DocumentWorkspace.tsx`) em componentes por seção
- [ ] **Teste:** suíte completa passa idêntica antes e depois da reformatação

## H2 — Cobertura de interface

- [ ] Hoje: 2 arquivos de teste de UI para 46 páginas/componentes. Cobrir os componentes de decisão: `ContextComposer`, `ContextUploadComposer`, `SpeakerReview`, `BudgetIntakePanel`, painel de autorização de envio, revisão de brief, plano de pagamento
- [ ] Testes de estado, não de renderização: seleção parcial, erro de validação, fonte alterada, permissão ausente

## H3 — Jornadas completas nos três breakpoints

As 11 jornadas do dossiê (`docs/execucao/2026-09-10/08-execucao-e-testes.md:44`) estão todas desmarcadas. Fechar cada uma em desktop 1440×900, tablet 1024×768 e mobile 390×844:

- [ ] Lead → Viabilidade entregue → encerramento sem Gestão
- [ ] Lead → orçamento → proposta aceita → Gestão → produção → pós-evento
- [ ] Mudança técnica → reconfirmação
- [ ] Escopo mudou → orçamento e documento em revisão
- [ ] IA indisponível → operação manual completa
- [ ] Envio falhou → arquivo e tentativa preservados
- [ ] Upload retomado sem duplicação
- [ ] Edição concorrente → recuperação com mensagem
- [ ] Aprovação sem autoridade bloqueada
- [ ] Refresh e novo login preservam registros
- [ ] Reunião gravada → brief revisado → pacote de projeto gerado *(nova, cobre o requisito central)*

## H4 — Acessibilidade

- [ ] `axe-core` integrado ao Playwright, rodando nas telas principais nos três breakpoints
- [ ] Contraste AA em todos os tokens de `resources/css/tokens.css`
- [ ] Foco visível consistente; navegação completa por teclado no painel de revisão de IA e no editor de documento
- [ ] `prefers-reduced-motion` respeitado (hoje há apenas 6 ocorrências de media query de preferência em todo o CSS)
- [ ] Rótulos e mensagens de erro associados aos campos; `aria-live` para status de processamento de áudio
- [ ] **Teste:** zero violação crítica ou séria do axe nas telas principais

## H5 — Estados padronizados

- [ ] Carregando, vazio, erro, sem permissão, offline, processando — componentes únicos reusando `EmptyState`, `Skeleton`, `StatusBadge` já existentes em `resources/js/components/ui/`
- [ ] Toda mensagem em português, explicando o que aconteceu e qual é a próxima ação possível

## H6 — Mobile de verdade

Os testes atuais cobrem navegação e overflow, não operação.

- [ ] Revisão de transcrição no celular: player, segmento, edição de falante e confirmação parcial repensados para toque
- [ ] Comparação de cotações: tabela vira cartões comparáveis abaixo de 620px
- [ ] Aprovação de orçamento e autorização de envio operáveis no celular
- [ ] Gravação de reunião pelo próprio celular, com upload retomável (a base de `upload.ts` já existe)

## H7 — Identidade Padrão RD

- [ ] Aplicar as ilustrações já presentes em `resources/images/rd/optimized/` (`context-core`, `briefing-folder`, `budget-ledger`, `production-path`, `event-memory`) nos estados vazios e cabeçalhos de módulo
- [ ] Tipografia, espaçamento e cor consolidados em `tokens.css`, sem valor solto no componente
- [ ] Substituir o cenário de demonstração "Conferência Horizonte 2026" por seed realista da operação real

---

# BLOCO I — Infraestrutura, dados e operação

**Objetivo:** publicar, observar e recuperar. Transversal — começa junto com o Bloco A.

---

- [x] **I1 — Commitar o Ciclo 04.** 33 arquivos modificados e 7 novos fora do git: compartilhamento de documentos, sourcing de fornecedores, validação técnica, métricas operacionais. Commits separados por domínio, não um commit único
- [ ] **I2 — CI verde de verdade.** O workflow em `.github/workflows/` existe e nunca rodou. Fazer passar: `composer quality`, MariaDB 11.4, `npm run quality`, `npm run test:e2e`, build e verificação do release Hostinger
- [ ] **I3 — MariaDB validado.** `scripts/ci/test-mariadb.sh` nunca executou (conexão recusada). Rodar contra banco dedicado, cobrindo as novas tabelas dos blocos A e C
- [ ] **I4 — Publicação.** Primeiro deploy real na Hostinger com `scripts/deploy/build-hostinger-release.sh` + `verify-hostinger-release.sh` (ambos já testados localmente), `.env` remoto, banco MariaDB, backup prévio no hPanel e smoke test roteirizado
- [ ] **I5 — Worker externo** provisionado, com healthcheck, limite de recurso, retenção alinhada e monitoramento
- [ ] **I6 — Backup automatizado** de banco e storage privado, com **restore testado** — backup não verificado não conta
- [ ] **I7 — Observabilidade.** Log estruturado (`LOG_CHANNEL=daily_json` já previsto no `.env.example`), health check estendido (fila parada, job falho, worker fora do ar, limite de IA), alerta acionável
- [ ] **I8 — Staging** espelhando produção, com dados anonimizados, para validar migration antes de subir
- [ ] **I9 — Runbook** de incidente: fila travada, transcrição presa, e-mail não sai, worker morto, rollback de release

---

# BLOCO J — Fechamento e lançamento

- [ ] **J1** — As 11 jornadas de H3 verdes nos três breakpoints
- [ ] **J2** — Todas as metas do dossiê movidas de "em verificação" para "concluída", com evidência registrada em `docs/execucao/2026-09-10/10-evidencias.md`
- [ ] **J3** — Manual de operação em português: primeiro acesso, cadastro, gravação de reunião, revisão de brief, orçamento, proposta, contrato, produção, pós-evento, financeiro
- [ ] **J4** — Piloto com um evento real de ponta a ponta, do lead ao fechamento financeiro
- [ ] **J5** — Revisão de segurança sobre o diff acumulado
- [ ] **J6** — Nova auditoria completa, repetindo o método desta análise, para confirmar 10/10

---

## Matriz de saída — de 6,5 para 10

| Categoria | Peso | Hoje | Alvo | Blocos que movem |
|---|---:|---:|---:|---|
| Back-end e domínio | 15% | 8,5 | 10 | A, C, F |
| Front-end | 12% | 7,5 | 10 | H1, H2, H5, H7 |
| Responsividade | 6% | 8,0 | 10 | H3, H6 |
| UX e acessibilidade | 8% | 6,5 | 10 | H4, H5, H6 |
| IA — arquitetura | 10% | 8,5 | 10 | B4, B5 |
| **IA — operação real** | 15% | **3,0** | **10** | **B1, B2, B3, B6, B7** |
| Infraestrutura | 10% | 6,0 | 10 | I |
| Segurança e contas | 8% | 5,5 | 10 | E |
| Qualidade e testes | 8% | 8,0 | 10 | H2, H3, I2 |
| Integrações | 5% | 2,5 | 10 | D |
| Observabilidade | 3% | 6,5 | 10 | I7, C6 |

O maior salto disponível está no Bloco B: 15% de peso saindo de 3,0. Nenhum outro bloco entrega tanto.

---

## Verificação

**Por bloco** — nenhuma tarefa é aceita sem o teste de comportamento correspondente. Regra do dossiê, mantida: escrever o teste quando há regra ou risco real, implementar, verificar, registrar evidência. Nunca marcar concluído por existir tela ou rota.

**Comandos de gate:**

```bash
composer quality && npm run quality && npm run test:e2e
```

```bash
bash scripts/ci/test-mariadb.sh
```

```bash
php artisan ai:verify && php artisan ai:evaluate
```

```bash
./scripts/deploy/build-hostinger-release.sh && ./scripts/deploy/verify-hostinger-release.sh dist/hostinger
```

**Verificação de ponta a ponta do requisito central** — a jornada que prova o produto:

1. Gravar uma reunião real de 60+ minutos e subir pelo celular, interrompendo a conexão no meio para forçar a retomada
2. Worker externo prepara e transcreve com diarização
3. Extração devolve brief tipado + requisitos por área + programação, cada item citando o trecho de origem
4. Revisar no navegador, aceitar parte, recusar parte, editar um item classificado como `hypothesis`
5. Confirmar → `event_briefs` preenchido, `brief_field_sources` gravado por campo
6. Abrir qualquer campo e ouvir o trecho exato da gravação que o originou
7. Gerar pacote de projeto (PDF + ZIP versionado)
8. Seguir para necessidades de fornecedor, orçamento, proposta com plano de pagamento, autorização de envio, contrato assinado, produção com escala e pós-evento com previsto × realizado
9. Repetir os passos 4 a 8 em 390×844

Se essa jornada fecha inteira, nos três breakpoints, com evidência rastreável em cada campo e sem nenhuma ação externa disparada sem autorização humana — o sistema está pronto para lançamento.
