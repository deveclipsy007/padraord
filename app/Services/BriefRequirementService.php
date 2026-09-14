<?php

namespace App\Services;

use App\Enums\ProductionArea;
use App\Models\AuditLog;
use App\Models\BriefNeedPreview;
use App\Models\BriefRequirement;
use App\Models\CaseContextSegment;
use App\Models\EventBrief;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class BriefRequirementService
{
    public function save(Opportunity $case, User $actor, ?BriefRequirement $requirement, array $data): BriefRequirement
    {
        $v = Validator::make($data, [
            'revision' => 'required|integer|min:0', 'area' => ['required', Rule::enum(ProductionArea::class)], 'requirement' => 'required|string|max:10000',
            'quantity' => 'required|decimal:0,2|min:0.01|max:10000', 'unit' => 'required|string|max:40', 'priority' => ['required', Rule::in(['obrigatorio', 'desejavel', 'opcional'])],
            'classification' => ['required', Rule::in(['fact', 'hypothesis', 'conflict', 'unknown'])], 'status' => ['sometimes', Rule::in(['draft', 'confirmed', 'cancelled'])],
            'source' => 'required|string|max:2000', 'evidence_segment_ids' => 'sometimes|array|max:100', 'evidence_segment_ids.*' => 'integer|distinct|min:1',
        ])->validate();
        $this->validateEvidence($case, $v['evidence_segment_ids'] ?? []);

        return app(EventBriefService::class)->changeChildren($case, $actor, $v['revision'], 'requirement_saved', function (EventBrief $brief) use ($v, $requirement) {
            if ($requirement) {
                abort_unless($requirement->event_brief_id === $brief->id, 404);
                $requirement = BriefRequirement::whereKey($requirement->id)->lockForUpdate()->firstOrFail();
                if ($requirement->supplier_need_id) {
                    throw ValidationException::withMessages(['requirement' => 'Este requisito já gerou uma necessidade. Preserve a origem e registre um novo requisito para a alteração.']);
                }
            }
            $data = $v;
            unset($data['revision']);
            $requirement ??= new BriefRequirement(['event_brief_id' => $brief->id]);
            $requirement->fill($data)->save();

            return $requirement;
        });
    }

    public function validateEvidence(Opportunity $case, array $ids): void
    {
        if (count($ids) !== CaseContextSegment::whereIn('id', $ids)->whereHas('entry', fn ($q) => $q->where('opportunity_id', $case->id))->count()) {
            throw ValidationException::withMessages(['evidence_segment_ids' => 'Todos os trechos precisam pertencer a gravações deste caso.']);
        }
    }

    public function previewNeeds(Opportunity $case, User $actor): BriefNeedPreview
    {
        return DB::transaction(function () use ($case, $actor) {
            $brief = app(EventBriefService::class)->ensure($case);
            $items = BriefRequirement::where('event_brief_id', $brief->id)->where('status', '!=', 'cancelled')->whereNull('supplier_need_id')->orderBy('id')->get()->toArray();
            if (! $items) {
                throw ValidationException::withMessages(['requirements' => 'Não há requisitos novos para cotar.']);
            }

            return BriefNeedPreview::create(['event_brief_id' => $brief->id, 'brief_revision' => $brief->revision, 'created_by' => $actor->id, 'items' => $items]);
        });
    }

    public function confirmNeeds(Opportunity $case, User $actor, BriefNeedPreview $preview, array $data): array
    {
        $v = Validator::make($data, ['requirement_ids' => 'required|array|min:1|max:100', 'requirement_ids.*' => 'integer|distinct', 'confirm_uncertain_ids' => 'sometimes|array|max:100', 'confirm_uncertain_ids.*' => 'integer|distinct'])->validate();

        return DB::transaction(function () use ($case, $actor, $preview, $v) {
            $brief = app(EventBriefService::class)->ensure($case);
            $p = BriefNeedPreview::whereKey($preview->id)->lockForUpdate()->firstOrFail();
            abort_unless($p->event_brief_id === $brief->id && $p->created_by === $actor->id, 404);
            if ($p->status === 'confirmed') {
                return $p->result;
            }
            if ($p->brief_revision !== $brief->revision) {
                throw ValidationException::withMessages(['revision' => 'O briefing mudou depois da prévia. Gere uma nova comparação.']);
            }
            if (array_diff($v['requirement_ids'], array_column($p->items, 'id'))) {
                throw ValidationException::withMessages(['requirement_ids' => 'A seleção contém requisitos que não estão nesta prévia.']);
            }
            $result = [];
            foreach ($p->items as $item) {
                if (! in_array($item['id'], $v['requirement_ids'], true)) {
                    continue;
                }
                if ($item['classification'] !== 'fact' && ! in_array($item['id'], $v['confirm_uncertain_ids'] ?? [], true)) {
                    throw ValidationException::withMessages(['confirmation' => 'Confirme individualmente as hipóteses, conflitos ou informações desconhecidas antes de gerar necessidades.']);
                }
                $requirement = BriefRequirement::whereKey($item['id'])->lockForUpdate()->firstOrFail();
                if ($requirement->supplier_need_id) {
                    $result[] = $requirement->supplier_need_id;

                    continue;
                }
                $need = app(SupplierSourcing::class)->createNeed($case, $actor, ['category' => $item['area'], 'scope' => $item['requirement'], 'quantity' => $item['quantity'], 'unit' => $item['unit'], 'required_date' => $brief->starts_at?->toDateString(), 'technical_requirements' => $item['source'], 'status' => 'ready_to_quote']);
                $requirement->update(['supplier_need_id' => $need->id, 'confirmed_by' => $actor->id, 'confirmed_at' => now()]);
                $result[] = $need->id;
            }
            $result = ['need_ids' => $result, 'count' => count($result)];
            $p->update(['status' => 'confirmed', 'result' => $result]);
            AuditLog::create(['user_id' => $actor->id, 'action' => 'brief.needs_confirmed', 'subject_type' => Opportunity::class, 'subject_id' => $case->id, 'metadata' => ['preview_id' => $p->id, 'uncertain_confirmed' => $v['confirm_uncertain_ids'] ?? [], 'result' => $result]]);

            return $result;
        }, 3);
    }
}
