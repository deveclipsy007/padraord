# Assistência guiada — entrega local de 27/08/2026

## Como testar

1. Entre como administrador e abra **Inteligência artificial**, em `/settings/ai`.
2. Para testar sem custo, escolha **Demonstração** e confirme a senha do usuário. Não é necessário informar uma chave.
3. Abra um caso real de teste → **Briefing**. Cole, por exemplo:

   ```text
   Objetivo: Reunir os parceiros da empresa
   Público: 120 pessoas
   Data: 15/10/2026
   Local: Recife
   Investimento disponível: Ainda a confirmar
   Escopo: Palco e credenciamento
   ```

4. O modo demonstração extrai campos explicitamente rotulados. Texto livre sem rótulos vira uma sugestão de escopo; não representa entendimento por IA.
5. A fila precisa estar rodando para organizar texto em modo demo/OpenAI. Manual salva imediatamente sem fila.
6. Revise sugestões individualmente ou em conjunto. O formulário manual continua disponível.
7. Aprovar o briefing registra uma versão humana; não aprova orçamento, fornecedor ou proposta.
8. No caso, abra **Preparar entrega**. O sistema cria roteiro de cotações, conteúdo inicial e uma tarefa interna, sem inventar custos ou quantidades.

O laboratório `/prototype` continua isolado: usa explicitamente o adaptador demo, mesmo se o workspace tiver OpenAI configurada. Seus campos compartilham a extração determinística do fluxo real.

## Configuração OpenAI

- Somente administradores; a gravação e o teste exigem confirmação da senha.
- Chave criptografada pelo Laravel com `APP_KEY`, armazenada fora da pasta pública. Preserve `APP_KEY` no backup privado.
- Nunca usar senha do ChatGPT como chave. Cadastre a chave de API da OpenAI.
- Origem explicitamente selecionada: ambiente ou administração. Remover desabilita a credencial sem ativar outra automaticamente.
- Defina tarifas de entrada/saída por milhão de tokens em dólares, limite mensal e por chamada. Zero bloqueia chamadas.
- Valide a política de dados antes de permitir envio de contexto real.
- Modelo inicial fixo: `gpt-4o-mini`; endpoint oficial fixo; resposta com JSON Schema estrito e validação local.
- Custos são calculados pelas tarifas informadas, não uma leitura da fatura da OpenAI. Reservas em aberto ou incertas continuam consumindo o limite para evitar repetição de gastos.
- A interface lista tentativas, estado e reserva/consumo. Não guarda chave no frontend, histórico do navegador ou valores antigos do formulário de validação.
- O teste de conexão usa texto fictício e passa pelo mesmo controle de consumo.

## Áudio

- Arquivos privados: MP3, WAV, M4A com duração reconhecida por getID3 (PHP puro).
- Máximo: menor entre 20 MB e os limites efetivos do PHP, reservando espaço para o formulário. Limite inicial de duração: 15 minutos por arquivo.
- Não há gravação pelo microfone, FFmpeg, identificação biométrica persistente ou integração com gravador.
- O arquivo é preservado antes da transcrição. A pessoa confirma a ação paga; processar o texto posteriormente é outra etapa.
- Modelo: `gpt-4o-transcribe-diarize`, `diarized_json`, chunking automático. Falantes são corrigidos por reunião; os horários e o áudio permitem conferir evidências.
- As correções geram revisão e auditoria. Encaminhar a mesma revisão para o briefing não duplica mensagens.
- A transcrição **permanece desabilitada por padrão**. Após validar tempo de resposta, memória, uploads, fila e privacidade na hospedagem, configurar no ambiente privado:

   ```dotenv
   AI_AUDIO_VALIDATED=false
   AI_AUDIO_PRICE_MICROS_PER_MINUTE=0
   ```

  Alterar para `true` somente após validação. A tarifa é em milionésimos de dólar por minuto, conferida na conta/provedor. Não foi preenchida por suposição.
- Áudio usa reserva conservadora por minutos, sem declarar cobrança exata: o ledger mantém esse valor comprometido e identifica o resultado como estimado.
- O cron diário `ai:purge-expired-audio` remove somente arquivos gerenciados cujo prazo de retenção expirou. Texto e auditoria permanecem. A remoção do áudio só é recuperável por backup; arquivos expirados já não são servidos pelo navegador.
- Mudanças na retenção valem para novos uploads, não apagam retroativamente os anteriores.

## Segurança e persistência

- Migrações expansivas 000011–000014, sem remoção dos dados antigos.
- Edição e aprovação do briefing usam revisão esperada e transação. Conflito preserva o formulário e exige comparação.
- Solicitações pagas repetidas reutilizam resultado; índice único no ledger evita dupla reserva da mesma entrada/contexto/modelo/prompt.
- Reserva financeira usa escrita serializada na configuração comum; tentativas em andamento contam no limite mensal.
- Trabalhos de IA têm uma tentativa automática. Falha incerta exige conferência, não reenvio indiscriminado.
- Download de áudio exige sessão ativa e vínculo com o caso. O workspace é colaborativo, não multiempresa.
- Preparação de entrega é um rascunho por modelo local, ligado à revisão aprovada. Alteração posterior marca a preparação como desatualizada; documentos comerciais antigos não são reescritos por esse fluxo.

## Limites e validações ainda necessárias

- Não declarar esta entrega liberada para produção: MariaDB, PHP 8.4 da Hostinger, teste visual/teclado/celular e piloto humano ainda precisam de verificação.
- As chamadas OpenAI foram verificadas com respostas simuladas nos testes, não com dados reais ou cobrança real.
- Os dez exemplos de extração validam o modo determinístico, **não** a qualidade do modelo OpenAI. A avaliação com dez briefings autorizados e revisão humana continua necessária.
- Textos longos são particionados em até três blocos, com resumo acumulado e reserva conjunta antes de qualquer chamada. Conflitos entre blocos deixam o campo para revisão manual. Entradas acima de 90 KB ou contexto consolidado acima de 40 KB são recusados com orientação de divisão; o texto original continua preservado.
- A preparação conectada fornece roteiro e conteúdo no caso; não insere automaticamente itens na tabela de orçamento nem produz proposta comercial final. Quantidades, cotações, taxas e condições exigem revisão nos módulos existentes.
- Não há reconciliador com a fatura do provedor nem reenvio automático de cobranças incertas.
- O cron/fila deve ser validado no ambiente de destino; localhost não comprova a capacidade da hospedagem.

## Fontes técnicas

- https://developers.openai.com/api/docs/guides/structured-outputs
- https://developers.openai.com/api/docs/guides/speech-to-text
- https://developers.openai.com/api/docs/guides/production-best-practices
- https://github.com/JamesHeinrich/getID3
