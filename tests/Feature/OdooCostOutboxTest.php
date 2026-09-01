<?php

namespace Tests\Feature;

use App\Contracts\OdooCostExporter;
use App\Jobs\ExportAiCostToOdoo;
use App\Models\AiCostEntry;
use App\Models\CaseContextEntry;
use App\Models\Opportunity;
use App\Models\User;
use App\Services\OdooCostOutboxService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OdooCostOutboxTest extends TestCase
{
    use RefreshDatabase;

    public function test_reported_costs_are_aggregated_once_and_export_is_idempotent(): void
    {
        config([
            'odoo.mode' => 'json2', 'odoo.base_url' => 'https://odoo.example.test', 'odoo.api_key' => 'odoo-secret',
            'odoo.database' => 'padrao', 'odoo.model' => 'account.analytic.line', 'odoo.analytic_account_id' => 42,
            'odoo.currency' => 'USD', 'odoo.idempotency_field' => 'x_padrao_rd_idempotency_key',
        ]);
        Http::fakeSequence()
            ->push([], 200)
            ->push([501], 200);
        $opportunity = Opportunity::create(['title' => 'Convenção', 'client_name' => 'Cliente', 'stage' => 'briefing']);
        $entry = CaseContextEntry::create([
            'opportunity_id' => $opportunity->id, 'user_id' => User::factory()->create()->id,
            'kind' => 'audio', 'phase' => 'briefing', 'status' => 'review_ready', 'digest' => str_repeat('e', 64),
        ]);
        foreach ([90_000, 10_000] as $index => $amount) {
            AiCostEntry::create([
                'opportunity_id' => $opportunity->id, 'case_context_entry_id' => $entry->id,
                'operation' => $index ? 'extraction' : 'transcription', 'provider' => 'openai', 'model' => 'model',
                'estimated_amount_micros' => $amount, 'reported_amount_micros' => $amount,
                'currency' => 'USD', 'pricing_version' => 'v1', 'idempotency_key' => hash('sha256', "cost-{$index}"), 'status' => 'reported',
            ]);
        }

        $outbox = app(OdooCostOutboxService::class)->enqueue($entry);
        $same = app(OdooCostOutboxService::class)->enqueue($entry);
        $this->assertSame($outbox->id, $same->id);
        $this->assertSame(100_000, $outbox->payload['amount_micros']);

        $job = new ExportAiCostToOdoo($outbox->id);
        $job->handle(app(OdooCostExporter::class));
        $job->handle(app(OdooCostExporter::class));

        $outbox->refresh();
        $this->assertSame('exported', $outbox->status);
        $this->assertSame('501', $outbox->external_id);
        $this->assertSame(1, $outbox->attempts);
        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'bearer odoo-secret') && $request->hasHeader('X-Odoo-Database', 'padrao'));
    }
}
