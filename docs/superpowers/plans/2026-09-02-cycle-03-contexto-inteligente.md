# Ciclo 03 — Motor de contexto, áudio, Briefing e Viabilidade

## Objetivo

Transformar uma reunião em contexto operacional revisável: a equipe adiciona texto, transcrição ou áudio; o sistema preserva a fonte, prepara um rascunho de Briefing e Viabilidade, indica incertezas e só altera registros após confirmação humana.

O ciclo termina com uma Viabilidade Express ou Completa preparada, entregue e aceita, ou encerrada corretamente sem contratação da Gestão. Orçamento, cotações, propostas e produção continuam fora deste ciclo.

## Arquitetura e limites

- Laravel 13 continua como domínio e fila; React 19, TypeScript e Inertia continuam com uma única entrada.
- SQLite é usado localmente; MariaDB é validado pela CI antes de produção.
- Todo áudio, transcrição e evidência ficam no storage privado. Nenhum arquivo é publicado em public_html.
- O fluxo transversal CaseContextEntry será a fonte nova. O fluxo legado do Briefing permanecerá compatível e delegará para ele gradualmente; não haverá apagamento de dados existentes.
- A IA prepara rascunhos. Ela nunca aprova briefing, escopo, estimativa, fornecedor, preço, contratação, entrega ou aceite.
- O modo manual é plenamente utilizável e o modo demonstração não chama provedores externos.
- A integração de transcrição seguirá o modelo diarizado já configurado, gpt-4o-transcribe-diarize, pelo endpoint de transcrição da OpenAI. A seleção de modelo e preços reais continuam sujeitos à aprovação administrativa e à configuração de produção.

## Fluxo final

    Entrada de texto ou áudio
      -> upload retomável privado
      -> preparação no navegador quando necessária
      -> transcrição por partes
      -> consolidação de segmentos e participantes
      -> extração estruturada
      -> prévia consolidada por módulo
      -> revisão humana
      -> confirmação transacional e rascunhos

Uma gravação de até uma hora será tratada como vários segmentos conservadores, com limite inferior a 20 MB por chamada ao provedor e sobreposição curta. Isso evita depender de processos permanentes, FFmpeg ou Node.js na Hostinger. Caso o navegador não possa preparar um arquivo grande com segurança, o original é preservado e a interface oferece alternativa explícita: arquivo compatível menor ou transcrição colada. Não haverá uma promessa falsa de processamento universal em qualquer aparelho.

## Sequência executável

### Tarefa 1 — Consolidar o domínio de contexto e a compatibilidade

Arquivos principais:

- Criar app/Domain/Context/ContextEntryService.php e app/Domain/Context/ContextRevisionGuard.php.
- Criar app/Domain/Context/ContextPreviewService.php.
- Ajustar app/Services/CaseContextService.php e os controllers de contexto.
- Ajustar app/Http/Controllers/BriefingAudioController.php e jobs legados para delegarem ao fluxo novo.
- Criar migration expansiva para relacionar entradas e áudios legados, sem apagar tabelas ou colunas.
- Criar resources/js/types/context.ts e consolidar contratos usados por ContextComposer e Briefing.

Passos:

1. Escrever testes de caracterização para mensagens e áudios legados.
2. Criar CaseContextEntry como ponto único para texto, transcrição e áudio, com fase, digest, estado, retenção, origem e versão.
3. Criar proteção de revisão otimista: uma prévia não poderá aplicar alterações se o caso ou o contexto mudou depois da análise.
4. Adaptar as rotas antigas para produzir os mesmos registros e respostas do fluxo novo.

Testes:

- Uma mensagem legada continua visível após a migração.
- Uma rota de áudio antiga cria ou referencia uma entrada de contexto sem duplicar conteúdo.
- Duas confirmações da mesma prévia retornam o mesmo resultado.
- Alteração concorrente retorna conflito recuperável, sem sobrescrever dados.

Comando de verificação:

    php artisan test --filter=Context

Commit planejado:

    feat: consolidate case context domain

### Tarefa 2 — Áudio longo, upload retomável e participantes corrigíveis

Arquivos principais:

- Criar database migration para context_audio_chunks e para estados de processamento por segmento.
- Ajustar app/Models/ContextAudioAsset.php e criar app/Models/ContextAudioChunk.php.
- Ajustar app/Services/ContextAudio/ResumableAudioUpload.php.
- Criar app/Services/ContextAudio/ChunkedAudioTranscription.php e app/Services/ContextAudio/TranscriptAssembler.php.
- Ajustar app/AI/OpenAiAudioTranscriber.php e jobs PrepareContextAudio e TranscribeContextAudio.
- Criar resources/js/workers/audio-preparation.worker.ts e carregar o worker apenas no compositor de áudio.
- Ajustar resources/js/components/context/ContextUploadComposer.tsx e AudioContext.tsx.

