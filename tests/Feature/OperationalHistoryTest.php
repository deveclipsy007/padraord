<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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

    public function test_case_history_includes_related_module_audits(): void
    {
        $user = User::factory()->create();
        $case = Opportunity::create(['title' => 'Caso', 'client_name' => 'Cliente', 'stage' => 'lead']);
        AuditLog::create([
            'user_id' => $user->id,
            'subject_type' => 'technical_validations',
            'subject_id' => 17,
            'action' => 'production.technical_validation_confirmed',
            'metadata' => ['opportunity_id' => $case->id, 'evidence' => 'Visita técnica'],
            'created_at' => Carbon::now()->subMinute(),
            'updated_at' => Carbon::now()->subMinute(),
        ]);
        AuditLog::create([
            'user_id' => $user->id,
            'subject_type' => Opportunity::class,
            'subject_id' => $case->id,
            'action' => 'opportunity.updated',
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        $this->actingAs($user)->get('/opportunities/'.$case->id.'/history')->assertInertia(fn ($page) => $page
            ->has('records', 2)
            ->where('records.0.action', 'opportunity.updated')
            ->where('records.1.action', 'production.technical_validation_confirmed'));
    }
}
