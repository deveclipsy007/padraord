# Deploy da Padrão RD na Hostinger

O servidor público é `public_html/index.php`. O Laravel fica em `app_core`, no diretório do domínio, fora da área pública. O bundle React compilado fica em `public_html/build`; `resources/js` e `node_modules` nunca são enviados.

## Primeiro deploy

1. Criar um site PHP/HTML para o domínio ou subdomínio no hPanel.
2. Configurar PHP 8.4, SSL, extensões PDO, cURL, DOM, Fileinfo, Mbstring, OpenSSL e XML.
3. Criar banco MariaDB e preencher manualmente `app_core/.env` no servidor.
4. Criar a conta SFTP/SSH e testar o caminho exibido em FTP Accounts.
5. Executar `./scripts/deploy/build-hostinger-release.sh` localmente.
6. Enviar `dist/hostinger` usando `./scripts/deploy/deploy-hostinger-sftp.sh`.
7. Rodar o smoke test em `https://app.padraord.com.br/up` e na raiz.

## Cron

No hPanel, criar um cron PHP a cada minuto apontando para:

```text
/opt/alt/php84/usr/bin/php /home/u12345678/domains/app.padraord.com.br/app_core/artisan schedule:run
```

O scheduler processa a fila de banco de forma transitória. Não manter `queue:work` permanente em hospedagem compartilhada.

## FTP manual

Use SFTP na porta 22 quando disponível. FTP explícito na porta 21 é contingência. Envie `app_core` e `public_html` para a pasta do domínio, preserve `.env` e `storage`, e execute migrations pelo SSH. O script `deploy-hostinger-ftp.sh` exige `lftp` e não executa migrations automaticamente.
