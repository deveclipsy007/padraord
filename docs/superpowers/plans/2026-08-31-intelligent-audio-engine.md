# Motor Inteligente de Áudio e Contexto — Plano de Implementação

> Execução nesta sessão: usar a skill `executing-plans` em modo inline. Não usar multiagentes neste projeto.

**Objetivo:** permitir que uma reunião de até 60 minutos entre uma única vez no sistema e produza uma transcrição com falantes, fatos rastreáveis, pendências e rascunhos para os módulos do caso, sempre sujeitos à revisão humana, com custo interno auditável e exportável ao Odoo.

**Arquitetura:** Laravel/PHP continua como orquestrador e fonte de verdade. O áudio fica privado, passa por preparação quando necessário, é transcrito pela OpenAI, organizado por um modelo de texto com saída estruturada e convertido em uma prévia consolidada. Somente a confirmação humana grava rascunhos nos módulos. Custos são registrados em um livro-razão interno e enviados ao Odoo por uma outbox idempotente.

**Stack preservada:** PHP 8.4, Laravel 13, React 19/Inertia 3, fila em banco, SQLite local, MariaDB em produção e Hostinger compartilhada. OpenAI atrás de interfaces internas. Integração Odoo via adaptador versionado.

---

## 1. Decisões fechadas

### O fluxo do usuário

Uma ação principal: **Enviar e organizar**.

```text
Upload privado
  → inspeção e deduplicação
  → normalização, somente se necessária
  → transcrição e separação de falantes
  → extração estruturada por módulo
  → prévia consolidada com evidências
  → confirmação humana
  → rascunhos aplicados em transação
  → custos fechados
  → lançamento enfileirado para o Odoo
```

O usuário não terá de clicar separadamente em “transcrever”, “analisar” e “encaminhar”. A interface mostrará o andamento e permitirá retomar após atualizar a página.

### Limite real de uma hora

A API de transcrição aceita arquivos de até 25 MB. Portanto:

- até 24 MB e formato compatível: envio direto;
- acima de 24 MB: preparação antes da OpenAI;
- o gravador nativo futuro usará áudio mono comprimido para manter uma hora abaixo do limite;
- arquivos existentes grandes passarão por um `MediaPreparationProvider`;
- a aplicação não dependerá de FFmpeg instalado na Hostinger;
- o adaptador padrão da Hostinger somente aceita/pass-through arquivos já adequados;
- um adaptador externo mínimo poderá normalizar para M4A/Opus mono e, somente se ainda necessário, dividir com pequena sobreposição;
- ao dividir, o sistema não fingirá que “Pessoa A” em dois fragmentos é certamente a mesma pessoa. A associação será revisável.

O processamento de negócio continua em PHP. O preparador externo, se necessário, executa apenas transformação de mídia.

### Modelos

- transcrição e diarização: `gpt-4o-transcribe-diarize`;
- extração: começar com um modelo econômico com Structured Outputs, configurável por ambiente;
- candidato inicial para avaliação: `gpt-5.6-luna`;
- o `gpt-4o-mini` existente permanece como fallback de compatibilidade até os testes comparativos;
- não haverá escalada automática para modelo mais caro;
- o modelo e a versão do prompt serão fixados por execução para preservar reprodutibilidade.

### Autoridade da IA

A IA poderá:

- transcrever;
- separar falantes;
- extrair fatos com fonte;
- marcar hipóteses, conflitos e ausências;
- preparar perguntas, tarefas e itens sem preço;
- sugerir módulos impactados.

A IA não poderá:

- inventar fornecedor, preço, quantidade, margem ou decisão;
- aprovar briefing, Viabilidade, orçamento ou documento;
- enviar proposta;
- registrar contratação;
- apagar registros;
- lançar custo diretamente no Odoo sem passar pela outbox e pelas configurações validadas.

### Custo e Odoo

O sistema registrará três valores distintos:

1. **estimado:** antes da chamada, usado para o limite;
2. **reportado:** calculado com os dados de uso devolvidos pelo provedor, quando disponíveis;
3. **reconciliado:** valor confirmado posteriormente, quando houver fonte financeira autorizada.

