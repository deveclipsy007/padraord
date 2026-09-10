# Domínio, dados e APIs
## Invariantes
opportunity_id existente identifica o caso. Laravel/Inertia same-origin; banco local SQLite, validação MariaDB. Serviços centralizam regra, não React. Valores em centavos, percentuais em pontos-base, arredondamento determinístico. GET não cria registros de domínio (acesso à proposta pode registrar visualização). Snapshots imutáveis.

## Agregados
Cliente/contato → oportunidade/atividades → contexto/segmentos/briefing → Viabilidade/entregáveis → necessidades/consultas/cotações → orçamento/itens/revisão → documento/versão/link/aceite → produção/validações → pós-evento/aprendizados.
Arquivos privados em storage; download autorizado por vínculo. Registros antigos não recebem condições ou aprovações inferidas.

## Fronteiras
Manter rotas existentes e delegar ao mesmo serviço.
- Contexto: store, process, speakers, preview/confirm.
- Comercial: qualification, commercial-stage, next-action, archive/restore.
- Sourcing: supplier-needs, inquiries, quote revisions, comparison e selection.
- Orçamento: mutate, intake, request review, decision, versions.
- Documentos: draft, review, delivery, external-signature, share/revoke.
- Externo: GET proposta/PDF por token; POST decisão da versão.
- Produção: tasks, preview/confirm, technical validation.
- Pós-evento: occurrences, actuals, review, closure.

## Concorrência e auditoria
Toda edição sensível inclui revisão esperada; conferir em transação. Confirmar duas vezes retorna o mesmo resultado. Auditoria guarda antes/depois seguros, pessoa, motivo, contexto e versão; jamais credenciais ou transcrição integral em logs.
Migrations expansivas: testar banco vazio e atualização com registros anteriores. Não remover campos legados durante esta entrega.

