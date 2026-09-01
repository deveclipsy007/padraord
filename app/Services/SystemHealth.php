<?php

namespace App\Services;

use App\AI\AiConfiguration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class SystemHealth
{
    public function __construct(private readonly AiConfiguration $aiConfiguration) {}

    /** @return array{status:string,version:string,timestamp:string,checks:array<string,string>} */
    public function check(): array
    {
        $database = $this->database();
        $storage = is_dir(storage_path()) && is_writable(storage_path('framework')) ? 'ok' : 'unavailable';

        $result = [
            'status' => $database === 'ok' && $storage === 'ok' ? 'ok' : 'degraded',
            'version' => (string) config('app.version', 'dev'),
            'timestamp' => now()->utc()->toIso8601String(),
            'checks' => [
                'database' => $database,
                'storage' => $storage,
                'queue' => $this->queue(),
                'mail' => filled(config('mail.default')) ? 'configured' : 'unconfigured',
                'ai' => $this->aiStatus(),
                'odoo' => $this->odooStatus(),
            ],
        ];

        return $result;
    }

    private function database(): string
    {
        try {
            DB::select('select 1');

            return 'ok';
        } catch (Throwable) {
            return 'unavailable';
        }
    }

    private function queue(): string
    {
        if (config('queue.default') !== 'database') {
            return filled(config('queue.default')) ? 'configured' : 'unconfigured';
        }

        return Schema::hasTable((string) config('queue.connections.database.table', 'jobs')) ? 'configured' : 'unavailable';
    }

    private function aiStatus(): string
    {
        try {
            return (string) ($this->aiConfiguration->publicState()['status'] ?? 'unavailable');
        } catch (Throwable) {
            return 'unavailable';
        }
    }

    private function odooStatus(): string
    {
        if (config('odoo.mode') === 'disabled') {
            return 'disabled';
        }

        return filled(config('odoo.base_url')) && filled(config('odoo.api_key')) ? 'configured' : 'unavailable';
    }
}