O valor manual por minuto existente continuará apenas como estimador temporário. Não será apresentado como fatura real da OpenAI.

Internamente, cada etapa terá uma linha de custo. Para o Odoo, o padrão será um lançamento agregado por processamento/reunião, com detalhamento no memo. O valor original fica em USD. Se o Odoo exigir BRL, o envio só acontece com taxa, data e fonte de câmbio configuradas; o sistema não inventa cotação.

---

## 2. Contratos do motor

### Estados do processamento

```php
<?php

enum ContextProcessingStatus: string
{
    case Uploading = 'uploading';
    case Inspecting = 'inspecting';
    case Preparing = 'preparing';
    case Queued = 'queued';
    case Transcribing = 'transcribing';
    case Extracting = 'extracting';
    case ReviewReady = 'review_ready';
    case Applying = 'applying';
    case Completed = 'completed';
    case Failed = 'failed';
    case Uncertain = 'uncertain';
}
```

### Saída estruturada da reunião

```json
{
  "summary": "string",
  "participants": [{"speaker_id": "spk_0", "display_name": null}],
  "facts": [{
    "key": "event.date",
    "value": "2026-11-20",
    "classification": "fact",
    "evidence_segment_ids": [42]
  }],
  "decisions": [],
  "open_questions": [],
  "risks": [],
  "constraints": [],
  "budget_mentions": [{
    "value": 120000,
    "currency": "BRL",
    "meaning": "investment_ceiling",
    "evidence_segment_ids": [75]
  }],
  "supplier_mentions": [],
  "action_items": [],
  "module_changes": {
    "case": [],
    "briefing": [],
    "viability": [],
    "budget": [],
    "documents": [],
    "production": [],
    "post_event": []
  }
}
```

`classification` aceitará apenas `fact`, `hypothesis`, `conflict` ou `unknown`. Um número mencionado como intenção não vira cotação, preço aprovado ou item contratado.

### Identidade e deduplicação

Cada operação paga terá uma chave única derivada de:

```text
sha256(
  audio_digest
  + provider
  + model
  + prompt_schema_version
  + context_revision
  + operation
)
```

Repetir a mesma solicitação concluída retorna o resultado anterior. Estado `uncertain` exige decisão manual ou reconciliação antes de uma nova chamada paga.

---

## 3. Estrutura de dados

### Tabelas novas

#### `audio_upload_sessions`

- UUID público não sequencial;
- oportunidade e usuário;
- nome, MIME e tamanho declarados;
- tamanho/chunks recebidos;
- digest esperado e calculado;
- caminho temporário privado;
- expiração e estado.

#### `context_audio_assets`

- `case_context_entry_id` único;
- caminhos original e preparado;
- MIME, bytes e duração de ambos;
- digest SHA-256;
- estado de preparação/transcrição;
- modelo e request ID do provedor;
- revisão da transcrição;
- erro seguro, sem conteúdo sensível.

#### `ai_cost_entries`

- caso, entrada de contexto e `ai_run`;
- operação: preparação, transcrição, extração ou redução;
- provedor, modelo e request ID;
- quantidade: segundos, tokens de entrada, cache e saída;
- `estimated_amount_micros`;
- `reported_amount_micros`;
- `reconciled_amount_micros`;
- moeda e tabela de preços/versionamento;
- estado `reserved`, `reported`, `reconciled`, `released` ou `uncertain`;
- chave idempotente única.

#### `odoo_cost_outbox`

- `ai_cost_batch_id`/processamento agregado;
- chave idempotente única;
- payload versionado;
- estado, tentativas e próxima tentativa;
- ID externo retornado;
- erro sanitizado;
- data da taxa de câmbio e origem, se aplicável.

### Expansões

- `case_context_segments`: sequência, chunk de origem, ID do segmento do provedor e índice único por entrada/sequência;
- `case_context_entries`: revisão do caso usada na análise, schema do pacote e estado operacional;
- `assistant_previews`: módulos incluídos/excluídos, revisões esperadas e resultado idempotente;
- `ai_runs`: operação, schema, contexto, modelo fixado e IDs correlacionados.

### Compatibilidade

