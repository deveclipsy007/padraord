<?php

namespace Tests\Feature;

use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CaseJourneyTest extends TestCase
{
    use RefreshDatabase;

    public function test_journey_map_includes_contract_and_finance_as_separate_stages(): void
    {
        $case = Opportunity::create(['title' => 'Jornada', 'client_name' => 'Cliente', 'stage' => 'lead']);
        $this->actingAs(User::factory()->create())->get("/opportunities/{$case->id}/journey")
            ->assertInertia(fn ($page) => $page
                ->where('moduleStatuses.5.key', 'contract')
                ->where('moduleStatuses.6.key', 'finance'));
    }

    public function test_viability_can_be_delivered_and_closed_without_management(): void
    {
        $case = Opportunity::create(['title' => 'Jornada', 'client_name' => 'Cliente', 'stage' => 'lead']);
        $this->actingAs(User::factory()->create());
        $path = "/opportunities/{$case->id}/journey";
        $this->post($path, ['action' => 'configure', 'revision' => 0, 'modality' => 'express', 'mode' => 'demo'])->assertSessionHasNoErrors();
        $this->post($path, ['action' => 'contract_viability', 'revision' => 1, 'evidence' => 'Aceite simulado'])->assertSessionHasNoErrors();
        $this->post($path, ['action' => 'deliver_viability', 'revision' => 2, 'evidence' => 'Entrega simulada', 'deliverables' => ['concept', 'estimate', 'suppliers', 'schedule']])->assertSessionHasNoErrors();
        $this->post($path, ['action' => 'accept_delivery', 'revision' => 3, 'evidence' => 'Aceite da entrega simulado'])->assertSessionHasNoErrors();
        $this->post($path, ['action' => 'close_viability', 'revision' => 4, 'evidence' => 'Cliente não contratou Gestão'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('case_journeys', ['opportunity_id' => $case->id, 'outcome' => 'viability_completed', 'management_status' => 'not_contracted']);
        $this->assertNotSame('lost', $case->fresh()->stage->value);
    }

    public function test_missing_evidence_and_stale_revision_do_not_advance_case(): void
    {
        $case = Opportunity::create(['title' => 'Jornada', 'client_name' => 'Cliente', 'stage' => 'lead']);
        $this->actingAs(User::factory()->create());
        $path = "/opportunities/{$case->id}/journey";
        $this->post($path, ['action' => 'configure', 'revision' => 0, 'modality' => 'complete', 'mode' => 'demo'])->assertSessionHasNoErrors();
        $this->post($path, ['action' => 'contract_viability', 'revision' => 1])->assertSessionHasErrors('evidence');
        $this->post($path, ['action' => 'contract_viability', 'revision' => 0, 'evidence' => 'Aceite'])->assertSessionHasErrors('revision');
        $this->assertDatabaseHas('case_journeys', ['opportunity_id' => $case->id, 'viability_status' => 'not_contracted']);
    }
}
