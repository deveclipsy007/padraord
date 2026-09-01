# Inventário Hostinger — Ciclo 01

**Data do inventário:** 01/09/2026  
**Escopo:** preparação, sem upload, migration ou alteração em produção.

## Estado de acesso

Neste ciclo não foram fornecidas credenciais do hPanel, FTP/SFTP, SSH, banco ou domínio. Portanto, os itens abaixo são requisitos a confirmar no painel, e não capacidades declaradas como testadas.

| Item | Estado | Evidência / ação necessária |
|---|---|---|
| Plano | Bloqueado por acesso | Confirmar que o site usa Web Business |
| Domínio recomendado | Pendente | Confirmar `app.padraord.com.br` e o diretório apresentado pelo hPanel |
| Document root | Planejado | `public_html`; confirmar no painel do domínio |
| PHP web | Pendente | Fixar em 8.4 e conferir a versão efetiva em uma página protegida |
| PHP CLI | Pendente | Confirmar binário e versão usados pelo cron/SSH |
| Extensões | Pendente | PDO, pdo_mysql, cURL, DOM, Fileinfo, Mbstring, OpenSSL, XML e Zip |
| MariaDB | Pendente | Criar banco, usuário e testar migration sem registrar senha |
| SSH | Pendente | Necessário para `artisan migrate --force`, `optimize`, manutenção e smoke test |
| SFTP | Pendente | Transporte padrão; validar porta e conta limitada ao domínio |
| FTP com TLS | Pendente | Contingência; confirmar FTP explícito na porta 21 com proteção de dados |
| Cron | Pendente | Um `schedule:run` por minuto usando o PHP 8.4 efetivo |
| SSL/HTTPS | Pendente | Confirmar certificado ativo e redirecionamento HTTPS |
| SMTP | Pendente | Confirmar host, porta, TLS e remetente autorizado; não registrar credenciais |
| Backups | Pendente | Confirmar backup diário e retenção; criar backup antes de migration |
| Composer/Artisan | Pendente | Confirmar se há SSH/CLI; FTP sozinho não executa comandos de aplicação |

## Caminho remoto planejado

```text
/home/u12345678/domains/app.padraord.com.br/
├── app_core/       # Laravel, vendor, .env e storage privados
└── public_html/    # index.php, .htaccess e build compilado
```

O caminho acima é um exemplo documentado da arquitetura e deve ser substituído pelo caminho exibido no hPanel. Nenhum arquivo foi enviado neste ciclo.

## Gate de produção

FTP sem SSH/SFTP/CLI não libera a publicação. O deploy só poderá ser coordenado quando houver mecanismo seguro para:

1. colocar a aplicação em manutenção;
2. executar migration e cache com o PHP correto;
3. processar scheduler/fila;
4. rodar smoke test interno;
5. preservar e restaurar `.env`, `storage` e documentos;
6. executar rollback de aplicação e banco.

O bloqueio permanece aberto para o Ciclo 06. Credenciais reais devem ser fornecidas apenas por canal seguro e nunca commitadas no Git.

## Runbook de coleta

Quando o acesso estiver disponível, registrar apenas valores não secretos:

- nome do plano, domínio e caminho absoluto;
- versões web/CLI do PHP e extensões;
- host/porta/nome do MariaDB, sem senha;
- disponibilidade de SSH/SFTP e porta;
- limites de upload, memória e tempo de execução;
- configuração do cron e saída visível no hPanel;
- estado do SSL, SMTP, backups e restauração;
- resultado de `php artisan --version`, `migrate:status`, `optimize` e `/api/v1/health`.

O resultado deve ser anexado ao registro de evidências do Ciclo 01 antes de qualquer deploy.
