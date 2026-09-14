<?php

namespace App\Services;

use App\Enums\EventFormat;
use App\Enums\EventType;
use App\Models\AuditLog;
use App\Models\BriefProgramBlock;
use App\Models\BriefRequirement;
use App\Models\EventBrief;
use App\Models\Opportunity;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class EventBriefService
{
    public const LEGACY_MAP = ['objective' => 'objective', 'audience' => 'audience_profile', 'event_date' => 'legacy_date_note', 'location' => 'location_note', 'budget' => 'budget_notes', 'scope' => 'scope_summary', 'restrictions' => 'restrictions_notes', 'references' => 'references_notes'];

    public function ensure(Opportunity $case): EventBrief
    {
        return DB::transaction(function () use ($case) {
            $case = Opportunity::whereKey($case->id)->lockForUpdate()->firstOrFail();
            $brief = EventBrief::where('opportunity_id', $case->id)->first();
            if (! $brief) {
                $legacy = $case->getRawOriginal('briefing_data');
                $legacy = is_string($legacy) ? json_decode($legacy, true) : [];
                $legacy = $legacy ?? [];
                $data = ['opportunity_id' => $case->id, 'event_name' => $case->title, 'revision' => $case->briefing_revision, 'legacy_snapshot' => $legacy,
                    'objective' => $case->objective, 'location_note' => $case->location, 'legacy_date_note' => $case->event_date?->format('Y-m-d')];
                foreach (self::LEGACY_MAP as $old => $new) {
                    if (array_key_exists($old, $legacy)) {
                        $data[$new] = $legacy[$old];
                    }
                }
                $brief = EventBrief::create($data)->refresh();
            }
            $this->score($brief);
            if ($brief->isDirty()) {
                $brief->save();
            }

            return $brief;
        });
    }

    public function mutate(Opportunity $case, User $actor, int $revision, array $fields): EventBrief
    {
        return DB::transaction(function () use ($case, $actor, $revision, $fields) {
            $brief = $this->locked($case, $revision);
            if ($brief->status === 'approved') {
                $this->fail('approval', 'Reabra o briefing com uma justificativa antes de alterar a versão aprovada.');
            }
            $rules = $this->rules();
            $rules['fields'] = ['required', 'array:'.implode(',', array_map(fn ($k) => substr($k, 7), array_filter(array_keys($rules), fn ($k) => substr_count($k, '.') === 1)))];
            $validated = Validator::make(['fields' => $fields], $rules)->validate()['fields'];
            $timezone = $validated['timezone'] ?? $brief->timezone;
            foreach (['starts_at', 'ends_at', 'setup_starts_at', 'teardown_ends_at'] as $key) {
                if (array_key_exists($key, $validated) && $validated[$key]) {
                    $validated[$key] = Carbon::parse($validated[$key], $timezone)->utc();
                }
            }
            if (isset($validated['state'])) {
                $validated['state'] = mb_strtoupper($validated['state']);
            }
            if (($validated['previous_opportunity_id'] ?? null) === $case->id) {
                $this->fail('fields.previous_opportunity_id', 'O evento anterior deve ser outro caso.');
            }
            $brief->fill($validated);
            $this->validateRanges($brief);
            $this->score($brief);

            return $this->persist($brief, $actor, 'saved');
        }, 3);
    }

    public function approve(Opportunity $case, User $actor, int $revision): EventBrief
    {
        return DB::transaction(function () use ($case, $actor, $revision) {
            $brief = $this->locked($case, $revision);
            if ($brief->status === 'approved') {
                return $brief;
            }
            $this->score($brief);
            if ($brief->missing_critical) {
                $this->fail('approval', 'Complete os campos essenciais antes de aprovar: '.implode(', ', $brief->missing_critical).'.');
            }
            $brief->fill(['status' => 'approved', 'approved_at' => now(), 'approved_by' => $actor->id]);
            app(BriefSourceService::class)->approved($brief);

            return $this->persist($brief, $actor, 'approved');
        }, 3);
    }

    public function reopen(Opportunity $case, User $actor, int $revision, string $reason): EventBrief
    {
        return DB::transaction(function () use ($case, $actor, $revision, $reason) {
            $brief = $this->locked($case, $revision);
            if ($brief->status !== 'approved') {
                $this->fail('approval', 'Este briefing já está aberto para edição.');
            }
            $brief->fill(['status' => 'draft', 'approved_at' => null, 'approved_by' => null]);

            return $this->persist($brief, $actor, 'reopened', $reason);
        }, 3);
    }

    public function updateContext(Opportunity $case, User $actor, int $revision, array $fields): EventBrief
    {
        $mapped = [];
        foreach (self::LEGACY_MAP as $old => $new) {
            if (array_key_exists($old, $fields)) {
                $mapped[$new] = $fields[$old];
            }
        }

        return $this->mutate($case, $actor, $revision, $mapped);
    }

    public function changeChildren(Opportunity $case, User $actor, int $revision, string $action, \Closure $change): mixed
    {
        return DB::transaction(function () use ($case, $actor, $revision, $action, $change) {
            $brief = $this->locked($case, $revision);
            if ($brief->status === 'approved') {
                $this->fail('approval', 'Reabra o briefing antes de alterar seu conteúdo.');
            }
            $result = $change($brief);
            $this->score($brief);
            $this->persist($brief, $actor, $action);

            return $result;
        }, 3);
    }

    private function locked(Opportunity $case, int $revision): EventBrief
    {
        $brief = $this->ensure($case);
        if ($brief->revision !== $revision) {
            $this->fail('revision', 'O briefing mudou. Atualize e compare os dados antes de salvar; sua edição permanece no formulário.');
        }

        return $brief;
    }

    private function persist(EventBrief $brief, User $actor, string $action, ?string $reason = null): EventBrief
    {
        $brief->revision++;
        $brief->save();
        $snapshot = [...$brief->toArray(), 'requirements' => BriefRequirement::where('event_brief_id', $brief->id)->get()->toArray(), 'program' => BriefProgramBlock::where('event_brief_id', $brief->id)->orderBy('sequence')->get()->toArray(), 'sources' => app(BriefSourceService::class)->describe($brief)];
        DB::table('event_brief_revisions')->insert(['event_brief_id' => $brief->id, 'revision' => $brief->revision, 'action' => $action, 'snapshot' => json_encode($snapshot), 'user_id' => $actor->id, 'reason' => $reason, 'created_at' => now(), 'updated_at' => now()]);
        $case = $brief->opportunity;
        $changes = ['briefing_revision' => $brief->revision, 'briefing_status' => $brief->status === 'approved' ? 'complete' : 'awaiting_review'];
        if ($brief->status === 'approved') {
            $changes['briefing_approval'] = ['revision' => $brief->revision, 'fields' => $brief->contextFields(), 'event_brief_id' => $brief->id, 'user_id' => $actor->id, 'at' => now()->toISOString()];
        }
        $case->update($changes);
        AuditLog::create(['user_id' => $actor->id, 'action' => 'event_brief.'.$action, 'subject_type' => Opportunity::class, 'subject_id' => $case->id, 'metadata' => ['revision' => $brief->revision, 'reason' => $reason]]);

        return $brief;
    }

    private function validateRanges(EventBrief $b): void
    {
        if ($b->starts_at && $b->ends_at && $b->ends_at->lte($b->starts_at)) {
            $this->fail('fields.ends_at', 'O término precisa ser depois do início.');
        }
        if ($b->setup_starts_at && $b->starts_at && $b->setup_starts_at->gt($b->starts_at)) {
            $this->fail('fields.setup_starts_at', 'A montagem deve começar antes do evento.');
        }
        if ($b->teardown_ends_at && $b->ends_at && $b->teardown_ends_at->lt($b->ends_at)) {
            $this->fail('fields.teardown_ends_at', 'A desmontagem deve terminar depois do evento.');
        }
        $program = BriefProgramBlock::where('event_brief_id', $b->id);
        if ($program->exists() && (! $b->starts_at || ! $b->ends_at || $program->where(fn ($q) => $q->where('starts_at', '<', $b->starts_at)->orWhere('ends_at', '>', $b->ends_at))->exists())) {
            $this->fail('fields.ends_at', 'A nova janela deixaria blocos da programação fora do evento. Ajuste os blocos antes de reduzir o intervalo.');
        }
        foreach (['audience_expected' => 'audience_expected_max', 'budget_range' => 'budget_range_max_cents'] as $prefix => $field) {
            $min = $prefix === 'audience_expected' ? $b->audience_expected_min : $b->budget_range_min_cents;
            $max = $b->{$field};
            if ($min !== null && $max !== null && $min > $max) {
                $this->fail('fields.'.$field, 'O máximo não pode ser menor que o mínimo.');
            }
        }
    }

    private function known(mixed $value): bool
    {
        return filled($value) && ! is_string($value) || (is_string($value) && trim($value) !== '' && ! preg_match('/^(?:ainda\s+)?(?:a\s+(?:definir|confirmar)|pendente|n[aã]o\s+(?:informad[oa]|definid[oa])|desconhecid[oa]|tbd)[.!]?$/iu', trim($value)));
    }

    private function score(EventBrief $b): void
    {
        $checks = ['event_name' => [$this->known($b->event_name), 2], 'event_type' => [$this->known($b->event_type), 1], 'starts_at' => [(bool) $b->starts_at, 3], 'ends_at' => [(bool) $b->ends_at, 3],
            'objective' => [$this->known($b->objective), 3], 'scope_summary' => [$this->known($b->scope_summary), 3],
            'audience_expected_max' => [$b->audience_expected_max > 0, 2], 'audience_profile' => [$this->known($b->audience_profile), 1],
            'budget' => [$b->budget_declared_cents !== null || ($b->budget_range_min_cents !== null && $b->budget_range_max_cents !== null), 3],
            'location' => [$b->event_format === 'online' || $this->known($b->location_note) || (bool) $b->venue_id, 2],
            'success_criteria' => [(bool) $b->success_criteria, 1]];
        $b->missing_critical = array_keys(array_filter($checks, fn ($v) => ! $v[0]));
        $b->completeness_score = (int) floor(100 * array_sum(array_map(fn ($v) => $v[0] ? $v[1] : 0, $checks)) / array_sum(array_column($checks, 1)));
    }

    public function rules(): array
    {
        $r = ['fields.event_name' => 'sometimes|required|string|max:200', 'fields.event_type' => ['sometimes', 'nullable', Rule::enum(EventType::class)], 'fields.event_format' => ['sometimes', Rule::enum(EventFormat::class)], 'fields.edition' => 'sometimes|nullable|string|max:80',
            'fields.previous_opportunity_id' => 'sometimes|nullable|integer|exists:opportunities,id', 'fields.venue_id' => 'sometimes|nullable|integer|exists:venues,id', 'fields.timezone' => 'sometimes|required|timezone',
            'fields.city' => 'sometimes|nullable|string|max:120', 'fields.state' => 'sometimes|nullable|string|size:2'];
        foreach (['starts_at', 'ends_at', 'setup_starts_at', 'teardown_ends_at'] as $f) {
            $r['fields.'.$f] = 'sometimes|nullable|date';
        }
        foreach (['date_confidence', 'budget_confidence', 'audience_confidence', 'venue_status'] as $f) {
            $r['fields.'.$f] = ['sometimes', Rule::in(['unknown', 'estimated', 'confirmed'])];
        }
        foreach (['budget_declared_cents', 'budget_range_min_cents', 'budget_range_max_cents'] as $f) {
            $r['fields.'.$f] = 'sometimes|nullable|integer|min:0|max:9000000000000';
        }
        foreach (['audience_expected_min', 'audience_expected_max'] as $f) {
            $r['fields.'.$f] = 'sometimes|nullable|integer|min:0|max:10000000';
        }
        foreach (['is_recurring', 'has_vip', 'budget_includes_taxes'] as $f) {
            $r['fields.'.$f] = 'sometimes|boolean';
        }
        foreach (['location_note', 'venue_requirements', 'audience_profile', 'vip_notes', 'accessibility_requirements', 'objective', 'key_message', 'tone', 'brand_notes', 'payment_expectation', 'scope_summary', 'budget_notes', 'restrictions_notes', 'references_notes', 'legacy_date_note'] as $f) {
            $r['fields.'.$f] = 'sometimes|nullable|string|max:10000';
        }
        foreach (['audience_segments', 'brand_assets', 'references'] as $f) {
            $r['fields.'.$f] = 'sometimes|array|max:100';
            $r['fields.'.$f.'.*'] = 'string|max:2000';
        }
        $r['fields.alternative_dates'] = 'sometimes|array|max:50';
        $r['fields.alternative_dates.*'] = 'date';
        foreach (['success_criteria' => ['metric', 'target'], 'constraints' => ['kind', 'description'], 'risks' => ['severity', 'description', 'mitigation'], 'deadlines' => ['title', 'due_at']] as $f => $keys) {
            $r['fields.'.$f] = 'sometimes|array|max:100';
            $r['fields.'.$f.'.*'] = 'array:'.implode(',', $keys);
            foreach ($keys as $key) {
                $r['fields.'.$f.'.*.'.$key] = ($key === 'mitigation' ? 'nullable' : 'required').($key === 'due_at' ? '|date' : '|string|max:2000');
            }
        }
        $r['fields.risks.*.severity'] = ['required', Rule::in(['low', 'medium', 'high', 'critical'])];
        $r['fields.constraints.*.kind'] = ['required', Rule::in(['technical', 'schedule', 'budget', 'accessibility', 'legal', 'venue', 'other'])];

        return $r;
    }

    private function fail(string $key, string $message): never
    {
        throw ValidationException::withMessages([$key => $message]);
    }
}