`briefing_audio` não será apagada neste ciclo. Um adaptador fará as rotas antigas delegarem ao novo pipeline. Uma migração expansiva vinculará registros antigos quando a origem puder ser provada; não deduzirá conteúdo nem aprovações.

---

## 4. Endpoints

```text
POST   /opportunities/{opportunity}/context/audio/uploads
PUT    /opportunities/{opportunity}/context/audio/uploads/{session}/chunks/{index}
POST   /opportunities/{opportunity}/context/audio/uploads/{session}/complete
GET    /opportunities/{opportunity}/context/{entry}
POST   /opportunities/{opportunity}/context/{entry}/process
PATCH  /opportunities/{opportunity}/context/{entry}/speakers
POST   /opportunities/{opportunity}/context/{entry}/preview/{preview}/confirm
POST   /opportunities/{opportunity}/context/{entry}/retry
```

O upload usará partes pequenas e retomáveis. A conclusão valida contagem, tamanho e digest antes de mover o arquivo temporário para o armazenamento privado.

As rotas antigas de áudio do Briefing permanecerão funcionais e chamarão os mesmos serviços de domínio.

---

## 5. Plano executável

### Tarefa 1 — congelar comportamento atual e criar fixtures de avaliação

**Arquivos:**

- Criar: `tests/Fixtures/Audio/README.md`
- Criar: `tests/Fixtures/Audio/meeting-manifest.json`
- Modificar: `tests/Feature/BriefingAudioTest.php`
- Criar: `tests/Feature/ContextAudioCompatibilityTest.php`

**Passos:**

1. Escrever testes que provem upload privado, deduplicação, autorização, fila e correção de falante atuais.
2. Criar manifesto de 10–20 áudios fictícios/autorizados, sem versionar arquivos sensíveis.
3. Registrar duração, formato, quantidade de falantes, ruído e fatos críticos esperados.
4. Rodar:

```bash
php artisan test --filter=BriefingAudioTest
php artisan test --filter=ContextAudioCompatibilityTest
```

**Aceite:** o comportamento legado está protegido antes da migração.

### Tarefa 2 — criar schema expansivo e modelos

**Arquivos:**

- Criar: `database/migrations/2026_09_01_000001_create_context_audio_pipeline.php`
- Criar: `app/Models/AudioUploadSession.php`
- Criar: `app/Models/ContextAudioAsset.php`
- Criar: `app/Models/AiCostEntry.php`
- Criar: `app/Models/OdooCostOutbox.php`
- Modificar: `app/Models/CaseContextEntry.php`
- Criar: `app/Models/CaseContextSegment.php`
- Criar: `app/Enums/ContextProcessingStatus.php`
- Criar: `tests/Feature/ContextAudioSchemaTest.php`

**Passos:**

1. Escrever o teste falhando para chaves, índices e relações.
2. Criar migrations apenas aditivas, compatíveis com SQLite e MariaDB.
3. Adicionar casts e relações sem remover `briefing_audio`.
4. Testar migration fresh e upgrade sobre cópia do SQLite.

```bash
php artisan test --filter=ContextAudioSchemaTest
php artisan migrate:fresh --seed --env=testing
```

**Aceite:** o banco suporta áudio, custo e outbox sem alterar dados antigos.

### Tarefa 3 — upload privado, retomável e idempotente

**Arquivos:**

- Criar: `app/Http/Controllers/ContextAudioUploadController.php`
- Criar: `app/Http/Requests/StartContextAudioUploadRequest.php`
- Criar: `app/Http/Requests/UploadContextAudioChunkRequest.php`
- Criar: `app/Services/ContextAudio/ResumableAudioUpload.php`
- Criar: `app/Policies/CaseContextEntryPolicy.php`
- Modificar: `routes/web.php`
- Modificar: `config/filesystems.php`
- Criar: `tests/Feature/ContextAudioUploadTest.php`

**Passos:**

1. Testar início, retomada, ordem de chunks, digest inválido, duplicação e acesso horizontal.
2. Armazenar chunks fora de `public_html` com nome interno aleatório.
3. Verificar MIME real, extensão permitida, tamanho, duração e SHA-256.
4. Montar o arquivo somente depois de todos os chunks, usando lock.
5. Expirar uploads incompletos via scheduler.

```bash
php artisan test --filter=ContextAudioUploadTest
```

