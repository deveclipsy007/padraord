# Operação local e publicação futura
## Preservação
Antes de migration no banco de trabalho, fazer backup consistente SQLite pela API de backup e inventariar documentos privados. Nunca migrate:fresh no banco de desenvolvimento. Testes usam base descartável.
Restaurar primeiro em cópia e conferir contagens antes de substituir banco ativo.

## Operação
Servidor Laravel em localhost; assets gerados com npm run build. Fila precisa de worker/scheduler local para áudio e extração. Modo manual mantém entrada e edição. /up é liveness; /api/v1/health protegido informa prontidão sem segredos.
Se uma tarefa falhar, consultar estado da execução e request ID; não reenviar áudio pago inteiro por padrão.

## Hostinger
Preservar docs/hostinger-deploy.md e docs/operations/hostinger-capabilities.md como runbooks técnicos existentes.
Layout: app_core privado, public_html/index.php e build público; sem index.html, Node de produção, .env, SQLite ou documentos públicos.
Checklist futuro: acesso real, PHP web/CLI 8.4, MariaDB, cron, SSL, SMTP, backup, SFTP, maintenance, migrate --force, caches, smoke e rollback.
Não efetuar publicação nesta execução.

## Níveis de entrega
Local concluído exige as três metas verificadas.
Integrações reais exigem chamadas autorizadas e evidências OpenAI/SMTP/Odoo.
Produção ativa exige servidor real, segurança, cron e recuperação verificados.

