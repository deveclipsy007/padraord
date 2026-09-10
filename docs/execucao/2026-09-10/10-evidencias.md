# Evidências de execução
## 10/09/2026 — início
- Meta 1 criada no mecanismo de metas da sessão.
- Alterações locais do Ciclo 04 preservadas.
- Dossiê inicial criado com tarefas M1-01 a M3-05.
- Baseline histórico dirigido: 6/7 testes comerciais; assinatura externa ausente (404).

## 10/09/2026 — execução incremental

### Meta 1 / preparação de contexto

- Backup SQLite consistente criado antes das alterações: `storage/backups/before-execution-20260910131857.sqlite`.
- Contagens do backup: 1 usuário, 5 oportunidades, 2 mensagens de briefing, 2 orçamentos, 0 documentos.
- Upload de áudio agora expõe progresso retomável por usuário, caso, digest e partes recebidas; nenhum caminho privado é devolvido.
- Upload acima do limite direto preserva o original e permite anexar uma cópia MP3 compactada ao mesmo contexto, sem substituir a fonte.
- O navegador carrega o worker FFmpeg somente no fluxo de preparação; a transcrição continua dependente de configuração e fila autorizadas.

### Meta 2 / documentos, fornecedores e cotações

- `QuoteSelectionTest`: cotação selecionada é importada ao rascunho uma única vez e preserva a revisão.
- `SupplierNeedTest`: necessidade de fornecedor e seleção de cotação permanecem vinculadas ao caso; seleção não contrata fornecedor.
- `DocumentRevisionTest`: assinatura externa exige contrato enviado, autoridade comercial, data, método e evidência; repetição não cria outro registro.
- `DocumentShareLinkTest`: link de proposta usa token aleatório armazenado somente por hash, validade, contagem de visualizações, revogação e aceite/pedido de ajustes idempotente.
- A página pública não expõe notas internas, margens ou custos além do valor apresentado na proposta; a decisão fica amarrada ao documento enviado.
- `DocumentWorkspace` agora permite criar/copiar link da versão enviada e registrar assinatura externa no fluxo do contrato.

### Meta 3 / primeiro fechamento operacional

- `ProductionTechnicalValidationTest`: ciclo de dependências recusado, tarefa ligada a validação técnica bloqueada até reconfirmação, prévia de escopo aprovada confirmada uma única vez, alteração de medidas invalidando confirmação e reabertura de tarefa concluída auditada.
- `PostEventOperationalMemoryTest`: pendência final sem resolução/atribuição bloqueia encerramento; previsto × realizado, avaliação de fornecedor e reabertura explícita são preservados; repetição do encerramento não duplica o relatório; memória encerrada rejeita alteração silenciosa.
- Migrações `2026_09_10_000006_add_production_validation_and_scope_previews` e `2026_09_10_000007_expand_post_event_memory` aplicadas localmente após backup `storage/backups/before-m3-migrations-20260910143000.sqlite`.

- `ProductionOperationsTest`: tarefas persistentes agora têm fase, dependência no mesmo caso, bloqueio com justificativa, timestamps e auditoria de transição; concluir tarefa dependente antes da anterior é recusado.
- `PostEventClosureTest`: ocorrência e extra são anexados sem apagar a memória existente; encerramento preserva o rascunho e explica tarefas pendentes; após concluir todas as tarefas, registra ator, data e auditoria e fecha o caso.
- `OperationalMetricsTest`: indicadores do Histórico são calculados a partir de casos ativos, revisões persistentes, tarefas abertas e atrasos reais; a distribuição por pessoa combina atividades e tarefas sem misturar feedback do piloto.
- `OperationalHistoryTest`: o Histórico do caso inclui auditorias cujo assunto é um módulo relacionado quando o metadado contém o identificador do caso; a ordenação é determinística por data e id.

### Verificações executadas

- PHPUnit completo: 169 testes, 1.219 asserções, todos passando.
- Vitest: 12 testes, todos passando.
- TypeScript: sem erros.
- Build Vite: concluído; o runtime de preparação de áudio foi emitido como chunk/asset separado.
- Playwright: 24 testes passando em desktop 1440×900, tablet 1024×768 e celular 390×844, incluindo os controles manuais de Produção e Pós-evento.
- Pint/composer lint: passou em toda a árvore `app bootstrap/app.php config database routes tests`.
- Composer quality: validação estrita, lint, PHPUnit SQLite e auditoria de dependências passaram.
- `scripts/ci/test-mariadb.sh`: não executado por ausência de servidor local; conexão `127.0.0.1:3306` recusada. A validação MariaDB permanece gate externo da CI.
- `scripts/deploy/build-hostinger-release.sh`: release local regenerado em `dist/hostinger` com o código atual e runtime de áudio.
- `scripts/deploy/verify-hostinger-release.sh dist/hostinger`: passou; manifest Vite e checksums conferidos, `public_html` contém `index.php`, `.htaccess`, manifest e assets, sem `index.html`, código-fonte, SQLite, logs ou diretórios privados.
- Reexecução final em 10/09/2026: `php artisan test --compact` (169/169; 1.219 asserções), `npm run typecheck`, `npm run build`, `npm run test:e2e` (24/24 em desktop/tablet/celular) e o verificador Hostinger passaram após a integração do compartilhamento, necessidades de fornecedores, produção, pós-evento e métricas operacionais.

### Limitações e próximos passos

- MariaDB, GitHub Actions, Hostinger, SMTP, OpenAI real e Odoo ainda não foram validados nesta sessão.
- O ciclo permanece em execução: a evidência acima cobre as ligações de áudio, fornecedores, documentos e compartilhamento; não encerra as jornadas completas de Produção e Pós-evento.