**Aceite:** atualizar a página ou repetir o último chunk não perde nem duplica o áudio.

### Tarefa 4 — preparação de mídia para até 60 minutos

**Arquivos:**

- Criar: `app/Contracts/MediaPreparationProvider.php`
- Criar: `app/Media/PassThroughMediaPreparationProvider.php`
- Criar: `app/Media/RemoteMediaPreparationProvider.php`
- Criar: `app/Data/PreparedMedia.php`
- Criar: `app/Jobs/PrepareContextAudio.php`
- Modificar: `app/AI/AudioInspector.php`
- Modificar: `config/ai.php`
- Modificar: `config/services.php`
- Criar: `tests/Unit/MediaPreparationProviderTest.php`
- Criar: `tests/Feature/PrepareContextAudioJobTest.php`

**Passos:**

1. Alterar o limite funcional de 15 para 60 minutos, mantendo 24 MB como limite direto.
2. O pass-through aceita somente mídia já compatível.
3. O adaptador remoto usa URL fixa por configuração, autenticação assinada e callback/consulta autenticada.
4. Normalizar para mono comprimido visando menos de 24 MB.
5. Se divisão for inevitável, persistir offsets e sobreposição para deduplicação posterior.
6. Se o preparador não estiver configurado, explicar como converter; nunca tentar FFmpeg escondido na Hostinger.

```bash
php artisan test --filter=MediaPreparationProviderTest
php artisan test --filter=PrepareContextAudioJobTest
```

**Aceite:** um arquivo de uma hora compatível segue direto; um arquivo grande segue ao preparador ou recebe bloqueio explícito e recuperável.

### Tarefa 5 — extrair o cliente OpenAI de transcrição

**Arquivos:**

- Criar: `app/Contracts/AudioTranscriber.php`
- Criar: `app/AI/OpenAiAudioTranscriber.php`
- Criar: `app/Data/TranscriptionResult.php`
- Criar: `app/Data/TranscriptionSegment.php`
- Modificar: `app/Jobs/TranscribeBriefing.php`
- Criar: `app/Jobs/TranscribeContextAudio.php`
- Criar: `tests/Unit/OpenAiAudioTranscriberTest.php`
- Criar: `tests/Feature/TranscribeContextAudioJobTest.php`

**Passos:**

1. Escrever testes HTTP fake para `diarized_json`, falha de autenticação, limite, timeout e resposta malformada.
2. Chamar `gpt-4o-transcribe-diarize` com `chunking_strategy=auto` para áudio maior que 30 segundos.
3. Validar todos os segmentos antes de persistir.
4. Salvar transcrição original imutável e segmentos ordenados.
5. Não repetir automaticamente resposta incerta.
6. Fazer o job legado delegar ao novo transcriber.

```bash
php artisan test --filter=OpenAiAudioTranscriberTest
php artisan test --filter=TranscribeContextAudioJobTest
```

**Aceite:** transcrição e diarização são substituíveis, testáveis e não dependem do controller.

### Tarefa 6 — orquestrar o pipeline com retomada

**Arquivos:**

- Criar: `app/Services/ContextAudio/ContextAudioPipeline.php`
- Criar: `app/Jobs/ExtractContextIntelligence.php`
- Criar: `app/Events/ContextProcessingAdvanced.php`
- Modificar: `routes/console.php`
- Modificar: `config/queue.php`
- Criar: `tests/Feature/ContextAudioPipelineTest.php`

**Passos:**

1. Testar a máquina de estados e transições proibidas.
2. Cada job lê o estado atual e encerra sem efeito quando já concluído.
3. Separar filas `media`, `ai` e `integrations`.
4. Configurar locks `withoutOverlapping` por entrada.
5. Manter um job pesado por vez no ambiente Hostinger.
6. Guardar heartbeat e último avanço para detectar trabalho abandonado.

```bash
php artisan test --filter=ContextAudioPipelineTest
php artisan schedule:list
```

**Aceite:** falhar na extração não exige retranscrever; retomar não duplica cobrança.

### Tarefa 7 — extração estruturada com fontes

**Arquivos:**

