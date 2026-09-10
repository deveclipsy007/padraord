# Padrão RD OS — Plano de Conclusão (v2)

Data: 10/09/2026. Documento de trabalho da equipe. Substitui a v1 do mesmo arquivo, que descrevia um MVP.
Complementa `docs/execucao/2026-09-10/` (dossiê de execução).

---

## Por que existe a v2

A v1 listava o mínimo para operar. A direção mudou: o alvo é um sistema **excelente**, não um MVP entregável. Infraestrutura, publicação e servidor saem do caminho crítico e vão para o fim — o foco é desenvolvimento, funcionalidade e experiência.

## Estado real hoje

**Suítes:** 196 testes PHP (1.343 asserções), 12 Vitest, TypeScript sem erros, 48 Playwright em três breakpoints, Pint limpo. Tudo verde.
**Inventário:** 131 rotas, 53 tabelas, 31 migrations, ~400 KB de PHP, ~330 KB de TS/TSX.

### Já entregue (branch `ciclo-04-consolidacao`)

| Commit | Entrega |
|---|---|
| `f51fa01` | Ciclo 04 inteiro versionado e verificado |
| `fe62287` | Dossiê de execução e plano |
| `712be04` | Transcrição destravada pela administração; quatro motores de IA visíveis; `SESSION_DRIVER` do e2e corrigido |
| `4a3220d` | Recuperação de senha completa, com revogação de sessões e limites |
| `0c2def0` | `padraord:doctor` e correção do falso verde de e-mail |
| `e3dc16b` | Registro do executado |

### O que trava hoje

`php artisan padraord:doctor` aponta **um bloqueio**: `MAIL_MAILER=log`. Nada de e-mail chega a ninguém.

---

## Decisões

| Tema | Decisão | Quem decidiu |
|---|---|---|
| E-mail | **SMTP da Hostinger** | Yohann |
| Infraestrutura de IA | Híbrido: app na Hostinger + worker externo com FFmpeg | Yohann |
| Briefing | Entidade real de evento, tipada | Yohann |
| Financeiro | Financeiro operacional do evento | Yohann |
| Comunicação | Envio real com autorização humana; assinatura eletrônica; lembretes. IA prepara, nunca envia | Yohann |
| Publicação e servidor | Fora do caminho crítico; vão para o Bloco I, no fim | Yohann |
| **Identidade visual** | **Evoluir, não refazer** | Claude — ver abaixo |

### Sobre a decisão visual

O padrão de referência da casa são as apresentações em `padraord-proposta/` — animadas, densas, bonitas. O sistema está muito abaixo disso.

**Não vamos refazer do zero.** Motivo: a arquitetura de shell atual (rail + trilha de contexto + conteúdo) já passa em três breakpoints com 48 testes de navegador, e jogar isso fora custa cobertura provada sem resolver o problema real. O que separa o sistema das apresentações não é o esqueleto — é **polimento, densidade de informação e visualização de dados**, que hoje simplesmente não existe (zero gráficos no sistema inteiro).

**Vamos evoluir:** refinar tipografia, escala e profundidade; movimento com propósito; usar as ilustrações RD que já estão em `resources/images/rd/optimized/`; e introduzir visualização de dados de verdade. Isso exige, antes, quebrar o front-end em componentes — hoje uma página inteira cabe em três linhas físicas de código, e nesse formato nenhuma iteração visual é viável.

---

## Invariantes que não podem quebrar

1. **Revisão humana obrigatória.** IA nunca aprova, envia, contrata ou negocia.
2. **Evidência rastreável.** Toda sugestão cita o trecho de origem. `unknown` nunca vira `fact`.
3. **Nada é sobrescrito.** Versão anterior sempre preservada.
4. **Conteúdo é dado, nunca instrução.**
5. **Concorrência protegida.** Padrão de `CaseContextService::confirm()` e `BudgetRevisionService::mutate()`.
6. **Autoridade comercial separada.** `can_approve_commercial` governa liberação, envio e assinatura.
7. **IA indisponível nunca bloqueia.** Todo fluxo tem caminho manual equivalente.
8. **Custo pago é reservado antes e reconciliado depois.**

---

## Ordem de execução

```
B0 (E-mail) ─→ K (Fundação de interface) ─┬─→ L (Diferenciais) ─┐
                                          │                     │
A (Domínio de eventos) ───────────────────┼─→ B (IA real) ──────┼─→ H (Acabamento) ─→ J
                                          ├─→ G (Documentos)    │
                                          ├─→ C (Financeiro)    │
                                          └─→ F (Produção)      │
D (Comunicação) ──────────────────────────────────────────────┘
E (Segurança) · quase pronto        I (Infra e publicação) · por último
```

**K e A são as duas fundações.** K destrava toda melhoria visual; A destrava briefing, proposta, produção e financeiro. Podem correr em paralelo — K é front, A é back.

---

# B0 — E-mail funcionando

Único bloqueio atual. Pequeno.

