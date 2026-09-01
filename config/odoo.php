<?php

return [
    'mode' => env('ODOO_MODE', 'disabled'),
    'base_url' => env('ODOO_BASE_URL'),
    'api_key' => env('ODOO_API_KEY'),
    'database' => env('ODOO_DATABASE'),
    'model' => env('ODOO_COST_MODEL', 'account.analytic.line'),
    'analytic_account_id' => (int) env('ODOO_ANALYTIC_ACCOUNT_ID', 0),
    'currency' => env('ODOO_COST_CURRENCY', 'USD'),
    'idempotency_field' => env('ODOO_IDEMPOTENCY_FIELD', 'x_padrao_rd_idempotency_key'),
    'amount_multiplier' => (float) env('ODOO_AMOUNT_MULTIPLIER', -1),
];