- Criar: `app/Contracts/ContextIntelligenceExtractor.php`
- Criar: `app/AI/OpenAiContextIntelligenceExtractor.php`
- Criar: `app/AI/DemoContextIntelligenceExtractor.php`
- Criar: `app/AI/ManualContextIntelligenceExtractor.php`
- Criar: `app/Schemas/context-intelligence-v1.json`
- Criar: `app/Data/ContextIntelligence.php`
- Criar: `app/Services/ContextAudio/CompactCaseContext.php`
- Modificar: `app/Providers/AppServiceProvider.php`
- Criar: `tests/Unit/ContextIntelligenceSchemaTest.php`
- Criar: `tests/Feature/ExtractContextIntelligenceJobTest.php`

**Passos:**

1. Testar fatos, hipóteses, conflitos, campos ausentes e referências inválidas.
2. Enviar transcrição por IDs de segmento e apenas o contexto atual necessário.
3. Usar Structured Outputs com schema fechado.
4. Rejeitar qualquer evidência que aponte para segmento inexistente.
5. Para entradas que excedam o limite interno, dividir deterministicamente por segmentos e reduzir sem perder IDs.
6. Não reenviar o áudio para esta etapa.

```bash
php artisan test --filter=ContextIntelligenceSchemaTest
php artisan test --filter=ExtractContextIntelligenceJobTest
```

**Aceite:** toda sugestão verificável aponta para um trecho; desconhecido permanece pendência.

### Tarefa 8 — revisão consolidada e aplicação transacional

**Arquivos:**

- Modificar: `app/Services/CaseContextService.php`
- Criar: `app/Services/ContextAudio/ContextReviewBuilder.php`
- Criar: `app/Services/ContextAudio/ConfirmedContextChanges.php`
- Criar: `app/Http/Controllers/ContextReviewController.php`
- Modificar: `routes/web.php`
- Criar: `tests/Feature/ContextReviewConfirmationTest.php`

**Passos:**

1. Construir prévia agrupada por módulo com atual, sugerido, classificação, fonte e impacto.
2. Permitir excluir módulo ou alteração.
3. Guardar a revisão esperada de cada agregado.
4. Na confirmação, recarregar permissões e revisões dentro da transação.
5. Em conflito, não aplicar nada; apresentar nova comparação.
6. Confirmação repetida retorna o mesmo resultado.

```bash
php artisan test --filter=ContextReviewConfirmationTest
```

**Aceite:** nenhuma informação entra silenciosamente e nenhuma confirmação parcial deixa o caso incoerente.

### Tarefa 9 — livro-razão de custo e limites concorrentes

**Arquivos:**

- Criar: `app/Services/AiCostLedger.php`
- Criar: `app/Services/AiPricingCatalog.php`
- Criar: `config/ai_pricing.php`
- Modificar: `app/AI/MeteredAiProvider.php`
- Modificar: `app/Jobs/TranscribeContextAudio.php`
- Modificar: `app/Jobs/ExtractContextIntelligence.php`
- Criar: `tests/Feature/AiCostLedgerTest.php`
- Criar: `tests/Feature/AiConcurrentReservationTest.php`

**Passos:**

1. Reservar orçamento em transação com lock antes de qualquer chamada paga.
2. Reconciliar com uso reportado quando o endpoint oferecer métricas utilizáveis.
3. Liberar reserva em falha conhecida sem cobrança.
4. Marcar timeout ambíguo como `uncertain`.
5. Versionar preços por modelo e data; não fazer scraping em runtime.
6. Exibir estimativa como estimativa.

```bash
php artisan test --filter=AiCostLedgerTest
php artisan test --filter=AiConcurrentReservationTest
```

**Aceite:** duas chamadas simultâneas não ultrapassam o limite e repetição não duplica custo.

### Tarefa 10 — integração Odoo por outbox

**Arquivos:**

- Criar: `app/Contracts/OdooCostExporter.php`
- Criar: `app/Integrations/Odoo/Json2OdooCostExporter.php`
- Criar: `app/Integrations/Odoo/NullOdooCostExporter.php`
- Criar: `app/Jobs/ExportAiCostToOdoo.php`
- Criar: `app/Services/OdooCostOutboxService.php`
- Criar: `config/odoo.php`
- Modificar: `.env.example`
- Criar: `tests/Unit/Json2OdooCostExporterTest.php`
- Criar: `tests/Feature/OdooCostOutboxTest.php`