- [ ] `MAIL_MAILER=smtp` com host, porta e credenciais da Hostinger; `MAIL_FROM_ADDRESS` e `MAIL_FROM_NAME` da Padrão RD
- [ ] Documentar em `docs/hostinger-deploy.md` os valores exatos (host, porta 465/587, criptografia) sem credencial versionada
- [ ] Layout de e-mail com identidade RD em `resources/views/vendor/mail/` — hoje usa o tema padrão do Laravel
- [ ] Comando `php artisan padraord:mail-test {destinatário}` que envia uma mensagem real e registra o resultado
- [ ] `padraord:doctor` passa a reportar E-mail como `ok`
- [ ] **Teste:** transporte que não entrega continua sendo reportado como bloqueio; layout renderiza em texto e HTML

---

# K — Fundação de interface

**Objetivo:** tornar o front-end iterável e elevar o padrão visual. Sem isso, todo item de design custa dez vezes mais.

**Arquivos-chave:** `resources/js/`, `resources/css/tokens.css`, `resources/js/components/ui/`

## K1 — Quebrar o código em componentes

O front está escrito em linhas de milhares de caracteres. `Feasibility.tsx` tem 452 linhas para o que seriam ~2.000 formatadas; `Production.tsx` cabe em 49 linhas físicas.

- [ ] Prettier + ESLint com largura de linha definida, aplicados a `resources/js/`
- [ ] Reformatação em **commit separado**, sem mudança de comportamento — os 48 e2e e 12 Vitest são a rede
- [ ] Quebrar por seção as páginas grandes: `Feasibility`, `Production`, `DocumentWorkspace`, `Suppliers`, `History`, `PostEvent`, `Budget`
- [ ] Extrair para `components/ui/` o que se repete: painel, cabeçalho de seção, linha de registro, barra de filtro, formulário em grade
- [ ] **Teste:** suíte completa idêntica antes e depois

## K2 — Sistema de design de verdade

- [ ] `tokens.css` vira a fonte única: escala tipográfica, espaçamento, raio, profundidade, cor semântica (sucesso/atenção/risco/neutro), duração e curva de animação
- [ ] Nenhum valor solto em componente — regra verificada por teste de contrato (o `VisualSystemContractTest` já existe, estender)
- [ ] **Modo escuro** completo, respeitando `prefers-color-scheme` e com escolha manual persistida
- [ ] `prefers-reduced-motion` respeitado em todo movimento
- [ ] Ilustrações RD (`context-core`, `briefing-folder`, `budget-ledger`, `production-path`, `event-memory`) aplicadas em estados vazios e cabeçalhos de módulo

## K3 — Visualização de dados

Hoje o sistema não tem **um único gráfico**. Para uma ferramenta de gestão isso é uma lacuna grave.

- [ ] Biblioteca de gráficos leve, sem dependência pesada, coerente com os tokens
- [ ] Componentes: barra, linha, área, rosca, marcador de meta, faixa comparativa, minigráfico embutido
- [ ] Legibilidade em claro e escuro; rótulo acessível e tabela alternativa para leitor de tela
- [ ] Aplicações reais: funil comercial por etapa, margem prevista × realizada, custo por categoria, carga por pessoa, evolução de consumo de IA, previsto × realizado do evento
- [ ] **Teste:** gráfico sem dados mostra estado vazio, não eixo vazio; valores conferem com o serviço que os calcula

## K4 — Interação de produto

- [ ] **Edição no lugar** — alterar campo sem abrir tela nova, com estado salvando/salvo/erro honesto
- [ ] **Ações em lote** em listas: arquivar, atribuir, mudar etapa
- [ ] **Filtros salvos** por usuário, com URL compartilhável
- [ ] Estados padronizados: carregando, vazio, erro, sem permissão, offline, processando — componentes únicos
- [ ] Toda mensagem em português dizendo o que aconteceu **e** qual é a próxima ação possível
- [ ] Atalhos de teclado além do ⌘K: navegação entre módulos do caso, salvar, confirmar prévia

## K5 — Mobile de operação, não de consulta

Os testes atuais cobrem navegação e transbordo, não operação.

- [ ] Tabelas viram cartões comparáveis abaixo de 620px (cotações, orçamento, histórico)
- [ ] Revisão de transcrição repensada para toque: player, segmento, falante, aceite parcial
- [ ] Aprovação de orçamento e autorização de envio operáveis no celular
- [ ] Gravar reunião pelo próprio celular com envio retomável (base já existe em `upload.ts`)

## K6 — Acessibilidade

- [ ] `axe-core` no Playwright, nas telas principais, nos três breakpoints
- [ ] Contraste AA em todos os tokens, claro e escuro
- [ ] Foco visível consistente; navegação completa por teclado no painel de revisão de IA e no editor de documento
- [ ] `aria-live` para processamento de áudio; rótulos e erros associados aos campos
- [ ] **Teste:** zero violação crítica ou séria

---

# A — Modelo de domínio de eventos

