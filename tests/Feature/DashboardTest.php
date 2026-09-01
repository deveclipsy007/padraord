<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_renders_the_operational_dashboard(): void
    {
        $response = $this->actingAs(User::factory()->create())->get('/');

        $response->assertOk()
            ->assertSee('script data-page="app" type="application/json"', false)
            ->assertSee('<div id="app"></div>', false)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->has('opportunities')
                ->has('columns')
                ->has('metrics.activeOpportunities'));
    }
}
