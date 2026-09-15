<?php

namespace Tests\Feature;

use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PipelineOperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_bulk_priority_updates_only_the_selected_current_cases_and_preserves_an_audit_per_case(): void
    {
        $user = User::factory()->create();
        $first = Opportunity::create(['title' => 'Primeiro caso', 'client_name' => 'Cliente A', 'stage' => 'lead', 'owner_id' => $user->id, 'priority' => 'normal']);
        $second = Opportunity::create(['title' => 'Segundo caso', 'client_name' => 'Cliente B', 'stage' => 'lead', 'owner_id' => $user->id, 'priority' => 'low']);
        $unselected = Opportunity::create(['title' => 'Caso fora da seleção', 'client_name' => 'Cliente C', 'stage' => 'lead', 'owner_id' => $user->id, 'priority' => 'normal']);

        $this->actingAs($user)->post('/pipeline/bulk-priority', [
            'opportunity_ids' => [$first->id, $second->id],
            'revisions' => [$first->id => 0, $second->id => 0],
            'priority' => 'high',
        ])->assertRedirect();

        $this->assertDatabaseHas('opportunities', ['id' => $first->id, 'priority' => 'high', 'commercial_revision' => 1]);
        $this->assertDatabaseHas('opportunities', ['id' => $second->id, 'priority' => 'high', 'commercial_revision' => 1]);
        $this->assertDatabaseHas('opportunities', ['id' => $unselected->id, 'priority' => 'normal', 'commercial_revision' => 0]);
        $this->assertDatabaseCount('audit_logs', 2);
        $this->assertDatabaseHas('audit_logs', ['action' => 'opportunity.priority_changed', 'subject_id' => $first->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'opportunity.priority_changed', 'subject_id' => $second->id]);
    }

    public function test_bulk_priority_refuses_to_overwrite_a_case_that_changed_after_selection(): void
    {
        $user = User::factory()->create();
        $case = Opportunity::create(['title' => 'Caso alterado', 'client_name' => 'Cliente', 'stage' => 'lead', 'owner_id' => $user->id, 'priority' => 'normal', 'commercial_revision' => 2]);

        $this->actingAs($user)->post('/pipeline/bulk-priority', [
            'opportunity_ids' => [$case->id],
            'revisions' => [$case->id => 0],
            'priority' => 'high',
        ])->assertSessionHasErrors('revisions');

        $this->assertDatabaseHas('opportunities', ['id' => $case->id, 'priority' => 'normal', 'commercial_revision' => 2]);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_bulk_priority_undo_restores_each_previous_value_only_when_the_batch_is_still_current(): void
    {
        $user = User::factory()->create();
        $first = Opportunity::create(['title' => 'Primeiro caso', 'client_name' => 'Cliente A', 'stage' => 'lead', 'owner_id' => $user->id, 'priority' => 'high', 'commercial_revision' => 1]);
        $second = Opportunity::create(['title' => 'Segundo caso', 'client_name' => 'Cliente B', 'stage' => 'lead', 'owner_id' => $user->id, 'priority' => 'high', 'commercial_revision' => 1]);

        $this->actingAs($user)->post('/pipeline/bulk-priority/undo', [
            'changes' => [
                ['id' => $first->id, 'priority' => 'normal', 'revision' => 1],
                ['id' => $second->id, 'priority' => 'low', 'revision' => 1],
            ],
        ])->assertRedirect();

        $this->assertDatabaseHas('opportunities', ['id' => $first->id, 'priority' => 'normal', 'commercial_revision' => 2]);
        $this->assertDatabaseHas('opportunities', ['id' => $second->id, 'priority' => 'low', 'commercial_revision' => 2]);
        $this->assertDatabaseCount('audit_logs', 2);
        $this->assertDatabaseHas('audit_logs', ['action' => 'opportunity.priority_changed', 'subject_id' => $first->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'opportunity.priority_changed', 'subject_id' => $second->id]);
    }
}