**Objetivo:** o sistema passa a conhecer evento, local, requisito técnico e programação como entidades reais, em vez de texto solto.

## A1 — `clients`: empresa de verdade

Hoje: `name`, `industry`, `notes`. Não dá para emitir contrato.

- [ ] `legal_name`, `tax_id` (único, só dígitos), `tax_id_type`, `state_registration`, `municipal_registration`
- [ ] `billing_email`, `billing_address` (json), `default_payment_terms_days`
- [ ] `segment` (`corporativo|social|institucional|cultural|esportivo|religioso|governo|terceiro_setor`), `tier` (`prospect|ativo|recorrente|inativo`)
- [ ] Regra `app/Rules/TaxId.php` com dígito verificador, sem pacote externo
- [ ] Duplicidade por `tax_id` reusando o padrão de `SupplierController::duplicates`
- [ ] **Teste:** contrato não é liberado sem `tax_id`; duplicado é recusado apontando o registro existente

## A2 — `contacts`: papel na decisão

- [ ] `is_primary`, `is_decision_maker`, `department`, `whatsapp`, `preferred_channel`
- [ ] No máximo um `is_primary` por cliente
- [ ] `opportunity_qualifications.decision_maker_contact_id` exige contato marcado como decisor
- [ ] **Teste:** qualificar apontando não-decisor é recusado com explicação

## A3 — `venues`: local reutilizável

- [ ] Identidade: `name`, `venue_type`, `tax_id`, `address` (json), `city`, `state`
- [ ] Capacidade: `capacity_seated`, `capacity_standing`, `capacity_cocktail`, `capacity_auditorium`
- [ ] Físico: `floor_area_m2`, `ceiling_height_m`, `column_notes`, `floor_load_kg_m2`
- [ ] **Acesso de carga:** `door_width_m`, `door_height_m`, `has_loading_dock`, `has_freight_elevator`, `elevator_capacity_kg`, `load_in_notes`
- [ ] Energia: `power_available_kva`, `power_phases`, `has_generator_area`
- [ ] Operação: `noise_curfew_time`, `load_in_window`, `load_out_window`, `parking_spots`, `catering_policy`
- [ ] Governança: `restrictions`, `rules_document_path`, contato, `status`, `revision`
- [ ] `opportunities.location` migra para `venue_id` + `location_note`
- [ ] `technical_validations` ganha `venue_id`
- [ ] Páginas `Venues.tsx` e `VenueProfile.tsx` com histórico de eventos no local
- [ ] **Teste:** medida declarada maior que a porta do local gera alerta bloqueante

## A4 — `event_briefs`: o coração

Substitui `opportunities.briefing_data` (JSON) e os 8 campos de `BriefingPayload::FIELDS`.

**Identidade:** `opportunity_id`, `revision`, `status`, `schema_version`, `event_name`, `event_type` (enum com 20 tipos reais), `event_format` (`presencial|hibrido|online`), `edition`, `is_recurring`, `previous_opportunity_id`

**Tempo** — hoje só existe `event_date`, e evento de dois dias é impossível de registrar:
`starts_at`, `ends_at`, `setup_starts_at`, `teardown_ends_at`, `timezone`, `date_confidence`, `alternative_dates`

**Local:** `venue_id`, `venue_status`, `city`, `state`, `venue_requirements`

**Público:** `audience_expected_min/max`, `audience_confidence`, `audience_profile`, `audience_segments`, `has_vip`, `vip_notes`, `accessibility_requirements`

**Mensagem:** `objective`, `success_criteria` (mensuráveis), `key_message`, `tone`, `brand_notes`, `brand_assets`

**Investimento:** `budget_declared_cents`, `budget_range_min/max_cents`, `budget_confidence`, `budget_includes_taxes`, `payment_expectation`

**Governança:** `constraints`/`risks`/`deadlines` tipados, `references`, `completeness_score`, `missing_critical`, `approved_by/at`

- [ ] Migration + model + enums nativos
- [ ] Migração de dados dos 8 campos antigos, preservando `briefing_revision`
- [ ] `briefing_data` vira legado somente-leitura, com teste garantindo que nada novo escreve nela
- [ ] `EventBriefService::mutate()` no padrão de `BudgetRevisionService`
- [ ] `completeness_score` com peso por criticidade
- [ ] **Teste:** aprovar com `missing_critical` é recusado; aprovado rejeita alteração silenciosa; revisão concorrente recupera

## A5 — `brief_requirements`: escopo técnico por área

O diferencial de produtora. Hoje escopo é uma string.

- [ ] `area` (enum com 30 áreas: palco, som, iluminação, LED, cenografia, climatização, energia, catering, staff, segurança, credenciamento, streaming, tradução…)
- [ ] `requirement`, `quantity`, `unit`, `priority` (`obrigatorio|desejavel|opcional`), `status`, `source`
- [ ] `classification` (`fact|hypothesis|conflict|unknown`) e `evidence_segment_ids` — aponta para o trecho da gravação
- [ ] Gera `supplier_needs` por prévia confirmável
- [ ] Alimenta a prévia de escopo de produção, complementando `ProductionOperations::prepareFromApprovedScope()`
- [ ] **Teste:** requisito `hypothesis` não vira necessidade sem confirmação humana

