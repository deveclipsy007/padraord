<?php

namespace Tests\Feature;

use App\Models\AiSetting;
use App\Models\User;
use App\Services\SystemHealth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DoctorCommandTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{name:string,level:string,detail:string,action:string}> */
    private function diagnose(): array
    {
        $exit = Artisan::call('padraord:doctor', ['--json' => true]);
        $payload = json_decode(Artisan::output(), true);

        $this->assertIsArray($payload, 'O comando deve devolver JSON válido.');
        $this->assertSame($payload['ready'], $exit === 0, 'O código de saída deve acompanhar o campo ready.');

        return collect($payload['checks'])->keyBy('name')->all();
    }

    public function test_a_log_only_mailer_blocks_because_nothing_reaches_a_person(): void
    {
        config(['mail.default' => 'log']);
        $checks = $this->diagnose();

        $this->assertSame('blocked', $checks['E-mail']['level']);
        $this->assertStringContainsString('não entrega', $checks['E-mail']['detail']);
        $this->assertStringContainsString('Redefinição de senha', $checks['E-mail']['action']);
        $this->assertSame('not_delivering', SystemHealth::mailStatus());
    }

    public function test_a_real_transport_with_a_sender_passes(): void
    {
        config(['mail.default' => 'smtp', 'mail.from.address' => 'operacao@padraord.com.br']);
        $checks = $this->diagnose();

        $this->assertSame('ok', $checks['E-mail']['level']);
        $this->assertSame('configured', SystemHealth::mailStatus());
    }

    public function test_a_transport_without_a_sender_is_not_considered_ready(): void
    {
        config(['mail.default' => 'smtp', 'mail.from.address' => null]);

        $this->assertSame('unconfigured', SystemHealth::mailStatus());
        $this->assertSame('blocked', $this->diagnose()['E-mail']['level']);
    }

    public function test_an_installation_without_an_active_administrator_is_blocked(): void
    {
        User::factory()->create(['role' => 'producer', 'is_active' => true]);
        User::factory()->create(['role' => 'admin', 'is_active' => false]);

        $checks = $this->diagnose();
        $this->assertSame('blocked', $checks['Administrador']['level']);

        User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->assertSame('ok', $this->diagnose()['Administrador']['level']);
    }

    public function test_transcription_is_reported_as_authorised_only_when_it_can_actually_run(): void
    {
        $checks = $this->diagnose();
        $this->assertSame('attention', $checks['Transcrição de reunião']['level']);

        AiSetting::create([
            'id' => 1, 'mode' => 'openai', 'credential_source' => 'settings', 'api_key' => 'secret',
            'policy_approved' => true, 'monthly_micros' => 1_000_000, 'processing_micros' => 100_000,
            'input_price' => 200_000, 'output_price' => 1_200_000,
            'audio_enabled' => true, 'audio_price_micros_per_minute' => 6000,
        ]);

        $this->assertSame('ok', $this->diagnose()['Transcrição de reunião']['level']);
    }

    public function test_a_queue_stuck_for_long_enough_reports_the_scheduler_as_blocked(): void
    {
        $this->assertSame('ok', $this->diagnose()['Agendador']['level']);

        DB::table('jobs')->insert([
            'queue' => 'ai', 'payload' => '{}', 'attempts' => 0,
            'available_at' => time() - 3600, 'created_at' => time() - 3600,
        ]);

        $scheduler = $this->diagnose()['Agendador'];
        $this->assertSame('blocked', $scheduler['level']);
        $this->assertStringContainsString('schedule:run', $scheduler['action']);
    }

    public function test_debug_enabled_in_production_is_blocking(): void
    {
        config(['app.debug' => true]);
        $this->app->detectEnvironment(fn () => 'production');

        $check = $this->diagnose()['Modo de depuração'];
        $this->assertSame('blocked', $check['level']);
        $this->assertStringContainsString('APP_DEBUG=false', $check['action']);
    }
}
