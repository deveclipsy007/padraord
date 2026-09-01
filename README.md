# Padrão RD — sistema operacional de eventos

Fundação do sistema operacional da Padrão RD: um CRM objetivo para transformar contexto de eventos em briefing, orçamento, proposta, contrato e produção, com IA assistiva e revisão humana.

## Stack

- Laravel 13 / PHP 8.3+ (produção fixada em PHP 8.4 na Hostinger)
- React 19 + TypeScript + Inertia React
- Vite apenas no desenvolvimento e na compilação do artefato
- SQLite para desenvolvimento/testes rápidos
- MariaDB na Hostinger, via driver `mysql`

O adaptador PHP do Inertia disponível no Composer é `inertiajs/inertia-laravel` 2.x; o adaptador React segue a linha 3.x do pacote oficial. O protocolo é encapsulado pela integração Inertia e não há dependência de Node.js no servidor.

## Desenvolvimento local

```bash
composer install
npm ci
php artisan migrate
npm run build
php artisan serve
```

O protótipo operacional validável está disponível com login, Dashboard, Pipeline, Agenda, Clientes, Histórico, tutorial, busca rápida, hub da oportunidade, Briefing, Orçamento, Proposta, Contrato, Produção e Pós-evento. A aplicação usa uma única entrada React em `resources/js/app.tsx`.

No ambiente local, rode `php artisan db:seed` para restaurar o cenário demonstrativo “Conferência Horizonte 2026”. O acesso inicial é `test@example.com` com senha `password`; altere esses dados antes de qualquer ambiente compartilhado.

## Hostinger compartilhada

O código-fonte local continua em `resources/`, mas o pacote de produção separa o núcleo privado da pasta pública:

```text
domínio/
├── app_core/       # Laravel, vendor, migrations, views, .env e storage
└── public_html/    # index.php, .htaccess e build/ compilado
```

Não existe `public_html/index.html`. O entrypoint é sempre `public_html/index.php`, que aponta para o Laravel em `../app_core`. `node_modules`, TypeScript e código-fonte não são publicados.

Para gerar o pacote:

```bash
./scripts/deploy/build-hostinger-release.sh
```

O resultado fica em `dist/hostinger`. Leia [`docs/hostinger-deploy.md`](docs/hostinger-deploy.md) antes do primeiro envio. Os scripts SFTP e FTP exigem variáveis de ambiente e nunca versionam credenciais.

## Verificação

```bash
php artisan test --compact
npm run typecheck
npm run build
bash -n scripts/deploy/*.sh
```

O deploy deve ser realizado somente depois de configurar o `.env` remoto, criar o banco MariaDB, fazer backup no hPanel e validar o smoke test.
