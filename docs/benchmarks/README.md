# Benchmark local de entrega

Base: ae15ee1, antes das mudanças. PHP 8.5.4, SQLite em memória, 100 clientes e 500 contatos. Cinco aquecimentos e 30 amostras por rota autenticada, via kernel HTTP/Inertia. Registra mediana, p95, consultas e erros. Não inclui rede nem pintura no navegador; os números não são uma previsão de hospedagem.

Cadastro: 222 testes PHPUnit / 1.456 asserções; fluxo Playwright passou em desktop, tablet e celular; TypeScript, Pint e build passaram. O servidor E2E passou a preparar o banco antes da sondagem inicial em checkouts novos.

Reprodução: APP_ENV=testing php scripts/benchmark-delivery.php. O script usa banco descartável em memória e nunca o banco operacional.

## Briefing estruturado — 14/09/2026

238 testes PHP, 25 testes selecionados em MariaDB 11.4.13, qualidade frontend e suíte Playwright completa passaram. O benchmark de cadastro permaneceu com 10/11 consultas; medianas 1,854/1,928 ms e p95 2,278/2,360 ms. JSON: `benchmark-rd-briefing.json`. São medições locais do kernel HTTP, sem rede ou pintura da tela.

Critérios de requisitos, programação e fontes concluídos. O critério conjunto A4/A8 permanece pendente: a consolidação dos estados, nomes derivados e aposentadoria do áudio legado ainda depende das próximas missões.
