# Evidências do Ciclo 01

**Execução:** 01/09/2026  
**Ambiente:** macOS local, PHP 8.5.4, Node 22.22.2, SQLite; MariaDB e Hostinger ainda não acessíveis.

## Preservação e baseline

- Integridade do SQLite verificada antes da execução: `ok`.
- Cópia consistente guardada em `../cycle-01-backup-20260901/database.sqlite`.
- Contagens no banco local no momento da auditoria: 1 usuário, 5 oportunidades, 2 mensagens de briefing, 2 orçamentos, 0 documentos e 3 arquivos privados.
- Branch local `main` criada com commit de baseline `7b88493`.
- Busca por credenciais não encontrou chave, senha ou token real versionado; o único resultado é o placeholder documentado em `.env.deploy.example`.

## Qualidade local

| Comando | Resultado observado |
|---|---|
| `composer validate --strict --no-check-publish` | passou |
| `composer lint` / Pint | passou sem arquivos pendentes |
| `composer test:sqlite` | 120 testes, 918 assertions — passou |
| `composer audit --locked` | nenhum advisory conhecido |
| `npm run test:ui` | 12 testes — passou |
| `npm run typecheck` | passou |
| `npm run build` | passou; manifest Vite gerado |
| `npm audit --omit=dev --audit-level=high` | 0 vulnerabilidades |
| `npm run test:e2e` | 21 testes em desktop, tablet e mobile — passou |
| `scripts/deploy/verify-hostinger-release.sh dist/hostinger` | checksums, manifest e superfície pública — passou |

## Verificações adicionadas

- 96 rotas detectadas após a inclusão de `/api/v1/health` e da alteração de autoridade comercial.
- Gate `manage-team`, `manage-ai`, `view-pilot-feedback`, `manage-demo` e `approve-commercial` centralizado em `Ability`.
- Alteração de autoridade exige administrador, senha atual, justificativa e gera auditoria antes/depois.
- `/api/v1/health` sem token ou com token inválido retorna `401`; com token válido retorna banco, storage, fila, mail, IA e Odoo sem segredos.
- Cada resposta recebe `X-Request-Id`; logs podem usar o canal diário JSON sem body, áudio, transcrição, senha ou chave.
- `public_html` do release contém apenas `index.php`, `.htaccess`, manifest e assets; `.env`, SQLite, testes, fonte React/TypeScript, logs e diretórios privados são rejeitados.

## Bloqueios externos

- `gh` não está instalado/autenticado; nenhum remote GitHub foi criado ou recebeu push. O workflow está versionado em `.github/workflows/ci.yml` e aguarda a criação do repositório privado `padrao-rd-os`.
- Não há servidor ou cliente MariaDB local. `phpunit.mariadb.xml` e `scripts/ci/test-mariadb.sh` estão prontos para o serviço MariaDB 11.4 na CI.
- Não foram fornecidas credenciais do hPanel, FTP/SFTP, SSH, domínio, SMTP ou Odoo. Nenhum upload, migration ou alteração de produção foi realizado.
- O inventário de capacidades está em [hostinger-capabilities.md](hostinger-capabilities.md) e marca o gate de CLI seguro como pendente.

## Próximo passo

Criar o repositório privado, executar o workflow em GitHub Actions (incluindo MariaDB), coletar o inventário real do hPanel e só então abrir o Ciclo 02. Até que isso ocorra, o estado honesto é **fundação local validada / produção não validada**.
