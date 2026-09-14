<?php

namespace App\Services;

use App\Enums\ProductionArea;
use App\Models\BriefProgramBlock;
use App\Models\EventBrief;
use App\Models\Opportunity;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class BriefProgramService
{
    public function save(Opportunity $case, User $actor, ?BriefProgramBlock $block, array $data): BriefProgramBlock
    {
        $v = Validator::make($data, ['revision' => 'required|integer|min:0', 'title' => 'required|string|max:200', 'description' => 'nullable|string|max:10000', 'starts_at' => 'required|date', 'ends_at' => 'required|date',
            'location_note' => 'nullable|string|max:2000', 'responsible_area' => ['nullable', Rule::enum(ProductionArea::class)], 'attendees_estimate' => 'nullable|integer|min:0|max:10000000', 'source' => 'required|string|max:2000', 'evidence_segment_ids' => 'sometimes|array|max:100', 'evidence_segment_ids.*' => 'integer|distinct|min:1'])->validate();
        app(BriefRequirementService::class)->validateEvidence($case, $v['evidence_segment_ids'] ?? []);

        return app(EventBriefService::class)->changeChildren($case, $actor, $v['revision'], 'program_saved', function (EventBrief $brief) use ($block, $v) {
            if ($block) {
                abort_unless($block->event_brief_id === $brief->id, 404);
            }
            $start = Carbon::parse($v['starts_at'], $brief->timezone)->utc();
            $end = Carbon::parse($v['ends_at'], $brief->timezone)->utc();
            if (! $brief->starts_at || ! $brief->ends_at || $start->lt($brief->starts_at) || $end->gt($brief->ends_at) || $end->lte($start)) {
                throw ValidationException::withMessages(['starts_at' => 'Defina um intervalo válido dentro da janela do evento.']);
            }
            $data = $v;
            unset($data['revision']);
            $data['starts_at'] = $start;
            $data['ends_at'] = $end;
            $block ??= new BriefProgramBlock(['event_brief_id' => $brief->id, 'sequence' => 1 + (int) BriefProgramBlock::where('event_brief_id', $brief->id)->max('sequence')]);
            $block->fill($data)->save();

            return $block;
        });
    }

    public function reorder(Opportunity $case, User $actor, array $data): void
    {
        $v = Validator::make($data, ['revision' => 'required|integer|min:0', 'ids' => 'required|array|min:1|max:500', 'ids.*' => 'integer|distinct'])->validate();
        app(EventBriefService::class)->changeChildren($case, $actor, $v['revision'], 'program_reordered', function (EventBrief $brief) use ($v) {
            $expected = BriefProgramBlock::where('event_brief_id', $brief->id)->orderBy('id')->pluck('id')->all();
            $ids = $v['ids'];
            sort($ids);
            if ($ids !== $expected) {
                throw ValidationException::withMessages(['ids' => 'A ordem precisa conter todos os blocos deste evento, uma vez cada.']);
            }
            foreach ($v['ids'] as $index => $id) {
                BriefProgramBlock::whereKey($id)->update(['sequence' => $index + 1]);
            }
        });
    }

    public function remove(Opportunity $case, User $actor, BriefProgramBlock $block, int $revision): void
    {
        app(EventBriefService::class)->changeChildren($case, $actor, $revision, 'program_removed', function (EventBrief $brief) use ($block) {
            abort_unless($block->event_brief_id === $brief->id, 404);
            $block->delete();
        });
    }

    public function warnings(EventBrief $brief): array
    {
        $blocks = BriefProgramBlock::where('event_brief_id', $brief->id)->orderBy('starts_at')->get();
        $warnings = [];
        foreach ($blocks as $i => $a) {
            foreach ($blocks->slice($i + 1) as $b) {
                if ($a->starts_at->lt($b->ends_at) && $b->starts_at->lt($a->ends_at)) {
                    $warnings[] = ['ids' => [$a->id, $b->id], 'message' => "{$a->title} e {$b->title} têm horários sobrepostos. Confirme se a programação paralela é intencional."];
                }
            }
        }

        return $warnings;
    }
}