**Passos:**

1. Implementar primeiro modo `disabled/demo`.
2. Criar um lançamento agregado por processamento com linhas internas detalhadas.
3. Usar chave idempotente como referência externa.
4. Enviar por `/json/2/<model>/<method>` quando o Odoo for 19+ e a conta possuir API externa.
5. Usar usuário bot com permissão mínima e chave rotacionável.
6. Não bloquear o resultado da IA quando o Odoo estiver fora do ar.
7. Repetir com backoff; parar em erro de configuração/autorização.

```bash
php artisan test --filter=Json2OdooCostExporterTest
php artisan test --filter=OdooCostOutboxTest
```

**Dependências que a Padrão RD precisa fornecer antes do modo real:** versão e plano do Odoo, URL, database, empresa, moeda, modelo/diário/produto/conta analítica de destino, regra de conversão USD→BRL e usuário bot.

**Aceite:** Odoo indisponível gera pendência visível, nunca perda ou custo duplicado.

### Tarefa 11 — experiência “Enviar e organizar”

**Arquivos:**

- Criar: `resources/js/components/context/ContextUploadComposer.tsx`
- Criar: `resources/js/components/context/ContextProcessingTimeline.tsx`
- Criar: `resources/js/components/context/SpeakerReview.tsx`
- Criar: `resources/js/components/context/ConsolidatedContextReview.tsx`
- Modificar: `resources/js/components/AudioContext.tsx`
- Modificar: `resources/js/pages/OpportunityShow.tsx`
- Modificar: `resources/js/pages/Briefing.tsx`
- Modificar: `resources/js/types.ts`
- Criar: `resources/js/components/context/ContextUploadComposer.test.tsx`

**Passos:**

1. Exibir preflight: formato, duração, tamanho, necessidade de preparação e custo máximo estimado.
2. Implementar upload retomável com progresso.
3. Mostrar estados: enviando, preparando, na fila, transcrevendo, organizando, pronto para revisar, falha e incerto.
4. Permitir renomear falantes e reproduzir o trecho no timestamp.
5. Mostrar prévia por módulo, com fontes e ação única de confirmação.
6. Manter texto/transcrição original acessível mesmo em falha.

```bash
npm run test:ui -- ContextUploadComposer
npm run typecheck
```

**Aceite:** uma pessoa sem conhecer os módulos consegue enviar, acompanhar, revisar e aplicar o resultado.

### Tarefa 12 — segurança, retenção e observabilidade

**Arquivos:**

- Criar: `app/Console/Commands/PurgeExpiredContextAudio.php`
- Modificar: `routes/console.php`
- Modificar: `app/Http/Controllers/AiSettingsController.php`
- Modificar: `resources/js/pages/AiSettings.tsx`
- Criar: `tests/Feature/ContextAudioRetentionTest.php`
- Criar: `tests/Feature/ContextAudioAuthorizationTest.php`

**Passos:**

1. Exigir política de dados aprovada antes de áudio real.
2. Configurar retenção separada para original, preparado e transcrição.
3. Auditar download, correção de falante, confirmação, remoção e exportação de custo.
4. Nunca logar chave, conteúdo integral, URL assinada ou payload sensível.
5. Referências de voz conhecidas, se adotadas depois, serão opt-in, por reunião, e não autenticação biométrica.
6. Criar painel de falhas e custos sem conteúdo sensível.

```bash
php artisan test --filter=ContextAudioRetentionTest
php artisan test --filter=ContextAudioAuthorizationTest
```

**Aceite:** arquivo e transcrição nunca ficam públicos; retenção é verificável e auditável.

### Tarefa 13 — avaliação, Hostinger gate e liberação

**Arquivos:**

- Criar: `scripts/evals/run-context-audio-eval.php`
- Criar: `docs/runbooks/context-audio-evaluation.md`
- Criar: `docs/runbooks/context-audio-hostinger.md`
- Modificar: `scripts/deploy/build-hostinger-release.sh`
- Modificar: `tests/Feature/HostingerDeploymentTest.php`

