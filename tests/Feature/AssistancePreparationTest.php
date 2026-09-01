<?php

namespace Tests\Feature;

use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssistancePreparationTest extends TestCase
{
    use RefreshDatabase;

    public function test_preparation_requires_review_and_is_idempotent(): void
    {
        $o = Opportunity::create(['title' => 'Evento', 'client_name' => 'Cliente', 'stage' => 'briefing']);
        $this->actingAs(User::factory()->create());
        $this->post("/opportunities/$o->id/prepare", ['revision' => 0])->assertSessionHasErrors('briefing');
        $o->update(['briefing_status' => 'complete', 'briefing_revision' => 1, 'briefing_data' => ['scope' => "Palco\nCredenciamento", 'objective' => 'Reunir equipe'], 'briefing_approval' => ['revision' => 1, 'fields' => ['scope' => "Palco\nCredenciamento", 'objective' => 'Reunir equipe']]]);
        $this->post("/opportunities/$o->id/prepare", ['revision' => 1])->assertRedirect();
        $this->post("/opportunities/$o->id/prepare", ['revision' => 1])->assertRedirect();
        $this->assertDatabaseCount('assistance_drafts', 1);
        $this->assertDatabaseCount('activities', 1);
        $this->assertDatabaseCount('documents', 0);
        $this->assertDatabaseCount('budget_items', 0);
        $this->get("/opportunities/$o->id")->assertInertia(fn ($p) => $p->has('nextStep')->has('preparation')->where('preparation.stale', false));
        $o->update(['briefing_revision' => 2]);
        $this->get("/opportunities/$o->id")->assertInertia(fn ($p) => $p->where('preparation.stale', true));
    }
}
