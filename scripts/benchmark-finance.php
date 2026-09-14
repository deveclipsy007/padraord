<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Opportunity;
use App\Models\User;
use App\Services\EventFinance;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
abort_unless(app()->environment('testing'), 403);
config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array', 'session.driver' => 'array', 'mail.default' => 'array']);
DB::purge('sqlite');
Artisan::call('migrate', ['--force' => true]);
$u = User::factory()->create(['can_approve_commercial' => true]);
Auth::login($u);
$c = Opportunity::create(['title' => 'Benchmark financeiro', 'client_name' => 'Cliente sintético']);
$b = $c->budgets()->create(['status' => 'approved', 'version' => 1]);
for ($i = 0; $i < 100; $i++) {
    $b->items()->create(['category' => 'categoria '.($i % 10), 'description' => "Item $i", 'quantity' => 2, 'unit_cost_cents' => 10000, 'margin_percent' => 20]);
}
$s = app(EventFinance::class);
$plan = $s->savePlan($c, $u, ['revision' => 0, 'total_cents' => 2400000, 'installments' => [['label' => 'Entrada', 'share_bps' => 5000, 'trigger' => 'assinatura'], ['label' => 'Saldo', 'share_bps' => 5000, 'trigger' => 'data_fixa', 'due_at' => '2026-10-10']]]);
$s->acceptPlan($c, $u, $plan, ['revision' => 1, 'evidence' => 'Fixture de benchmark']);
$s->confirmReceivables($c, $u, $s->previewReceivables($c, $u, $plan));
$kernel = $app->make(HttpKernel::class);
$q = 0;
DB::listen(function () use (&$q) {
    $q++;
});
$url = "/opportunities/{$c->id}/finance";
$times = [];
$queries = [];
$errors = [];
for ($i = 0; $i < 35; $i++) {
    $q = 0;
    $r = Request::create($url, 'GET', [], [], [], ['HTTP_ACCEPT' => 'text/html', 'HTTP_X_INERTIA' => 'true', 'HTTP_X_INERTIA_VERSION' => (new HandleInertiaRequests)->version(Request::create($url))]);
    $start = hrtime(true);
    $response = $kernel->handle($r);
    $ms = (hrtime(true) - $start) / 1e6;
    $kernel->terminate($r, $response);
    if ($i >= 5) {
        $times[] = $ms;
        $queries[] = $q;
        if ($response->getStatusCode() !== 200) {
            $errors[] = $response->getStatusCode();
        }
    }
}
sort($times);
echo json_encode(['at' => now()->toIso8601String(), 'php' => PHP_VERSION, 'dataset' => ['budget_items' => 100, 'categories' => 10, 'receivables' => 2], 'samples' => 30, 'warmups' => 5, 'median_ms' => round(($times[14] + $times[15]) / 2, 3), 'p95_ms' => round($times[28], 3), 'queries_max' => max($queries), 'errors' => $errors], JSON_PRETTY_PRINT).PHP_EOL;