Passos:

1. Registrar cada parte do upload com hash, ordem, tamanho, idempotência e estado; retomar somente partes ausentes.
2. No navegador, preparar áudio em formato comprimido quando suportado e dividir em segmentos menores que o limite de chamada, com sobreposição de dois segundos.
3. Enviar cada segmento privado para transcrição, salvando tentativa, custo, duração e resultado por segmento.
4. Consolidar horários globais e deduplicar somente texto coincidente na janela de sobreposição.
5. Mostrar falantes inicialmente como Pessoa A, Pessoa B e assim por diante. Permitir nomear e corrigir falantes e trechos naquela reunião, sem criar biometria persistente.
6. Exibir reprodução do trecho de origem quando o formato e o navegador permitirem.

Testes:

- Upload interrompido retoma sem criar áudio ou custo duplicado.
- Uma gravação segmentada produz sequência cronológica de segmentos.
- Sobreposição não duplica frase idêntica.
- Falante corrigido é preservado na nova revisão.
- Falha de uma parte deixa o conteúdo original disponível e permite nova tentativa controlada.

Comandos de verificação:

    php artisan test --filter=Audio
    npm run test:ui

Commit planejado:

    feat: process long meeting audio privately

### Tarefa 3 — Extração econômica, evidências e controle de consumo

Arquivos principais:

- Ajustar app/AI/OpenAiContextIntelligenceExtractor.php e app/AI/ContextIntelligenceSchema.php.
- Criar app/Domain/Ai/ContextPayloadBuilder.php e app/Domain/Ai/AiUsageReservationService.php.
- Ajustar app/Models/AiRun.php, as migrations de uso e os jobs de extração.
- Ajustar config/ai.php e a página de configurações existente.
- Criar resources/js/components/context/EvidencePopover.tsx e ContextProcessingStatus.tsx.

Passos:

1. Enviar para a extração somente a transcrição nova, o resumo consolidado aprovado e os campos relevantes à ação; nunca o histórico inteiro sem necessidade.
2. Exigir saída estruturada com fato, hipótese, conflito ou lacuna, valor sugerido, confiança, módulo impactado e referência de fonte.
3. Limitar entrada e saída por ação, reutilizar processamento idêntico por digest e versão de prompt e reservar consumo antes da chamada.
4. Reconciliar uso real depois da resposta e registrar modelo, duração, tokens ou segundos de áudio, custo estimado, custo confirmado, falha e chave idempotente.
5. Preservar o resultado bruto como dado privado de auditoria, sem colocar prompts, chaves ou transcrições em logs.

Testes:

- Dado ausente gera lacuna, não fato.
- Hipótese e conflito nunca são aplicados como informação confirmada.
- Mesma entrada, contexto e versão reutilizam resultado concluído.
- Limite por processamento ou mensal bloqueia antes da chamada paga.
- Manual e demonstração não usam rede externa.

Comandos de verificação:

    php artisan test --filter=Ai
    php artisan test --filter=ContextIntelligence

Commit planejado:

    feat: add evidence based context intelligence

### Tarefa 4 — Revisão consolidada e Briefing progressivo

Arquivos principais:

- Criar app/Domain/Context/ConsolidatedReviewService.php.
- Ajustar rotas de contexto e o controller de confirmação.
- Criar resources/js/components/context/ConsolidatedReview.tsx, SmartField.tsx e ImpactNotice.tsx.
- Ajustar resources/js/pages/Briefing.tsx e o hub da oportunidade.
- Criar resources/js/types/workspace.ts para ContextChangeSet, EvidenceReference, ModuleChange e ImpactReference.

Passos:

1. Agrupar a prévia em dados do caso, Briefing e Viabilidade; cada alteração mostra valor atual, sugestão, motivo, fonte e impacto.
2. Permitir aceitar o pacote, excluir módulos ou revisar campos individuais; a confirmação usa uma única transação e a revisão esperada.
3. Redesenhar Briefing em três camadas: contexto original, entendimento organizado e revisão.
4. Na primeira dobra, mostrar resumo e no máximo três perguntas prioritárias. Campos completos, histórico e fontes serão expansíveis.
5. Exigir aprovação humana explícita para congelar uma versão de Briefing; edição manual seguirá disponível em qualquer falha de IA.

Testes:

- Rejeitar uma sugestão não apaga as demais sugestões.
- Toda alteração aceita mostra fonte humana, mensagem ou trecho de áudio.
- Aprovação cria snapshot e mudança posterior exige nova revisão.
- Interface preserva rascunho após refresh ou erro.

Comandos de verificação:

    php artisan test --filter=Briefing
    npm run typecheck
    npm run test:e2e -- --grep "briefing"

