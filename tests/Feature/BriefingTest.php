<?php

namespace Tests\Feature;

use App\Jobs\AnalyzeBriefing;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class BriefingTest extends TestCase
{
    use RefreshDatabase;

    public function test_briefing_workspace_preserves_the_conversation_shape_for_inertia(): void
    {
        $opportunity = Opportunity::create([
            'title' => 'Jornada Aurora',
            'client_name' => 'Aurora Eventos',
            'stage' => 'briefing',
        ]);

        $response = $this->actingAs(User::factory()->create())->get(route('opportunities.briefing', $opportunity));

        $response->assertInertia(fn ($page) => $page
            ->component('Briefing')
            ->where('opportunity.title', 'Jornada Aurora')
            ->has('messages', 0));
    }

    public function test_a_conversational_briefing_message_is_saved_and_sent_to_the_queue(): void
    {
        config(['ai.mode' => 'demo']);
        Queue::fake();
        $opportunity = Opportunity::create([
            'title' => 'Festival Aurora',
            'client_name' => 'Aurora Eventos',
            'stage' => 'briefing',
            'briefing_status' => 'not_started',
        ]);

        $response = $this->actingAs(User::factory()->create())->post(route('opportunities.briefing.messages.store', $opportunity), [
            'body' => 'Precisamos de uma experiência para 800 pessoas em setembro, com palco e credenciamento.',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('briefing_messages', [
            'opportunity_id' => $opportunity->id,
            'role' => 'user',
            'source' => 'manual',
        ]);
        $this->assertDatabaseHas('opportunities', [
            'id' => $opportunity->id,
            'briefing_status' => 'processing',
        ]);
        Queue::assertPushed(AnalyzeBriefing::class);
    }

    public function test_manual_input_needs_no_worker_and_does_not_call_ai(): void
    {
        config(['ai.mode' => 'manual']);
        Queue::fake();
        $o = Opportunity::create(['title' => 'Manual', 'client_name' => 'Equipe', 'stage' => 'briefing']);
        $this->actingAs(User::factory()->create())->post("/opportunities/$o->id/briefing/messages", ['body' => 'Contexto original preservado'])->assertRedirect();
        $this->assertSame('manual', $o->fresh()->briefing_status);
        Queue::assertNothingPushed();
    }
}
