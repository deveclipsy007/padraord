<?php

namespace Tests\Feature;

use App\Enums\OpportunityStage;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpportunityTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_an_opportunity_with_the_first_pipeline_stage(): void
    {
        $response = $this->actingAs(User::factory()->create())->post('/opportunities', [
            'title' => 'Conferência Horizonte 2026',
            'client_name' => 'Horizonte Energia',
            'contact_name' => 'Marina Costa',
            'contact_email' => 'marina@example.com',
        ]);

        $response->assertRedirect('/');
        $this->assertDatabaseHas('opportunities', [
            'title' => 'Conferência Horizonte 2026',
            'client_name' => 'Horizonte Energia',
            'stage' => OpportunityStage::LEAD->value,
        ]);
        $this->assertInstanceOf(Opportunity::class, Opportunity::query()->first());
    }
}
