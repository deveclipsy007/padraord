<?php

// Local benchmark: always uses a disposable, in-memory database.
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Client;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
abort_unless(app()->environment('testing'), 403, 'Use APP_ENV=testing.');
config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array', 'session.driver' => 'array', 'mail.default' => 'array']);
DB::purge('sqlite');
Artisan::call('migrate', ['--force' => true]);
$user = User::factory()->create();
Auth::login($user);
for ($i = 0; $i < 100; $i++) {
    $client = Client::create(['name' => "Benchmark $i", 'legal_name' => "Empresa $i"]);
    for ($j = 0; $j < 5; $j++) {
        $client->contacts()->create(['name' => "Pessoa $j", 'email' => "p$j@example.test"]);
    }
}
$kernel = $app->make(HttpKernel::class);
$report = ['measured_at' => now()->toIso8601String(), 'php' => PHP_VERSION, 'dataset' => ['clients' => 100, 'contacts' => 500], 'warmups' => 5, 'samples' => 30, 'routes' => []];
$queryCount = 0;
DB::listen(function () use (&$queryCount) {
    $queryCount++;
});
foreach (['/clients', '/clients/1'] as $url) {
    $times = [];
    $queries = [];
    $errors = [];
    for ($i = 0; $i < 35; $i++) {
        $queryCount = 0;
        $request = Request::create($url, 'GET', [], [], [], ['HTTP_ACCEPT' => 'text/html', 'HTTP_X_INERTIA' => 'true', 'HTTP_X_INERTIA_VERSION' => (new HandleInertiaRequests)->version(Request::create($url))]);
        $start = hrtime(true);
        $response = $kernel->handle($request);
        $elapsed = (hrtime(true) - $start) / 1e6;
        $kernel->terminate($request, $response);
        if ($i >= 5) {
            $times[] = $elapsed;
            $queries[] = $queryCount;
            if ($response->getStatusCode() !== 200) {
                $errors[] = $response->getStatusCode();
            }
        }
    }
    sort($times);
    $report['routes'][$url] = ['median_ms' => round(($times[14] + $times[15]) / 2, 3), 'p95_ms' => round($times[28], 3), 'queries_max' => max($queries), 'errors' => $errors];
}
echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
