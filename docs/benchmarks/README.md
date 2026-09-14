# Benchmark local de entrega

Base: ae15ee1, antes das mudanças. PHP 8.5.4, SQLite em memória, 100 clientes e 500 contatos. Cinco aquecimentos e 30 amostras por rota autenticada, via kernel HTTP/Inertia. Registra mediana, p95, consultas e erros. Não inclui rede nem pintura no navegador; os números não são uma previsão de hospedagem.

Cadastro: 222 testes PHPUnit / 1.456 asserções; fluxo Playwright passou em desktop, tablet e celular; TypeScript, Pint e build passaram. O servidor E2E passou a preparar o banco antes da sondagem inicial em checkouts novos.

Reprodução: APP_ENV=testing php scripts/benchmark-delivery.php. O script usa banco descartável em memória e nunca o banco operacional.
