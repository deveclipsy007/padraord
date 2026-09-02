# Avaliação do motor de contexto e Briefing

## Objetivo

Validar se o motor reduz redigitação sem transformar hipóteses em fatos. A avaliação é obrigatória antes de ativar uma chave OpenAI com dados reais de cliente.

## Conjunto de avaliação

Usar dez reuniões fictícias ou explicitamente autorizadas. Para cada uma, registrar:

- fatos críticos esperados;
- lacunas que devem continuar como desconhecidas;
- conflitos deliberados, quando houver;
- módulo afetado;
- trechos de origem;
- resultado da revisão humana.

Os áudios reais não entram no repositório. As transcrições de teste devem ser sintéticas ou autorizadas.

## Roteiro

1. Criar um caso isolado e adicionar a transcrição ou o áudio.
2. Confirmar que o original e os segmentos permanecem privados.
3. Revisar resumo, fatos, hipóteses, conflitos e lacunas.
4. Aceitar uma parte do pacote e rejeitar outra parte.
5. Alterar o caso entre a análise e a confirmação para validar o conflito recuperável.
6. Medir o tempo até a prévia, quantidade de correções e custo registrado.
7. Repetir em modo manual e demonstração para confirmar que não há chamada externa.

## Métricas

- fatos críticos corretos;
- fatos incorretos bloqueados ou corrigidos;
- lacunas corretamente marcadas;
- sugestões aceitas, editadas e rejeitadas;
- tempo de transcrição e de extração;
- custo estimado e reportado por reunião;
- tentativas, falhas e reprocessamentos;
- tempo manual economizado, somente após piloto.

Não definir meta de economia antes de obter a linha de base do piloto.

## Critério mínimo

Nenhuma informação desconhecida pode ser aplicada como fato. Toda alteração aceita deve exibir mensagem, documento ou trecho de áudio de origem. Falha de fornecedor, limite ou timeout deve preservar conteúdo e permitir continuar manualmente.
