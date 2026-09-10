<?php

namespace App\Console\Commands;

use App\AI\AiConfiguration;
use App\Models\User;
use App\Services\SystemHealth;
use Illuminate\Console\Command;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Diz, numa tela, o que ainda impede a operação real. Cada item responde
 * "o que fazer" — não apenas "falhou". Só lê; nunca corrige sozinho.
 */
class Doctor extends Command
{
    protected $signature = 'padraord:doctor {--json : Devolve o resultado em JSON}';

    protected $description = 'Verifica se a instalação está pronta para uso real e aponta o que falta';

    public function handle(AiConfiguration $ai, Migrator $migrator): int
    {
        $checks = [
            $this->appKey(),
            $this->database(),
            $this->migrations($migrator),
            $this->storage(),
            $this->queue(),
            $this->scheduler(),
            $this->mail(),
            $this->aiEngine($ai),
            $this->audio($ai),
            $this->build(),
            $this->administrator(),
            $this->debugFlag(),
        ];

        $blocking = array_values(array_filter($checks, fn (array $c) => $c['level'] === 'blocked'));
        $attention = array_values(array_filter($checks, fn (array $c) => $c['level'] === 'attention'));

        if ($this->option('json')) {
            $this->line((string) json_encode(['ready' => $blocking === [], 'checks' => $checks], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return $blocking === [] ? self::SUCCESS : self::FAILURE;
        }

        $this->newLine();
        $this->components->info('Padrão RD · prontidão da instalação');
        foreach ($checks as $check) {
            [$colour, $label] = match ($check['level']) {
                'ok' => ['green', 'OK'],
                'attention' => ['yellow', 'ATENÇÃO'],
                default => ['red', 'BLOQUEIO'],
            };
            $this->components->twoColumnDetail(
                $check['name'].' <fg=gray>'.$check['detail'].'</>',
                "<fg={$colour};options=bold>{$label}</>",
            );
            if ($check['level'] !== 'ok' && $check['action'] !== '') {
                $this->components->bulletList([$check['action']]);
            }
        }

        $this->newLine();
        if ($blocking !== []) {
            $this->line('  <fg=red;options=bold>'.count($blocking).' item(ns) impedem o uso real.</>');
        } elseif ($attention !== []) {
            $this->line('  <fg=yellow;options=bold>Pronto para uso, com '.count($attention).' ponto(s) de atenção.</>');
        } else {
            $this->line('  <fg=green;options=bold>Instalação pronta para uso real.</>');
        }
        $this->newLine();

        return $blocking === [] ? self::SUCCESS : self::FAILURE;
    }

    /** @return array{name:string,level:string,detail:string,action:string} */
    private function result(string $name, string $level, string $detail, string $action = ''): array
    {
        return ['name' => $name, 'level' => $level, 'detail' => $detail, 'action' => $action];
    }

    private function appKey(): array
    {
        return filled(config('app.key'))
            ? $this->result('Chave da aplicação', 'ok', 'definida')
            : $this->result('Chave da aplicação', 'blocked', 'ausente', 'Rode php artisan key:generate. Sem ela a sessão e a chave da IA não são decifradas.');
    }

    private function database(): array
    {
        try {
            DB::select('select 1');

            return $this->result('Banco de dados', 'ok', (string) config('database.default'));
        } catch (Throwable $e) {
            return $this->result('Banco de dados', 'blocked', 'inacessível', 'Confira as variáveis DB_* no .env. '.$e->getMessage());
        }
    }

    private function migrations(Migrator $migrator): array
    {
        try {
            if (! $migrator->repositoryExists()) {
                return $this->result('Migrações', 'blocked', 'nunca executadas', 'Rode php artisan migrate --force.');
            }
            $applied = $migrator->getRepository()->getRan();
            $pending = collect($migrator->getMigrationFiles($migrator->paths() ?: [database_path('migrations')]))
                ->keys()
                ->reject(fn ($name) => in_array($name, $applied, true));

            return $pending->isEmpty()
                ? $this->result('Migrações', 'ok', count($applied).' aplicadas')
                : $this->result('Migrações', 'blocked', $pending->count().' pendente(s)', 'Rode php artisan migrate --force após fazer backup do banco.');
        } catch (Throwable $e) {
            return $this->result('Migrações', 'attention', 'não verificáveis', $e->getMessage());
        }
    }

    private function storage(): array
    {
        $paths = ['framework' => storage_path('framework'), 'logs' => storage_path('logs'), 'app/private' => storage_path('app')];
        $bad = array_keys(array_filter($paths, fn (string $path) => ! is_dir($path) || ! is_writable($path)));

        return $bad === []
            ? $this->result('Armazenamento', 'ok', 'gravável')
            : $this->result('Armazenamento', 'blocked', 'sem escrita em '.implode(', ', $bad), 'Ajuste as permissões da pasta storage no servidor.');
    }

    private function queue(): array
    {
        $driver = (string) config('queue.default');
        if ($driver === 'sync') {
            return $this->result('Fila', 'attention', 'sync (processa na requisição)', 'Transcrição e extração longas vão travar a tela. Use QUEUE_CONNECTION=database.');
        }
        if ($driver === 'database' && ! Schema::hasTable((string) config('queue.connections.database.table', 'jobs'))) {
            return $this->result('Fila', 'blocked', 'tabela de jobs ausente', 'Rode php artisan migrate --force.');
        }
        $failed = Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0;

        return $failed > 0
            ? $this->result('Fila', 'attention', $driver.', '.$failed.' job(s) com falha', 'Inspecione com php artisan queue:failed antes de reprocessar.')
            : $this->result('Fila', 'ok', $driver);
    }

    private function scheduler(): array
    {
        if (! Schema::hasTable('jobs')) {
            return $this->result('Agendador', 'attention', 'não verificável', 'A tabela de jobs ainda não existe.');
        }
        $waiting = DB::table('jobs')->count();
        $oldest = DB::table('jobs')->min('available_at');
        $stuckMinutes = $oldest ? (int) round((time() - (int) $oldest) / 60) : 0;

        if ($waiting > 0 && $stuckMinutes >= 10) {
            return $this->result('Agendador', 'blocked', $waiting.' job(s) parados há '.$stuckMinutes.' min', 'O cron de schedule:run não está rodando. Veja docs/hostinger-deploy.md.');
        }

        return $this->result('Agendador', 'ok', $waiting === 0 ? 'sem fila acumulada' : $waiting.' job(s) na fila');
    }

    private function mail(): array
    {
        return match (SystemHealth::mailStatus()) {
            'configured' => $this->result('E-mail', 'ok', (string) config('mail.default')),
            'not_delivering' => $this->result('E-mail', 'blocked', (string) config('mail.default').' (não entrega a ninguém)', 'Redefinição de senha e envio de proposta não chegam ao destinatário. Configure MAIL_MAILER com SMTP ou provedor real e MAIL_FROM_ADDRESS.'),
            default => $this->result('E-mail', 'blocked', 'sem remetente ou transporte', 'Defina MAIL_MAILER e MAIL_FROM_ADDRESS no .env.'),
        };
    }

    private function aiEngine(AiConfiguration $ai): array
    {
        $state = $ai->publicState();
        $detail = $state['mode'].' · '.$state['status'];

        return match ($state['status']) {
            'ready' => $this->result('Inteligência artificial', 'ok', $detail),
            'manual' => $this->result('Inteligência artificial', 'attention', $detail, 'A operação funciona toda no manual. Para ligar a IA, abra Administração → Inteligência artificial.'),
            'demo' => $this->result('Inteligência artificial', 'attention', $detail, 'Modo demonstração não envia nada para fora e não vale como validação.'),
            'missing_key' => $this->result('Inteligência artificial', 'attention', $detail, 'Cadastre a chave em Administração → Inteligência artificial.'),
            'policy_pending' => $this->result('Inteligência artificial', 'attention', $detail, 'Aprove a política de dados na tela de administração.'),
            'limits_pending' => $this->result('Inteligência artificial', 'attention', $detail, 'Defina limite mensal, limite por processamento e tarifas.'),
            'limit_reached' => $this->result('Inteligência artificial', 'attention', $detail, 'O limite mensal foi atingido. Reveja o teto antes de continuar.'),
            default => $this->result('Inteligência artificial', 'attention', $detail),
        };
    }

    private function audio(AiConfiguration $ai): array
    {
        if ($ai->audioReady()) {
            return $this->result('Transcrição de reunião', 'ok', 'autorizada · US$ '.number_format($ai->audioPriceMicrosPerMinute() / 1_000_000, 6).'/min');
        }

        return $this->result('Transcrição de reunião', 'attention', 'não autorizada', 'Reunião gravada não vira briefing automaticamente. Autorize e informe a tarifa por minuto em Administração → Inteligência artificial.');
    }

    private function build(): array
    {
        $manifest = public_path('build/manifest.json');
        if (! is_file($manifest)) {
            return $this->result('Interface compilada', 'blocked', 'manifest ausente', 'Rode npm ci && npm run build antes de publicar.');
        }
        $entries = json_decode((string) file_get_contents($manifest), true);

        return is_array($entries) && $entries !== []
            ? $this->result('Interface compilada', 'ok', count($entries).' entradas')
            : $this->result('Interface compilada', 'blocked', 'manifest inválido', 'Rode npm run build novamente.');
    }

    private function administrator(): array
    {
        if (! Schema::hasTable('users')) {
            return $this->result('Administrador', 'blocked', 'tabela de usuários ausente', 'Rode php artisan migrate --force.');
        }
        $admins = User::query()->where('is_active', true)->whereIn('role', ['admin', 'administrator'])->count();

        return $admins > 0
            ? $this->result('Administrador', 'ok', $admins.' ativo(s)')
            : $this->result('Administrador', 'blocked', 'nenhum administrador ativo', 'Sem administrador ninguém configura IA nem gerencia a equipe.');
    }

    private function debugFlag(): array
    {
        if (! config('app.debug')) {
            return $this->result('Modo de depuração', 'ok', 'desligado');
        }

        return app()->environment('production')
            ? $this->result('Modo de depuração', 'blocked', 'ligado em produção', 'Defina APP_DEBUG=false. Ligado, ele expõe caminhos internos e trechos de configuração em telas de erro.')
            : $this->result('Modo de depuração', 'attention', 'ligado ('.app()->environment().')', 'Aceitável fora de produção; nunca publique assim.');
    }
}
