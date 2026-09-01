<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_requires_the_configured_bearer_token(): void
    {
        config(['health.token' => 'health-test-token']);

        $this->getJson('/api/v1/health')->assertUnauthorized();
        $this->withHeader('Authorization', 'Bearer wrong-token')->getJson('/api/v1/health')->assertUnauthorized();
    }

    public function test_health_returns_safe_readiness_checks_and_request_id(): void
    {
        config(['health.token' => 'health-test-token']);

        $response = $this->withHeader('Authorization', 'Bearer health-test-token')
            ->withHeader('X-Request-Id', 'cycle01-health-request')
            ->getJson('/api/v1/health');

        $response->assertOk()
            ->assertHeader('X-Request-Id', 'cycle01-health-request')
            ->assertJsonStructure(['status', 'version', 'timestamp', 'checks' => ['database', 'storage', 'queue', 'mail', 'ai', 'odoo']])
            ->assertJsonMissing(['HEALTH_CHECK_TOKEN', 'health-test-token', 'DB_PASSWORD', 'OPENAI_API_KEY']);
    }
}