## A6 — `brief_program_blocks`: programação

- [ ] `sequence`, `starts_at`, `ends_at`, `title`, `description`, `location_note`, `responsible_area`, `attendees_estimate`, `source`, `evidence_segment_ids`
- [ ] Bloco fora da janela do evento é recusado; sobreposição alerta sem bloquear
- [ ] Timeline editável, arrastar no desktop e reordenar por botão no mobile
- [ ] **Teste:** sobreposição sinalizada; bloco fora da data recusado

## A7 — Vínculo permanente brief ↔ gravação

Hoje o purge apaga o áudio em 30 dias e o vínculo morre.

- [ ] `case_context_entries` ganha `title`, `meeting_date`, `meeting_kind`, `retain_forever`, `participants`
- [ ] **`brief_field_sources`**: `event_brief_id`, `field_path`, `case_context_entry_id`, `segment_ids`, `extracted_at`, `confirmed_by/at`
- [ ] `context:purge-expired-audio` nunca remove áudio vinculado a brief aprovado; registra o motivo
- [ ] **Player sincronizado:** cada campo do brief tem ícone de origem que abre o áudio no instante exato
- [ ] **Teste:** purge preserva áudio de brief aprovado; campo confirmado mantém origem após nova revisão

## A8 — Dívidas do schema

- [ ] `stage` × `commercial_stage`: dois enums paralelos (13 e 8 estados). Consolidar em um
- [ ] `client_name`/`contact_name`/`contact_email` denormalizados contra `client_id`/`contact_id`: derivar por relação, colunas viram snapshot histórico
- [ ] `attachments`: tabela órfã — ativar no G4 ou remover
- [ ] `briefing_audio` (legado) × `context_audio_assets` (atual): migrar ou aposentar explicitamente
- [ ] **Teste:** `DatabasePortabilityTest` cobre as novas tabelas em SQLite e MariaDB

---

# B — IA em produção real

## B1 — Verificação com chamada real

Modelos e parâmetros já conferidos em 10/09/2026 e **corretos**: `gpt-5.6-luna` (extração, structured outputs, 1,05M de contexto, US$ 0,20/1,20 por milhão) e `gpt-4o-transcribe-diarize` com `diarized_json` + `chunking_strategy: auto`.

- [x] `AiConfiguration::publicState()` deixou de anunciar `gpt-4o-mini` fixo
- [x] `.env.example` ganhou `AI_MODE` e `AI_DATA_POLICY_APPROVED`
- [ ] `php artisan ai:verify` — chamada real mínima de cada operação, gravando resultado, custo e `request_id` em `AiRun`
- [ ] `config/ai.php` ganha catálogo de modelos com data da última verificação
- [ ] **Teste:** chave ausente falha com mensagem acionável, não com stack trace

## B2 — Worker externo de mídia

- [ ] `app/Media/FfmpegMediaPreparationProvider.php` — o contrato `MediaPreparationProvider` já existe
- [ ] **`media_jobs`**: `context_audio_asset_id`, `operation`, `external_id`, `status`, `callback_token_hash`, `attempts`, `payload`, `result`, `error`
- [ ] `POST /api/media/callback` com HMAC e idempotência. Callback é **dado, nunca instrução**: só move status e anexa resultado
- [ ] Envio direto do navegador para o worker por URL assinada de curta duração
- [ ] Fallback para `PassThroughMediaPreparationProvider` + preparo no navegador, com aviso na tela
- [ ] **Teste:** HMAC inválido recusado; callback repetido não duplica segmento; worker fora do ar mantém o manual vivo

## B3 — Áudio longo

- [ ] Partes **decodificáveis** com overlap, preservando offsets absolutos
- [ ] **Continuidade de falante entre partes.** A API diariza cada requisição de forma independente — o "Falante A" da parte 1 não é o da parte 2, e acima de 25 MB o arquivo obrigatoriamente vira várias requisições. Usar `known_speaker_names[]` + `known_speaker_references[]` (amostras de 2–10s da primeira parte). Sem isso, reunião longa sai com falantes embaralhados
- [ ] Costura por similaridade no overlap, sem descartar fala
- [ ] `context_audio_assets` ganha `part_count` e `parts`
- [ ] Com worker externo, o job do app vira despacho + acompanhamento, não processamento longo
- [ ] **Teste:** áudio de 3h processa por partes; falha na parte 7 não reprocessa nem recobra as anteriores; offsets batem com o player

## B4 — Extração alimenta o brief tipado

- [ ] `app/AI/EventBriefSchema.php` — JSON Schema estrito com os enums do domínio. O modelo escolhe de lista fechada, não inventa categoria
- [ ] `ContextIntelligenceSchema::MODULES` ganha `requirements` e `program`
- [ ] Extração devolve requisitos e blocos de programação candidatos, cada um com `evidence_segment_ids`
- [ ] `CaseContextService::confirm()` aceita por módulo **e por item**, gravando `brief_field_sources`
- [ ] **Teste:** área fora do enum é recusada; `unknown` não pode ser confirmado sem edição humana

