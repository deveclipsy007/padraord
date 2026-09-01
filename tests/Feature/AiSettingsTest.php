<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function settings(array $extra = []): array
    {
        return array_replace(['password' => 'password', 'mode' => 'openai', 'credential_source' => 'settings', 'api_key' => 'secret-for-test-only', 'monthly_usd' => '10', 'processing_usd' => '0.5', 'input_price' => '0.15', 'output_price' => '0.60', 'policy_approved' => true, 'retention_days' => 30], $extra);
    }

    public function test_only_administrators_can_read_or_change_settings(): void
    {
        $this->actingAs(User::factory()->create())->get('/settings/ai')->assertForbidden();
        $this->post('/settings/ai', $this->settings())->assertForbidden();
    }

    public function test_key_is_encrypted_and_never_returned_and_password_is_required(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->post('/settings/ai', $this->settings(['password' => 'wrong']))->assertSessionHasErrors('password');
        $this->assertNull(session()->get('_old_input.api_key'));
        $this->post('/settings/ai', $this->settings())->assertRedirect();
        $this->assertNotSame('secret-for-test-only', DB::table('ai_settings')->value('api_key'));
        $this->get('/settings/ai')->assertDontSee('secret-for-test-only')->assertInertia(fn ($page) => $page->component('AiSettings')->where('settings.status', 'ready')->where('settings.has_key', true));
        $this->assertStringNotContainsString('secret-for-test-only', DB::table('audit_logs')->get()->toJson());
    }

    public function test_removing_key_does_not_fall_back_to_environment(): void
    {
        config(['services.openai.api_key' => 'environment-secret']);
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->post('/settings/ai', $this->settings())->assertRedirect();
        $this->post('/settings/ai', $this->settings(['api_key' => '', 'remove_key' => true]))->assertRedirect();
        $this->get('/settings/ai')->assertInertia(fn ($p) => $p->where('settings.status', 'missing_key'));
    }

    public function test_no_paid_test_without_limits(): void
    {
        Http::fake();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->post('/settings/ai', $this->settings(['monthly_usd' => '0', 'processing_usd' => '0']))->assertRedirect();
        $this->post('/settings/ai/test', ['password' => 'password'])->assertSessionHasErrors('ai');
        Http::assertNothingSent();
    }
}