Commit planejado:

    feat: deliver reviewable intelligent briefing

### Tarefa 5 — Viabilidade Express e Completa como ciclo próprio

Arquivos principais:

- Criar ou expandir migration para viability_projects, viability_deliverables e evidências.
- Ajustar app/Models/ViabilityProject.php, app/Services/ViabilityWorkspaceService.php e app/Http/Controllers/FeasibilityController.php.
- Criar app/Domain/Viability/ViabilityLifecycleService.php.
- Ajustar resources/js/pages/Feasibility.tsx e a rail contextual do caso.

Estados do ciclo:

    draft
    in_development
    ready_for_delivery
    delivered
    accepted
    closed_without_management
    cancelled

Passos:

1. Separar modalidade Express e Completa, conceito, premissas, estimativa preliminar, cronograma e entregáveis.
2. Marcar maquete 3D e planta baixa como entregáveis aplicáveis, não como promessas automáticas.
3. Exigir entregáveis obrigatórios e evidência antes de marcar pronto para entrega.
4. Registrar entrega, aceite, responsável, data e snapshot. Alteração posterior abre nova revisão.
5. Permitir encerrar validamente sem Gestão, preservando relacionamento e documentos. Isso não pode virar perda comercial.
6. Mostrar ligações com Briefing, fornecedores futuros e orçamento futuro, sem construir o orçamento agora.

Testes:

- Express e Completo têm entregáveis corretos.
- Entrega bloqueia sem entregáveis obrigatórios.
- Aceite exige evidência ou observação.
- Encerramento sem Gestão é sucesso válido e não muda a oportunidade para perdida.
- Estimativa de Viabilidade não se mistura a orçamento de execução.

Comandos de verificação:

    php artisan test --filter=Viability
    npm run test:e2e -- --grep "viability"

Commit planejado:

    feat: complete viability workspace lifecycle

### Tarefa 6 — Avaliação, acessibilidade e gate de release

Arquivos principais:

- Criar tests/Fixtures/briefings ou database/seeders/AuthorizedBriefingEvaluationSeeder.php.
- Criar docs/operations/ai-briefing-evaluation.md.
- Ajustar testes Playwright de desktop, tablet e celular.
- Atualizar docs/visao-produto-auditoria-roadmap-2026-09-01.md com evidências do ciclo.

Passos:

1. Criar conjunto controlado de dez briefings fictícios ou autorizados, com gabarito de fatos críticos e lacunas esperadas.
2. Medir correções necessárias, tempo até rascunho, latência, custo por reunião e taxa de informação desconhecida corretamente sinalizada.
3. Garantir interface utilizável por teclado, sem blur, sem imagens decorativas e com prefers-reduced-motion.
4. Validar que upload, transcrição, erro, reprocessamento, revisão e confirmação funcionam em 1440x900, 1024x768 e 390x844.
5. Rodar a matriz SQLite local, MariaDB na CI, release Hostinger e verificador do pacote. A ativação real da OpenAI, SMTP, Odoo e Hostinger só volta no Ciclo 6.

Comandos finais:

    composer quality
    npm run quality
    npm run test:e2e
    scripts/ci/test-mariadb.sh
    scripts/deploy/build-hostinger-release.sh
    scripts/deploy/verify-hostinger-release.sh

Commit planejado:

    test: validate cycle three context to viability journey

## Critérios de aceite

- Um texto ou áudio cria contexto preservado, revisável e acessível apenas a usuários vinculados ao caso.
- Áudio longo é tratado em segmentos privados, sem uma chamada grande única ao provedor.
- Cada informação sugerida tem fonte, classificação e módulos impactados.
- Nenhuma sugestão é gravada sem confirmação humana.
- Uma falha de IA ou áudio não remove texto, arquivo ou edição manual.
- O Briefing mostra entendimento, pendências e evidências sem exigir formulário longo desde o início.
- Viabilidade aparece como rota real e percorre desenvolvimento, entrega, aceite ou encerramento sem Gestão.
- Nenhum valor estimado vira preço, orçamento ou aprovação.
- Processamentos repetidos, confirmações duplicadas e uploads retomados não duplicam registros nem custos.
- PHPUnit, Vitest, TypeScript, Vite, Playwright, MariaDB na CI e verificação Hostinger passam antes de declarar o ciclo pronto.

## Fora do Ciclo 03

- Cadastro profundo de fornecedores, cotações e orçamento versionado.
- Propostas, contratos, PDF e SMTP.
- Produção, operação do evento e pós-evento.
- Exportação efetiva de custos ao Odoo.
- Ativação de credencial OpenAI real em dados de cliente.
- Publicação na Hostinger.

Esses itens dependem do contexto confiável produzido aqui e permanecem nos Ciclos 04, 05 e 06.
