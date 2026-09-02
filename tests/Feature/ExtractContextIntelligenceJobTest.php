<?php

namespace Tests\Feature;

use App\Contracts\ContextIntelligenceExtractor;
use App\Jobs\ExtractContextIntelligence;
use App\Models\AiSetting;
use App\Models\AssistantPreview;
use App\Models\CaseContextEntry;
use App\Models\CaseContextSegment;
use App\Models\ContextAudioAsset;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ExtractContextIntelligenceJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_transcript_becomes_one_reviewable_preview_with_segment_evidence_and_no_silent_mutation(): void
    {
        AiSetting::create([
            'id' => 1, 'mode' => 'openai', 'credential_source' => 'settings', 'api_key' => 'secret',
            'policy_approved' => true, 'monthly_micros' => 10_000_000, 'processing_micros' => 2_000_000,
            'input_price' => 200_000, 'output_price' => 1_200_000,
        ]);
        $user = User::factory()->create();
        $opportunity = Opportunity::create(['title' => 'Evento', 'client_name' => 'Cliente', 'stage' => 'briefing']);
        $entry = CaseContextEntry::create([
            'opportunity_id' => $opportunity->id, 'user_id' => $user->id, 'kind' => 'audio',
            'phase' => 'briefing', 'status' => 'transcribed', 'path' => 'private.wav', 'digest' => str_repeat('d', 64),
        ]);
        $segment = CaseContextSegment::create([
            'case_context_entry_id' => $entry->id, 'sequence' => 0, 'speaker_key' => 'A',
            'start_ms' => 0, 'end_ms' => 3000, 'text' => 'O objetivo é integrar a liderança.',
        ]);
        $asset = ContextAudioAsset::create([
            'case_context_entry_id' => $entry->id, 'original_path' => 'private.wav', 'prepared_path' => 'private.wav',
            'original_mime' => 'audio/wav', 'prepared_mime' => 'audio/wav', 'original_bytes' => 10,
            'prepared_bytes' => 10, 'duration_ms' => 3000, 'digest' => str_repeat('d', 64), 'status' => 'transcribed',
        ]);
        $payload = [
            'summary' => 'Reunião de briefing do evento.',
            'participants' => [['speaker_id' => 'A', 'display_name' => null]],
            'facts' => [['key' => 'briefing.objective', 'value' => 'Integrar a liderança', 'classification' => 'fact', 'evidence_segment_ids' => [$segment->id]]],
            'decisions' => [], 'open_questions' => [], 'risks' => [], 'constraints' => [],
            'budget_mentions' => [], 'supplier_mentions' => [], 'action_items' => [],
            'module_changes' => [
                'case' => [],
                'briefing' => [['field' => 'objective', 'suggested' => 'Integrar a liderança', 'reason' => 'Dito explicitamente.', 'classification' => 'fact', 'evidence_segment_ids' => [$segment->id]]],
                'viability' => [], 'budget' => [], 'documents' => [], 'production' => [], 'post_event' => [],
            ],
        ];
        Http::fake(['api.openai.com/v1/chat/completions' => Http::response([
            'choices' => [['message' => ['content' => json_encode($payload)]]],
            'usage' => ['prompt_tokens' => 120, 'completion_tokens' => 80],
        ], 200, ['x-request-id' => 'req_extract_123'])]);

        $job = new ExtractContextIntelligence($entry->id);
        $job->handle(app(ContextIntelligenceExtractor::class));
        $job->handle(app(ContextIntelligenceExtractor::class));

        $this->assertNull($opportunity->fresh()->briefing_data);
        $this->assertSame('review_ready', $entry->fresh()->status);
        $this->assertSame('review_ready', $asset->fresh()->status);
        $this->assertDatabaseCount('assistant_previews', 1);
        $this->assertDatabaseHas('ai_runs', ['opportunity_id' => $opportunity->id, 'action' => 'context_intelligence', 'status' => 'success']);
        $this->assertDatabaseHas('ai_cost_entries', ['case_context_entry_id' => $entry->id, 'operation' => 'extraction', 'status' => 'reported']);
        $this->assertDatabaseHas('odoo_cost_outbox', ['case_context_entry_id' => $entry->id, 'status' => 'pending']);
        $preview = AssistantPreview::firstOrFail();
        $this->assertSame($segment->id, $preview->actions[0]['evidence_segment_ids'][0]);
        $this->actingAs($user)->post("/opportunities/{$opportunity->id}/context/{$entry->id}/preview/{$preview->id}/confirm", [
            'modules' => ['briefing'],
        ])->assertSessionHasNoErrors();
        $this->assertSame('Integrar a liderança', $opportunity->fresh()->briefing_data['objective']);
        Http::assertSentCount(1);
    }

    public function test_cost_limit_preserves_transcript_and_returns_extraction_to_waiting(): void
    {
        AiSetting::create([
            'id' => 1, 'mode' => 'openai', 'credential_source' => 'settings', 'api_key' => 'secret',
            'policy_approved' => true, 'monthly_micros' => 1, 'processing_micros' => 1,
            'input_price' => 200_000, 'output_price' => 1_200_000,
        ]);
        $opportunity = Opportunity::create(['title' => 'Evento', 'client_name' => 'Cliente', 'stage' => 'briefing']);
        $entry = CaseContextEntry::create([
            'opportunity_id' => $opportunity->id, 'user_id' => User::factory()->create()->id, 'kind' => 'audio',
            'phase' => 'briefing', 'status' => 'transcribed', 'path' => 'private.wav', 'digest' => str_repeat('f', 64),
        ]);
        CaseContextSegment::create([
            'case_context_entry_id' => $entry->id, 'sequence' => 0, 'speaker_key' => 'A',
            'start_ms' => 0, 'end_ms' => 3000, 'text' => 'O evento será em novembro.',
        ]);
        $asset = ContextAudioAsset::create([
            'case_context_entry_id' => $entry->id, 'original_path' => 'private.wav', 'prepared_path' => 'private.wav',
            'original_mime' => 'audio/wav', 'prepared_mime' => 'audio/wav', 'original_bytes' => 10,
            'prepared_bytes' => 10, 'duration_ms' => 3000, 'digest' => str_repeat('f', 64), 'status' => 'transcribed',
        ]);

        (new ExtractContextIntelligence($entry->id))->handle(app(ContextIntelligenceExtractor::class));

        $this->assertSame('waiting', $entry->fresh()->status);
        $this->assertSame('transcribed', $asset->fresh()->status);
        $this->assertSame('cost_limit', $asset->fresh()->error_code);
        $this->assertDatabaseCount('assistant_previews', 0);
        $this->assertDatabaseCount('ai_cost_entries', 0);
        Http::assertNothingSent();
    }

    public function test_demo_mode_creates_the_same_reviewable_context_preview_without_network_access(): void
    {
        AiSetting::create([
            'id' => 1,
            'mode' => 'demo',
            'credential_source' => 'settings',
            'monthly_micros' => 10_000_000,
            'processing_micros' => 2_000_000,
            'input_price' => 200_000,
            'output_price' => 1_200_000,
        ]);
        $user = User::factory()->create();
        $opportunity = Opportunity::create(['title' => 'Evento', 'client_name' => 'Cliente', 'stage' => 'briefing']);
        $entry = CaseContextEntry::create([
            'opportunity_id' => $opportunity->id,
            'user_id' => $user->id,
            'kind' => 'audio',
            'phase' => 'briefing',
            'status' => 'transcribed',
            'path' => 'private.wav',
            'digest' => str_repeat('e', 64),
        ]);
        $segment = CaseContextSegment::create([
            'case_context_entry_id' => $entry->id,
            'sequence' => 0,
            'speaker_key' => 'A',
            'start_ms' => 0,
            'end_ms' => 1000,
            'text' => 'Objetivo: integrar a liderança.',
        ]);

        (new ExtractContextIntelligence($entry->id))->handle(app(ContextIntelligenceExtractor::class));

        $preview = AssistantPreview::firstOrFail();
        $this->assertSame('demo', $preview->mode);
        $this->assertSame($segment->id, $preview->actions[0]['evidence_segment_ids'][0]);
        Http::assertNothingSent();
    }
}