## B5 — Pacote de projeto

- [ ] `ProjectPackageBuilder`: capa, brief, requisitos por área, programação, cronograma de montagem/evento/desmontagem, equipe, investimento
- [ ] PDF (dompdf já instalado) + ZIP versionado
- [ ] **`project_packages`**: `opportunity_id`, `revision`, `path`, `hash`, `contents`, `source_brief_revision`
- [ ] Regeneração idempotente; brief alterado marca o pacote anterior como desatualizado sem apagar
- [ ] Informação vinda de IA e ainda não confirmada é marcada visualmente
- [ ] **Teste:** gerar duas vezes sem mudança não cria segunda versão

## B6 — Avaliação medida

- [ ] 10 reuniões (sintéticas + reais autorizadas): fatos críticos, informação ausente, contradição, múltiplos falantes, mudança de escopo, falha e retomada
- [ ] Métricas por campo: precisão, recall, taxa de correção humana, custo real, tempo até brief revisável
- [ ] `php artisan ai:evaluate` gera `docs/operations/ai-briefing-evaluation.md`
- [ ] **Teste:** avaliação roda com respostas gravadas, sem custo em CI

## B7 — Degradação

- [ ] Erros acionáveis em português para cada causa: chave, política, tarifa, limite, worker, áudio corrompido, áudio longo demais
- [ ] Tela de consumo: gasto do mês por operação e por caso
- [ ] **Teste:** com IA em `manual`, todo o fluxo de brief funciona pelo teclado

---

# L — Diferenciais competitivos

**Objetivo:** o que separa "sistema bom" de "sistema que a concorrência não tem". Depende de A e K.

## L1 — Link de briefing para o cliente preencher

Não existe hoje. O briefing é só interno.

- [ ] **`briefing_templates`**: perguntas e condições versionadas por tipo de evento; versão liberada não muda
- [ ] **`briefing_links`**: token só em hash, validade, revogação, modo de múltiplas respostas, **modo de teste identificado**
- [ ] **`briefing_submissions`**: respostas de um respondente, rascunho/revisão/final, identidade própria
- [ ] Página pública com a identidade RD, não cara de formulário genérico: salva e retoma, volta, revisa, erro acessível, funciona no celular
- [ ] Nunca perguntar de novo o que já está confirmado — apresentar para correção
- [ ] Respostas entram como contexto do caso, na mesma esteira de prévia e confirmação
- [ ] Submissão de teste não dispara análise paga nem vira fato de proposta
- [ ] **Teste:** token revogado recusa; respostas parciais sobrevivem a queda de conexão; página não expõe custo, margem ou nota interna

## L2 — Memória de eventos anteriores

O recurso mais forte disponível para uma produtora com histórico.

- [ ] Similaridade entre eventos por tipo, porte, público, local e áreas de requisito
- [ ] "Esse evento se parece com X" traz: escopo usado, fornecedores que atenderam, **custo realizado** (não o orçado) e ocorrências do pós-evento
- [ ] Reaproveitar como prévia confirmável — nunca copiar direto
- [ ] Evento recorrente puxa a edição anterior pelo `previous_opportunity_id`
- [ ] Painel de referência: quanto costuma custar cada área por porte de evento, com faixa e amostra
- [ ] **Teste:** sugestão sempre cita o evento de origem; caso arquivado ou de outro cliente não vaza custo sem permissão

## L3 — Catálogo de entregáveis e cenários de escopo

- [ ] **`deliverables`**: escopo incluso, exclusões, critério de aceite, insumos do cliente, papéis, horas, recorrência, dependências
- [ ] **`scope_scenarios`**: mínimo, recomendado e completo, compostos a partir do catálogo e dos requisitos do brief
- [ ] Comparação lado a lado dos três cenários, com o que entra e o que sai
- [ ] O orçamento continua sendo a **única** autoridade de preço; cenário não precifica
- [ ] Custo desconhecido permanece bloqueador — nunca preenchido com estimativa inventada
- [ ] **Teste:** cenário sem custo de item obrigatório não vira proposta; alterar catálogo não altera proposta já emitida

## L4 — Comparação honesta de cotações

- [ ] Normalizar unitário × pacote antes de comparar (o campo `price_basis` já existe)
- [ ] Sinalizar escopo não equivalente, validade vencida e revisão substituída
- [ ] Nunca presumir que o menor preço é a melhor opção — a tela diz isso explicitamente
- [ ] Comparação exportável para a decisão com o cliente
- [ ] **Teste:** comparar pacote com unitário sem normalizar é impedido

## L5 — Busca que entende produção

