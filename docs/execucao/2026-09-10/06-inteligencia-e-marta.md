# Inteligência e reaproveitamento da Marta
## Referência inspecionada
Yohann (Harness)/catalogo-operacional/sistema/telegram/MartaAi.php e docs/superpowers/plans/2026-09-08-marta-painel.md.
Marta já interpreta intenção estruturada, mantém contexto, pede seleção de registros reais e prepara ações confirmáveis. Código e relatório são evidências de implementação, não validação automática no domínio RD.

| Capacidade | Decisão |
|---|---|
| Contexto curto e foco em registro | Adaptar ao contexto do caso |
| Perguntas e retomada de fluxo | Adaptar à entrada contextual |
| IDs reais e seleção ambígua | Reutilizar princípio e testes |
| Prévia/confirmar | Usar AssistantActions e CaseContextService existentes |
| Narrativa de proposta | Adaptar ao briefing e orçamento aprovados |
| Telegram e identidade Operon | Não incorporar |
| Credenciais e dados | Nunca copiar |

## Motor
Upload privado → transcrição → falantes/trechos → extração → prévia → confirmação → rascunhos.
Transcrição e extração separadas; não repetir etapa paga concluída. Mensagens e documentos são dados, não instruções. Fato, hipótese, conflito e lacuna distinguíveis. Identificação de falante vale para aquela reunião.

## Áudio longo
Inspecionar formato, tamanho e duração. Preparar partes comprimidas antes da API; não confundir chunk de transporte com segmento de áudio decodificável. Preservar offsets e fontes, remover sobreposição textual sem descartar fala. Retomada verifica usuário, caso, digest e partes recebidas. Não depender de FFmpeg no servidor compartilhado.

## Consumo
Modo efetivo vem do servidor. Reservar custo antes da chamada, reconciliar depois. Deduplicar por contexto/revisão/prompt/modelo. Timeout incerto não gera repetição paga automática. Odoo preserva outbox para validação externa futura.

## Avaliação
Dez briefings sintéticos ou autorizados: fatos críticos, informações desconhecidas, contradições, múltiplos participantes, mudança de escopo, falha e retomada. Toda sugestão aplicada deve ter origem; desconhecido não vira confirmado. Medir correções e custo sem prometer economia.

