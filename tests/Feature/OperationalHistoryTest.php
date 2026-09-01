<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationalHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_history_lists_audit_decisions_and_filters_by_case(): void
    {
        $user = User::factory()->create();
        $case = Opportunity::create(['title' => 'Caso', 'client_name' => 'Cliente', 'stage' => 'lead']);
        AuditLog::create(['user_id' => $user->id, 'subject_type' => Opportunity::class, 'subject_id' => $case->id, 'action' => 'journey.contract_viability', 'metadata' => ['mode' => 'demo']]);
        $this->actingAs($user)->get('/history')->assertOk()->assertInertia(fn ($page) => $page->has('records', 1)->where('records.0.action', 'journey.contract_viability'));
        $this->get('/opportunities/'.$case->id.'/history')->assertOk()->assertInertia(fn ($page) => $page->has('records', 1));
    }
}
