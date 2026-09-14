<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\BriefFieldSource;
use App\Models\BriefProgramBlock;
use App\Models\BriefRequirement;
use App\Models\CaseContextEntry;
use App\Models\CaseContextSegment;
use App\Models\EventBrief;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class BriefSourceService
{
    public function confirm(Opportunity $case, User $actor, array $data): BriefFieldSource
    {
        $fields = array_map(fn ($key) => substr($key, 7), array_filter(array_keys(app(EventBriefService::class)->rules()), fn ($key) => substr_count($key, '.') === 1));
        $v = Validator::make($data, ['revision' => 'required|integer|min:0', 'field_path' => ['required', Rule::in($fields)], 'case_context_entry_id' => 'required|integer', 'segment_ids' => 'required|array|min:1|max:100', 'segment_ids.*' => 'integer|distinct|min:1'])->validate();

        return app(EventBriefService::class)->changeChildren($case, $actor, $v['revision'], 'source_confirmed', function (EventBrief $brief) use ($case, $actor, $v) {
            $entry = CaseContextEntry::where('opportunity_id', $case->id)->whereKey($v['case_context_entry_id'])->lockForUpdate()->firstOrFail();
            $segments = $entry->segments()->whereIn('id', $v['segment_ids'])->get();
            if ($segments->count() !== count($v['segment_ids'])) {
                throw ValidationException::withMessages(['segment_ids' => 'Selecione apenas trechos desta gravação.']);
            }

            return BriefFieldSource::create(['event_brief_id' => $brief->id, 'field_path' => $v['field_path'], 'case_context_entry_id' => $entry->id, 'segment_ids' => $v['segment_ids'],
                'source_snapshot' => $segments->map(fn ($s) => $s->only(['id', 'start_ms', 'end_ms', 'speaker_key', 'speaker_name', 'text']))->all(), 'transcript_revision' => $entry->revision, 'field_value_hash' => $this->hash($brief, $v['field_path']),
                'confirmed_by' => $actor->id, 'confirmed_at' => now(), 'extracted_at' => now()]);
        });
    }

    public function hash(EventBrief $brief, string $field): string
    {
        return hash('sha256', json_encode($brief->getAttribute($field)));
    }

    public function approved(EventBrief $brief): void
    {
        foreach (BriefFieldSource::where('event_brief_id', $brief->id)->whereNull('approved_at')->get() as $source) {
            if ($source->field_value_hash === $this->hash($brief, $source->field_path)) {
                $source->update(['approved_at' => now()]);
            }
        }
        $ids = [];
        foreach ([BriefRequirement::class, BriefProgramBlock::class] as $model) {
            foreach ($model::where('event_brief_id', $brief->id)->get() as $item) {
                $ids = array_merge($ids, $item->evidence_segment_ids ?? []);
            }
        }
        $entries = CaseContextSegment::whereIn('id', array_unique($ids))->whereHas('entry', fn ($q) => $q->where('opportunity_id', $brief->opportunity_id))->distinct()->pluck('case_context_entry_id');
        foreach ($entries as $id) {
            DB::table('brief_retained_contexts')->insertOrIgnore(['event_brief_id' => $brief->id, 'case_context_entry_id' => $id, 'approved_revision' => $brief->revision + 1, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function retained(CaseContextEntry $entry): bool
    {
        return $entry->retain_forever || BriefFieldSource::where('case_context_entry_id', $entry->id)->whereNotNull('approved_at')->exists() || DB::table('brief_retained_contexts')->where('case_context_entry_id', $entry->id)->exists();
    }

    public function describe(EventBrief $brief): array
    {
        return BriefFieldSource::where('event_brief_id', $brief->id)->latest('id')->get()->map(fn ($s) => [...$s->toArray(), 'value_changed' => $s->field_value_hash !== $this->hash($brief, $s->field_path), 'audio_url' => "/opportunities/{$brief->opportunity_id}/context/{$s->case_context_entry_id}/audio"])->all();
    }

    public function metadata(Opportunity $case, CaseContextEntry $entry, User $actor, array $data): void
    {
        abort_unless($entry->opportunity_id === $case->id, 404);
        $v = Validator::make($data, ['revision' => 'required|integer|min:0', 'title' => 'nullable|string|max:200', 'meeting_date' => 'nullable|date', 'meeting_kind' => ['nullable', Rule::in(['briefing', 'alinhamento', 'tecnica', 'comercial', 'retrospectiva', 'outra'])], 'retain_forever' => 'required|boolean', 'participants' => 'array|max:100', 'participants.*' => 'string|max:160'])->validate();
        DB::transaction(function () use ($case, $entry, $actor, $v) {
            $entry = CaseContextEntry::whereKey($entry->id)->lockForUpdate()->firstOrFail();
            if ($entry->revision !== $v['revision']) {
                throw ValidationException::withMessages(['revision' => 'Os dados da reunião mudaram. Atualize antes de salvar.']);
            }$v['revision']++;
            $entry->update($v);
            AuditLog::create(['user_id' => $actor->id, 'action' => 'context.metadata_updated', 'subject_type' => Opportunity::class, 'subject_id' => $case->id, 'metadata' => ['entry_id' => $entry->id, 'revision' => $entry->revision]]);
        });
    }
}
