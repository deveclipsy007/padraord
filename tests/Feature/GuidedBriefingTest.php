<?php

namespace Tests\Feature;

use App\AI\AiCompletion;
use App\AI\AiProvider;
use App\Jobs\AnalyzeBriefing;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuidedBriefingTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_placeholder_is_not_treated_as_known_critical_information(): void
    {
        $o = Opportunity::create(['title' => 'Evento', 'client_name' => 'Cliente', 'stage' => 'briefing', 'briefing_data' => ['objective' => 'Encontro', 'audience' => '100', 'event_date' => '2026-10-15', 'location' => 'Recife', 'budget' => 'Ainda a confirmar', 'scope' => 'Palco']]);
        $this->actingAs(User::factory()->create())->post("/opportunities/$o->id/briefing/review", ['revision' => 0, 'action' => 'approve'])->assertSessionHasErrors('review');
        $this->assertNotSame('complete', $o->fresh()->briefing_status);
    }

    public function test_manual_changes_are_versioned_and_stale_write_is_rejected(): void
    {
        $o = Opportunity::create(['title' => 'Evento', 'client_name' => 'Cliente', 'stage' => 'briefing']);
        $this->actingAs(User::factory()->create());
        $this->post("/opportunities/$o->id/briefing/review", ['revision' => 0, 'action' => 'save', 'fields' => ['objective' => 'Conectar equipe']])->assertRedirect();
        $this->assertSame('Conectar equipe', $o->fresh()->briefing_data['objective']);
        $this->post("/opportunities/$o->id/briefing/review", ['revision' => 0, 'action' => 'save', 'fields' => ['objective' => 'Sobrescrever']])->assertSessionHasErrors('revision');
        $this->assertSame('Conectar equipe', $o->fresh()->briefing_data['objective']);
    }

    public function test_job_receives_consolidated_context_and_does_not_mutate_briefing(): void
    {
        $o = Opportunity::create(['title' => 'Evento', 'client_name' => 'Cliente', 'stage' => 'briefing', 'briefing_data' => ['location' => 'Recife'], 'briefing_revision' => 2]);
        $m = $o->briefingMessages()->create(['role' => 'user', 'source' => 'manual', 'body' => 'Público de 300 pessoas']);
        $provider = new class implements AiProvider
        {
            public array $context = [];

            public function analyzeBriefing(string $transcript, array $context = []): AiCompletion
            {
                $this->context = $context;

                return AiCompletion::success(['summary' => 'Encontro de 300 pessoas', 'facts' => [], 'missing_questions' => [], 'risks' => [], 'suggested_changes' => [['field' => 'audience', 'current' => null, 'suggested' => '300 pessoas', 'reason' => 'Explicitado no texto']]], 'demo', 'test', 'v2');
            }
        };
        (new AnalyzeBriefing($m->id))->handle($provider);
        $this->assertSame('Recife', $provider->context['briefing']['location']);
        $this->assertSame(2, $provider->context['revision']);
        $this->assertSame(['location' => 'Recife'], $o->fresh()->briefing_data);
        $run = $o->aiRuns()->first();
        $this->actingAs(User::factory()->create())->post("/opportunities/$o->id/briefing/review", ['revision' => 2, 'action' => 'accept', 'run_id' => $run->id, 'indices' => [0]])->assertRedirect();
        $this->assertSame('300 pessoas', $o->fresh()->briefing_data['audience']);
    }

    public function test_suggestion_from_another_case_is_rejected(): void
    {
        $o = Opportunity::create(['title' => 'Evento', 'client_name' => 'Cliente', 'stage' => 'briefing']);
        $other = Opportunity::create(['title' => 'Outro', 'client_name' => 'Outro', 'stage' => 'briefing']);
        $run = $other->aiRuns()->create(['action' => 'briefing_analysis', 'provider' => 'demo', 'prompt_version' => 'v2', 'status' => 'success', 'input_hash' => str_repeat('a', 64), 'input_text' => 'Texto']);
        $this->actingAs(User::factory()->create())->post("/opportunities/$o->id/briefing/review", ['revision' => 0, 'action' => 'accept', 'run_id' => $run->id, 'indices' => [0]])->assertNotFound();
    }
}
