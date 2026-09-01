<?php

namespace Tests\Feature;

use App\AI\NullAiProvider;
use App\Jobs\AnalyzeBriefing;
use App\Models\BriefingMessage;
use App\Models\Opportunity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyzeBriefingJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_late_failure_does_not_undo_human_approval(): void
    {
        $o = Opportunity::create(['title' => 'Evento', 'client_name' => 'Cliente', 'stage' => 'briefing', 'briefing_status' => 'complete']);
        $m = $o->briefingMessages()->create(['role' => 'user', 'source' => 'manual', 'body' => 'Contexto']);
        (new AnalyzeBriefing($m->id))->handle(new NullAiProvider);
        $this->assertSame('complete', $o->fresh()->briefing_status);
    }

    public function test_ai_outage_is_recorded_and_manual_editing_remains_available(): void
    {
        $opportunity = Opportunity::create([
            'title' => 'Lançamento Horizonte',
            'client_name' => 'Horizonte',
            'stage' => 'briefing',
            'briefing_status' => 'processing',
        ]);
        $message = BriefingMessage::create([
            'opportunity_id' => $opportunity->id,
            'role' => 'user',
            'source' => 'manual',
            'body' => 'Evento para 300 convidados.',
        ]);

        $job = new AnalyzeBriefing($message->id);
        $job->handle(new NullAiProvider);
        $job->handle(new NullAiProvider);

        $this->assertDatabaseHas('ai_runs', [
            'briefing_message_id' => $message->id,
            'status' => 'unavailable',
        ]);
        $this->assertDatabaseHas('opportunities', [
            'id' => $opportunity->id,
            'briefing_status' => 'manual',
        ]);
        $this->assertDatabaseCount('ai_runs', 1);
        $this->assertDatabaseCount('briefing_messages', 1);
    }
}