**Passos:**

1. Avaliar áudio limpo, telefone, ruído, sobreposição, sotaques e 2–5 pessoas.
2. Medir: tempo, custo estimado/reportado, fatos críticos corretos, atribuição de falante, falsos fatos e correções humanas.
3. Comparar o modelo de extração candidato com `gpt-4o-mini` usando os mesmos casos.
4. Rodar soak tests de 15, 30 e 60 minutos.
5. Medir limites reais do PHP CLI/cron da Hostinger.
6. Se a chamada longa não for estável, ativar o worker externo apenas para mídia/transcrição e devolver resultado por callback assinado; não aumentar timeouts indefinidamente.
7. Testar SQLite e MariaDB.
8. Confirmar que nenhum áudio, transcrição ou chave entra em `public_html` ou no release.

```bash
php artisan test
npm run test:ui
npm run typecheck
npm run build
bash scripts/deploy/build-hostinger-release.sh
```

**Porta de liberação:** áudio de 60 minutos autorizado processado de ponta a ponta, sem duplicação, com revisão humana, custo interno fechado e outbox Odoo testada em modo sandbox/demo.

---

## 6. Scheduler e hospedagem

O cron continuará transitório, mas com filas separadas e baixa concorrência:

```text
media        → no máximo 1 job pesado
ai           → no máximo 1 transcrição/extração paga por vez no piloto
integrations → lotes curtos e idempotentes
default      → trabalho comum
```

Não se deve simplesmente aumentar `--timeout=180`. Primeiro será medido o comportamento com áudio real de 60 minutos. A implantação escolherá entre:

1. **Hostinger validada:** PHP CLI sustenta a chamada e o job recebe timeout coerente;
2. **worker externo mínimo:** transcrição/preparação roda fora, Laravel acompanha por estado e callback assinado.

A opção 2 não altera o frontend, o domínio ou os dados: é apenas um executor do mesmo contrato.

---

## 7. Economia de tokens e processamento

- áudio é transcrito uma única vez;
- correção de nome/falante não retranscreve;
- extração recebe texto + IDs, não áudio;
- o contexto do caso é compacto e versionado;
- não há chamada ao abrir página, digitar ou atualizar;
- resultado idêntico é reutilizado por digest;
- Structured Outputs reduz retrabalho de parsing;
- entradas realmente grandes são divididas por segmentos com redução determinística;
- cache de prompt é bônus, não a principal economia;
- não há retry pago cego;
- não há escalada automática de modelo;
- custos de preparação, transcrição e extração ficam separados para identificar o gargalo real.

---

## 8. Critérios finais de aceite

- processar reunião de até 60 minutos em formato suportado;
- tratar arquivos maiores que 24 MB de forma explícita e recuperável;
- identificar falantes como rótulos revisáveis, sem prometer identidade biométrica;
- toda informação sugerida possui trecho de origem;
- hipóteses e conflitos não viram fatos;
- repetir upload/processamento não duplica arquivo, chamada, rascunho ou custo;
- confirmar uma prévia desatualizada retorna conflito;
- indisponibilidade da OpenAI preserva áudio e operação manual;
- indisponibilidade do Odoo não bloqueia a revisão;
- custo distingue estimativa, reporte e reconciliação;
- Odoo recebe no máximo um lançamento por chave idempotente;
- chaves, áudios e transcrições permanecem fora de `public_html`;
- fluxo funciona em desktop e celular;
- testes passam em SQLite e MariaDB;
- o piloto mede qualidade e custo antes de prometer automação total.

---

## 9. Referências técnicas oficiais

- OpenAI Speech-to-text: <https://developers.openai.com/api/docs/guides/speech-to-text>
- OpenAI `gpt-4o-transcribe-diarize`: <https://developers.openai.com/api/docs/models/gpt-4o-transcribe-diarize>
- OpenAI `gpt-5.6-luna`: <https://developers.openai.com/api/docs/models/gpt-5.6-luna>
- OpenAI Prompt Caching: <https://developers.openai.com/api/docs/guides/prompt-caching>
- Odoo 19 External JSON-2 API: <https://www.odoo.com/documentation/19.0/developer/reference/external_api.html>
