<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorizationMatrixTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_administrator_can_grant_commercial_authority_after_password_confirmation(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $producer = User::factory()->create(['role' => 'producer']);

        $response = $this->actingAs($admin)->post("/team/{$producer->id}/commercial-authority", [
            'password' => 'password',
            'enabled' => true,
            'reason' => 'Responsável pela revisão comercial do piloto.',
        ]);

        $response->assertRedirect()->assertSessionHasNoErrors();
        $this->assertTrue($producer->fresh()->can_approve_commercial);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'user.commercial_authority_changed',
            'subject_id' => $producer->id,
        ]);
    }

    public function test_authority_change_requires_current_password_reason_and_admin(): void
    {
        $producer = User::factory()->create(['role' => 'producer']);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($producer)
            ->post("/team/{$admin->id}/commercial-authority", ['enabled' => true, 'reason' => 'Tentativa'])
            ->assertForbidden();

        $this->actingAs($admin)
            ->post("/team/{$producer->id}/commercial-authority", ['password' => 'wrong', 'enabled' => true, 'reason' => ''])
            ->assertSessionHasErrors(['password', 'reason']);

        $this->assertFalse($producer->fresh()->can_approve_commercial);
    }

    public function test_login_is_rate_limited_after_five_failed_attempts_for_same_identity(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/login', ['email' => 'nobody@example.com', 'password' => 'wrong-password'])
                ->assertSessionHasErrors('email');
        }

        $response = $this->post('/login', ['email' => 'nobody@example.com', 'password' => 'wrong-password']);

        $this->assertSame(429, $response->status());
    }

    public function test_producer_cannot_open_team_or_ai_configuration(): void
    {
        $producer = User::factory()->create(['role' => 'producer']);

        $this->actingAs($producer)->get('/team')->assertForbidden();
        $this->actingAs($producer)->get('/settings/ai')->assertForbidden();
    }
}
