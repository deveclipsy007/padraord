# Deploy da Padrão RD na Hostinger

O servidor público é `public_html/index.php`. O Laravel fica em `app_core`, no diretório do domínio, fora da área pública. O bundle React compilado fica em `public_html/build`; `resources/js` e `node_modules` nunca são enviados.

## Primeiro deploy

1. Criar um site PHP/HTML para o domínio ou subdomínio no hPanel.
2. Configurar PHP 8.4, SSL, extensões PDO, cURL, DOM, Fileinfo, Mbstring, OpenSSL e XML.
3. Criar banco MariaDB e preencher manualmente `app_core/.env` no servidor.
4. Criar a conta SFTP/SSH e testar o caminho exibido em FTP Accounts.
5. Executar `./scripts/deploy/build-hostinger-release.sh` localmente.
   O script roda a suíte local, gera `dist/hostinger` e executa o verificador de superfície pública.
6. Enviar `dist/hostinger` usando `./scripts/deploy/deploy-hostinger-sftp.sh`.
7. Rodar o smoke test em `https://app.padraord.com.br/up` e na raiz.

## Cron

No hPanel, criar um cron PHP a cada minuto apontando para:

```text
/opt/alt/php84/usr/bin/php /home/u12345678/domains/app.padraord.com.br/app_core/artisan schedule:run
```

O scheduler processa a fila de banco de forma transitória. Não manter `queue:work` permanente em hospedagem compartilhada.

## Verificação do pacote

Antes de qualquer transporte, executar:

```text
./scripts/deploy/verify-hostinger-release.sh dist/hostinger
```

O verificador confirma checksums, manifest Vite, `public_html/index.php`, ausência de `index.html`, `.env`, SQLite, logs, source maps, código React/TypeScript e diretórios privados na área pública. O release `20260814161958` foi marcado como obsoleto e não deve ser enviado.

## FTP manual

Use SFTP na porta 22 quando disponível. FTP explícito na porta 21 é contingência. Envie `app_core` e `public_html` para a pasta do domínio, preserve `.env` e `storage`, e execute migrations pelo SSH. O script `deploy-hostinger-ftp.sh` exige `lftp` e não executa migrations automaticamente.

## E-mail (SMTP da Hostinger)

Sem transporte real, a redefinição de senha e o envio de proposta ficam gravados
num arquivo e não chegam a ninguém. `MAIL_MAILER=log` é aceitável apenas em
desenvolvimento, sabendo dessa limitação.

No hPanel, criar a caixa de e-mail do sistema (por exemplo `sistema@padraord.com.br`)
e usar o endereço completo como usuário:

```dotenv
MAIL_MAILER=smtp
MAIL_SCHEME=smtps
MAIL_HOST=smtp.hostinger.com
MAIL_PORT=465
MAIL_USERNAME=sistema@padraord.com.br
MAIL_PASSWORD=<senha da caixa, nunca versionada>
MAIL_FROM_ADDRESS=sistema@padraord.com.br
MAIL_FROM_NAME="Padrão RD"
```

Porta 465 usa TLS implícito e exige `MAIL_SCHEME=smtps`. Para a porta 587, use
`MAIL_SCHEME=smtp`, que negocia STARTTLS. Trocar a porta sem trocar o esquema
é a causa mais comum de falha de conexão.

Depois de configurar, confirmar com um envio real:

```bash
php artisan padraord:mail-test voce@seudominio.com.br
```

O provedor aceitar a mensagem não garante a entrega na caixa. Confira o
recebimento, inclusive na pasta de spam, antes de considerar concluído.
`php artisan padraord:doctor` passa a reportar E-mail como `ok`.
