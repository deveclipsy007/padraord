# Benchmark local de entrega

Base: ae15ee1, antes das mudanças. PHP 8.5.4, SQLite em memória, 100 clientes e 500 contatos. Cinco aquecimentos e 30 amostras por rota autenticada, via kernel HTTP/Inertia. Registra mediana, p95, consultas e erros. Não inclui rede nem pintura no navegador; os números não são uma previsão de hospedagem.

Cadastro: 222 testes PHPUnit / 1.456 asserções; fluxo Playwright passou em desktop, tablet e celular; TypeScript, Pint e build passaram. O servidor E2E passou a preparar o banco antes da sondagem inicial em checkouts novos.

Reprodução: APP_ENV=testing php scripts/benchmark-delivery.php. O script usa banco descartável em memória e nunca o banco operacional.

## Briefing estruturado — 14/09/2026

238 testes PHP, 25 testes selecionados em MariaDB 11.4.13, qualidade frontend e suíte Playwright completa passaram. O benchmark de cadastro permaneceu com 10/11 consultas; medianas 1,854/1,928 ms e p95 2,278/2,360 ms. JSON: `benchmark-rd-briefing.json`. São medições locais do kernel HTTP, sem rede ou pintura da tela.

Critérios de requisitos, programação e fontes concluídos. O critério conjunto A4/A8 permanece pendente: a consolidação dos estados, nomes derivados e aposentadoria do áudio legado ainda depende das próximas missões.

## Financeiro — 14/09/2026

Cenário isolado: 100 itens aprovados, 10 categorias, duas parcelas; 5 aquecimentos e 30 medições. O benchmark encontrou uma consulta ao orçamento por item. Ao reaproveitar a relação já carregada, as consultas caíram de 143 para 43, a mediana de 18,210 ms para 10,347 ms e o p95 de 19,262 ms para 11,090 ms. Zero erros. Relatórios `benchmark-finance-before.json` / `benchmark-finance-after.json`, reproduzíveis com `APP_ENV=testing php scripts/benchmark-finance.php`. Não mede rede, navegador nem produção.

Validação da missão financeira: 249 testes PHP (1.634 asserções), 12 testes selecionados no MariaDB (62 asserções), qualidade frontend e suíte Playwright completa passaram. Casos de pacote cotado e duplicação cotação/orçamento incluídos.