- [ ] Buscar "palco 8x4" encontra em brief, requisito, cotação, validação técnica e evento anterior
- [ ] Filtros por área, período, cliente, local e faixa de valor
- [ ] Resultado mostra o contexto do achado, não só o título
- [ ] **Teste:** resultado respeita permissão e não atravessa caso arquivado sem intenção

## L6 — Painel de decisão

- [ ] Substituir lista de pendências por **o que trava o quê**: "orçamento parado porque falta cotação de som"
- [ ] Cadeia de bloqueio visível: brief → viabilidade → orçamento → proposta → contrato → produção
- [ ] Reusar `NextActionService` e `OperationalQueueService::items()` como fonte única
- [ ] **Teste:** o painel deriva de dados reais e não duplica lógica de pendência

---

# G — Documentos e proposta

## G1 — Estrutura real de proposta

Hoje `documents.content.sections` tem três chaves: objetivo, escopo, condições.

- [ ] **`document_sections`**: `document_id`, `sequence`, `key`, `title`, `body`, `is_visible_to_client`, `source`
- [ ] Seções: apresentação, entendimento, conceito, escopo por área, programação, cronograma, equipe, investimento, condições de pagamento, validade, **o que não está incluso**, próximos passos
- [ ] **`o_que_nao_esta_incluso` é obrigatória** — é o que evita briga de escopo depois
- [ ] Alimentadas por brief, orçamento e plano de pagamento, com marcação de origem e alerta de fonte alterada
- [ ] **Teste:** proposta sem exclusões não é liberada; fonte alterada marca desatualizado sem apagar versão

## G2 — Proposta bonita

O padrão a alcançar são as apresentações em `padraord-proposta/`.

- [ ] Biblioteca controlada de composições visuais e cenas de demonstração
- [ ] Imagens reais ou autorizadas primeiro; imagem gerada é pedida explicitamente, salva, revisada e marcada como conceitual
- [ ] Logotipo do cliente como camada separada
- [ ] **Dois clientes do mesmo setor não podem virar a mesma proposta trocando o nome** — narrativa, hierarquia e exemplos variam com o contexto revisado
- [ ] Prévia e publicado usam o **mesmo** documento estruturado
- [ ] Ao liberar, congela JSON público, corpo renderizado, estilos, fontes, logos e imagens. Atualização da biblioteca afeta só versões novas
- [ ] **Teste:** link antigo continua renderizando o conteúdo congelado após a biblioteca mudar

## G3 — Templates

- [ ] **`document_templates`**: por tipo de evento e por finalidade (viabilidade, gestão)
- [ ] **Teste:** alterar template não altera documento já gerado

## G4 — Contrato liberável

Hoje está declarado fora de escopo no próprio código.

- [ ] Cláusulas versionadas, preenchidas com dados do cliente (A1), do evento (A4) e do plano de pagamento (C1)
- [ ] Liberação exige cadastro completo, orçamento aprovado, plano aceito e autoridade comercial
- [ ] **Teste:** contrato sem `tax_id` não é liberado; assinado rejeita edição sem reabertura auditada

## G5 — Anexos

- [ ] Ativar a tabela órfã `attachments`: escopo por caso e módulo, allowlist de mime, limite, quota, armazenamento privado
- [ ] Vínculo com requisito (foto de referência), validação técnica (planta), local (regulamento), pagável (nota fiscal)
- [ ] Nunca servir por caminho direto — sempre por rota autorizada
- [ ] **Teste:** anexo de outro caso não é acessível; caminho privado nunca aparece na resposta

## G6 — Aceite atômico

- [ ] Aceite registra signatário declarado, versão exata, hash do conteúdo, data e confirmação explícita
- [ ] **Na mesma transação:** fecha versões concorrentes, marca a oportunidade como contratada, cria o projeto e abre o onboarding
- [ ] Notificação sai depois, por outbox — falha de envio não desfaz o aceite
- [ ] Aceite repetido devolve o mesmo recibo e nunca cria segundo projeto
- [ ] **Teste:** repetição idempotente; falha de notificação não reverte estado comercial

---

# C — Financeiro operacional

## C1 — Condições de pagamento

- [ ] **`payment_plans`** e **`payment_plan_installments`** com gatilho (`assinatura|dias_antes_evento|dias_apos_evento|entrega|data_fixa|marco`)
- [ ] Soma das parcelas igual ao total ao centavo, via `Money::ratio`
- [ ] Plano vira seção da proposta
- [ ] **Teste:** rateio percentual fecha ao centavo; plano aceito rejeita alteração

## C2 — Contas a receber

- [ ] **`receivables`** com baixa parcial; `atrasado` derivado por data, nunca gravado
- [ ] Gerado do plano aceito por prévia confirmável
- [ ] **Teste:** baixa maior que o saldo recusada; cancelar com baixa exige justificativa

## C3 — Contas a pagar

- [ ] **`payables`** com origem rastreável em item de orçamento aprovado ou cotação selecionada
- [ ] Aprovação de pagamento exige autoridade
- [ ] **Teste:** pagável sem origem recusado; pagar sem aprovação bloqueado

