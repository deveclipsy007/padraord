<?php

namespace Tests\Feature;

use App\Models\BriefFieldSource;
use App\Models\CaseContextEntry;
use App\Models\CaseContextSegment;
use App\Models\ContextAudioAsset;
use App\Models\Opportunity;
use App\Models\User;
use App\Services\BriefRequirementService;
use App\Services\BriefSourceService;
use App\Services\EventBriefService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BriefSourcesTest extends TestCase
{
    use RefreshDatabase;

    public function test_approval_preserves_recordings_used_in_fields_and_requirements_after_reopening(): void
    {
        $actor = User::factory()->create();
        $case = Opportunity::create(['title' => 'Congresso', 'client_name' => 'Cliente']);
        $entry = CaseContextEntry::create(['opportunity_id' => $case->id, 'user_id' => $actor->id, 'kind' => 'audio', 'phase' => 'briefing', 'status' => 'review_ready', 'digest' => str_repeat('d', 64)]);
        $segment = CaseContextSegment::create(['case_context_entry_id' => $entry->id, 'sequence' => 0, 'speaker_key' => 'A', 'start_ms' => 8500, 'end_ms' => 12000, 'text' => 'Reunir parceiros com acesso inclusivo']);
        $service = app(EventBriefService::class);
        $service->mutate($case, $actor, 0, ['event_type' => 'congresso', 'starts_at' => '2026-10-10T09:00', 'ends_at' => '2026-10-10T18:00', 'objective' => 'Reunir parceiros', 'scope_summary' => 'Plenária', 'audience_expected_max' => 100, 'audience_profile' => 'Parceiros', 'budget_declared_cents' => 1000000, 'location_note' => 'Centro', 'success_criteria' => [['metric' => 'Participantes', 'target' => '100']]]);
        app(BriefRequirementService::class)->save($case, $actor, null, ['revision' => 1, 'area' => 'acessibilidade', 'requirement' => 'Rota acessível', 'quantity' => 1, 'unit' => 'serviço', 'priority' => 'obrigatorio', 'classification' => 'fact', 'source' => 'Cliente', 'evidence_segment_ids' => [$segment->id]]);
        $source = app(BriefSourceService::class)->confirm($case, $actor, ['revision' => 2, 'field_path' => 'objective', 'case_context_entry_id' => $entry->id, 'segment_ids' => [$segment->id]]);
        $service->approve($case, $actor, 3);
        $this->assertNotNull($source->fresh()->approved_at);
        $this->assertDatabaseHas('brief_retained_contexts', ['case_context_entry_id' => $entry->id, 'approved_revision' => 4]);
        $service->reopen($case, $actor, 4, 'Ajustar a programação');
        $this->assertTrue(app(BriefSourceService::class)->retained($entry));
    }

    public function test_confirmed_source_keeps_exact_time_and_transcript_after_other_fields_change(): void
    {
        $actor = User::factory()->create();
        $this->actingAs($actor);
        $case = Opportunity::create(['title' => 'Evento', 'client_name' => 'Cliente']);
        $entry = CaseContextEntry::create(['opportunity_id' => $case->id, 'user_id' => $actor->id, 'kind' => 'audio', 'phase' => 'briefing', 'status' => 'review_ready', 'digest' => str_repeat('a', 64)]);
        $segment = CaseContextSegment::create(['case_context_entry_id' => $entry->id, 'sequence' => 0, 'speaker_key' => 'A', 'start_ms' => 8500, 'end_ms' => 12000, 'text' => 'Queremos reunir parceiros']);
        app(EventBriefService::class)->mutate($case, $actor, 0, ['objective' => 'Reunir parceiros']);
        $this->post("/opportunities/{$case->id}/event-brief/sources", ['revision' => 1, 'field_path' => 'objective', 'case_context_entry_id' => $entry->id, 'segment_ids' => [$segment->id]])->assertRedirect()->assertSessionDoesntHaveErrors();
        $source = BriefFieldSource::firstOrFail();
        app(EventBriefService::class)->mutate($case, $actor, 2, ['event_name' => 'Evento atualizado']);
        $this->assertSame(8500, $source->fresh()->source_snapshot[0]['start_ms']);
        $segment->update(['text' => 'Transcrição corrigida']);
        $this->assertSame('Queremos reunir parceiros', $source->fresh()->source_snapshot[0]['text']);
        $this->get("/opportunities/{$case->id}/briefing")->assertInertia(fn ($p) => $p->where('briefSources.0.field_path', 'objective')->where('briefSources.0.value_changed', false));
    }

    public function test_source_must_belong_to_case_and_selected_recording(): void
    {
        $actor = User::factory()->create();
        $this->actingAs($actor);
        $case = Opportunity::create(['title' => 'Evento', 'client_name' => 'Cliente']);
        $other = Opportunity::create(['title' => 'Outro', 'client_name' => 'Outro']);
        $entry = CaseContextEntry::create(['opportunity_id' => $other->id, 'user_id' => $actor->id, 'kind' => 'audio', 'phase' => 'briefing', 'status' => 'review_ready', 'digest' => str_repeat('b', 64)]);
        $segment = CaseContextSegment::create(['case_context_entry_id' => $entry->id, 'sequence' => 0, 'speaker_key' => 'A', 'start_ms' => 0, 'end_ms' => 1000, 'text' => 'Outro caso']);
        $this->post("/opportunities/{$case->id}/event-brief/sources", ['revision' => 0, 'field_path' => 'objective', 'case_context_entry_id' => $entry->id, 'segment_ids' => [$segment->id]])->assertNotFound();
        $this->assertDatabaseCount('brief_field_sources', 0);
    }

    public function test_purge_preserves_audio_used_by_an_approved_brief_and_it_remains_playable(): void
    {
        Storage::fake('local');
        $actor = User::factory()->create();
        $this->actingAs($actor);
        $case = Opportunity::create(['title' => 'Evento', 'client_name' => 'Cliente']);
        $path = 'context-audio/original/11111111-1111-4111-8111-111111111111.wav';
        $entry = CaseContextEntry::create(['opportunity_id' => $case->id, 'user_id' => $actor->id, 'kind' => 'audio', 'phase' => 'briefing', 'status' => 'review_ready', 'path' => $path, 'digest' => str_repeat('c', 64), 'expires_at' => now()->subDay()]);
        Storage::disk('local')->put($path, 'audio');
        ContextAudioAsset::create(['case_context_entry_id' => $entry->id, 'original_path' => $path, 'original_mime' => 'audio/wav', 'original_bytes' => 5, 'duration_ms' => 1000, 'digest' => str_repeat('c', 64), 'status' => 'review_ready']);
        $segment = CaseContextSegment::create(['case_context_entry_id' => $entry->id, 'sequence' => 0, 'speaker_key' => 'A', 'start_ms' => 0, 'end_ms' => 1000, 'text' => 'Reunir parceiros']);
        $brief = app(EventBriefService::class)->ensure($case);
        $source = BriefFieldSource::create(['event_brief_id' => $brief->id, 'field_path' => 'objective', 'case_context_entry_id' => $entry->id, 'segment_ids' => [$segment->id], 'source_snapshot' => [$segment->toArray()], 'field_value_hash' => hash('sha256', 'null'), 'confirmed_by' => $actor->id, 'confirmed_at' => now(), 'extracted_at' => now(), 'approved_at' => now()]);
        $this->artisan('context:purge-expired-audio')->assertSuccessful();
        Storage::disk('local')->assertExists($path);
        $this->get("/opportunities/{$case->id}/context/{$entry->id}/audio")->assertOk();
        $this->assertDatabaseHas('audit_logs', ['action' => 'context.audio.retained']);
    }
}