## C4 — Previsto × realizado por categoria

- [ ] **`event_cost_results`** por categoria, com `variance_reason`
- [ ] Desvio acima do limite exige justificativa para fechar o pós-evento
- [ ] **Teste:** fechamento bloqueado sem justificar desvio relevante

## C5 — Margem viva

- [ ] `EventProfitability` reusando `Money::breakdown()`
- [ ] Receita contratada, custo previsto, custo realizado, margem prevista e realizada
- [ ] Margem realizada só é final com pós-evento encerrado
- [ ] **Teste:** bate com a soma dos itens; evento aberto marca margem como provisória

---

# F — Produção e equipe

- [ ] **F1** `task_assignments` — hoje tarefa tem um responsável só
- [ ] **F2 `crew_assignments`**: pessoa ou fornecedor, papel (16 funções), `call_time`, `end_time`, `rate_cents`, alimentação, transporte, status, confirmação
- [ ] **F3** `production_tasks` ganha `venue_area`, `checklist`, `evidence_photos`, `estimated_duration_minutes`
- [ ] **F4** Checklist de montagem e desmontagem com foto; desmontagem exige conferência de devolução
- [ ] **F5 Linha do tempo de verdade.** `Production.tsx` tem três modos — Lista, Linha do tempo, Agenda — e **os três renderizam a mesma lista**. Implementar Gantt com dependências visíveis (`dependency_id` já existe), caminho crítico e calendário por fase
- [ ] **F6** Validação técnica ligada ao local, comparando medidas e alertando divergência
- [ ] **F7** Ordem de serviço por fornecedor, gerada da necessidade + cotação, com confirmação de recebimento
- [ ] **Teste:** pessoa escalada em dois eventos no mesmo horário gera conflito explícito; desmontagem sem conferência bloqueia encerramento

---

# D — Comunicação e assinatura

## D1 — Envio real com autorização humana

- [ ] **`mail_logs`**: destinatários, assunto, hash do corpo, id do provedor, status, `authorized_by/at`, entrega, abertura, erro, tentativa
- [ ] Fluxo: rascunho → revisão → **tela de autorização** mostrando destinatário, assunto, anexo e link → confirmação por quem tem autoridade → sistema envia e registra
- [ ] `DocumentRevisions::sent()` aceita registro automático, mantendo a evidência manual como fallback
- [ ] Falha de envio preserva documento e tentativa
- [ ] **Teste:** envio sem autorização recusado; sem `can_approve_commercial` não autoriza; reenvio não duplica registro

## D2 — Assinatura eletrônica

- [ ] Contrato `app/Contracts/SignatureProvider.php` + implementação brasileira + `NullSignatureProvider`
- [ ] **`signature_requests`** com status, signatários, documento assinado e hash
- [ ] Webhook verificado e idempotente atualiza `documents.signed_at` e cria `external_signature_records`
- [ ] Registro manual **permanece** válido
- [ ] **Teste:** webhook forjado recusado; repetido não duplica

## D3 — Lembretes internos

- [ ] Gatilhos: tarefa vencendo, cotação perto da validade, proposta sem resposta, validação técnica pendente antes da montagem, parcela vencendo, brief aprovado sem orçamento, caso sem próxima ação
- [ ] Fonte única: `NextActionService` e `OperationalQueueService::items()`
- [ ] Preferência por usuário: canal e frequência
- [ ] Central de notificações no shell, com contador e marcação de lida
- [ ] **Teste:** não dispara duas vezes para o mesmo fato; canal desligado não recebe

## D4 — Fronteira da IA

- [ ] IA pode preparar documento, sugerir ação e redigir rascunho. **Não pode** enviar, aceitar, contratar, aprovar pagamento ou assumir compromisso
- [ ] **Teste dedicado:** nenhuma rota de efeito externo é alcançável a partir de um job de IA sem prévia confirmada por quem tem autoridade

## D5 — WhatsApp

- [ ] Definir `ChannelProvider` para que entre depois sem refatorar envio, registro e autorização. Não implementar agora

---

# E — Segurança e contas

- [x] **E1** Recuperação de senha, com revogação de sessões, limites e resposta que não revela contas
- [ ] **E2** Verificação de e-mail e primeiro acesso guiado
- [ ] **E3** Política de senha e troca obrigatória no primeiro acesso
- [ ] **E4** 2FA opcional, **obrigatório** para quem tem `can_approve_commercial`
- [ ] **E5** Papéis reais: mapa explícito papel → habilidade (`admin`, `diretor`, `produtor`, `comercial`, `financeiro`, `operacao`, `leitura`), coberto por `AuthorizationMatrixTest`
- [ ] **E6** Expiração de sessão, "sair de todos os dispositivos", registro de acesso
- [ ] **E7** Nenhum caminho privado, chave ou nota interna em resposta ao navegador
- [ ] **Teste:** sem 2FA não autoriza envio; matriz cobre todas as rotas de efeito

---

# H — Acabamento

- [ ] Cobertura de UI para os componentes de decisão: revisão de contexto, envio de áudio, falantes, entrada de orçamento, autorização de envio, revisão de brief, plano de pagamento
- [ ] **Jornadas completas nos três breakpoints:**
  - [ ] Lead → viabilidade entregue → encerramento sem gestão
  - [ ] Lead → orçamento → proposta aceita → gestão → produção → pós-evento
  - [ ] Mudança técnica → reconfirmação
  - [ ] Escopo mudou → orçamento e documento em revisão
  - [ ] IA indisponível → operação manual completa
  - [ ] Envio falhou → arquivo e tentativa preservados
  - [ ] Envio retomado sem duplicação
  - [ ] Edição concorrente → recuperação
  - [ ] Aprovação sem autoridade bloqueada
  - [ ] Recarga e novo login preservam registros
  - [ ] **Reunião gravada → brief revisado → pacote de projeto gerado**
  - [ ] **Cliente preenche briefing por link → vira contexto do caso**
- [ ] Substituir o cenário "Conferência Horizonte 2026" por dados realistas da operação

---

# I — Infraestrutura e publicação

Por último, por decisão do Yohann.

- [ ] **I1** ~~Commitar o Ciclo 04~~ — feito em `f51fa01`
- [ ] **I2** CI verde de verdade: `composer quality`, MariaDB 11.4, `npm run quality`, e2e, build e verificação do release
- [ ] **I3** MariaDB validado cobrindo as tabelas novas
- [ ] **I4** Publicação na Hostinger com backup prévio e smoke test
- [ ] **I5** Worker externo provisionado e monitorado
- [ ] **I6** Backup automatizado com **restore testado**
- [ ] **I7** Log estruturado, health check estendido, alerta de fila parada
- [ ] **I8** Ambiente de homologação com dados anonimizados
- [ ] **I9** Runbook de incidente

---

# J — Fechamento

- [ ] Todas as jornadas de H verdes nos três breakpoints
- [ ] Metas do dossiê movidas para concluída, com evidência em `10-evidencias.md`
- [ ] Manual de operação em português
- [ ] Piloto com um evento real, do lead ao fechamento financeiro
- [ ] Revisão de segurança sobre o diff acumulado
- [ ] Auditoria completa repetindo o método da inicial, para confirmar 10/10

---

## Matriz de saída

| Categoria | Peso | Auditoria inicial | Alvo | Blocos |
|---|---:|---:|---:|---|
| Back-end e domínio | 14% | 8,5 | 10 | A, C, F |
| **Interface e design** | 14% | 7,5 | 10 | **K, G2** |
| Responsividade | 5% | 8,0 | 10 | K5, H |
| UX e acessibilidade | 8% | 6,5 | 10 | K4, K6 |
| IA — arquitetura | 8% | 8,5 | 10 | B4, B5 |
| IA — operação real | 13% | 3,0 | 10 | B1, B2, B3, B6 |
| **Diferenciais** | 10% | 0,0 | 10 | **L** |
| Segurança e contas | 7% | 5,5 | 10 | E (E1 feito) |
| Qualidade e testes | 7% | 8,0 | 10 | H, I2 |
| Integrações | 6% | 2,5 | 10 | B0, D |
| Infraestrutura | 5% | 6,0 | 10 | I |
| Observabilidade | 3% | 6,5 | 10 | I7, C5 |

O Bloco L entra em 0,0 porque **não existe nada** dessas funcionalidades hoje. É o maior espaço disponível e o que a concorrência não tem.

---

## Verificação

Nenhuma tarefa é aceita sem o teste de comportamento correspondente. Nunca marcar concluído por existir tela ou rota.

```bash
php artisan padraord:doctor
```

```bash
composer quality && npm run quality && npm run test:e2e
```

```bash
php artisan ai:verify && php artisan ai:evaluate
```

### A jornada que prova o produto

1. Cliente recebe o link de briefing e responde pelo celular
2. Reunião de 60+ minutos gravada e enviada, com queda de conexão no meio para forçar a retomada
3. Worker externo prepara e transcreve com falantes preservados entre as partes
4. Extração devolve brief tipado, requisitos por área e programação, cada item citando o trecho de origem
5. Revisar: aceitar parte, recusar parte, editar um item classificado como suposição
6. Abrir qualquer campo e ouvir o instante exato da gravação que o originou
7. O sistema aponta um evento anterior parecido e traz escopo, fornecedores e custo realizado
8. Montar três cenários de escopo e gerar a proposta, com o que não está incluso
9. Autorizar o envio; o sistema envia e registra; o cliente aceita pelo link
10. O aceite cria o projeto e abre a produção numa transação só
11. Produção com escala de equipe, dependências e validação técnica
12. Pós-evento com previsto × realizado por categoria e margem real
13. Repetir os passos 5 a 12 em 390×844

Se essa jornada fecha inteira, nos três breakpoints, com evidência rastreável em cada campo e sem nenhuma ação externa disparada sem autorização humana — o sistema está pronto.
