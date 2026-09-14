<?php

namespace App\Http\Controllers;

use App\Models\BriefNeedPreview;
use App\Models\BriefProgramBlock;
use App\Models\BriefRequirement;
use App\Models\CaseContextEntry;
use App\Models\Opportunity;
use App\Services\BriefProgramService;
use App\Services\BriefRequirementService;
use App\Services\BriefSourceService;
use Illuminate\Http\Request;

class BriefStructureController extends Controller
{
    public function sourceStore(Request $r, Opportunity $opportunity, BriefSourceService $s)
    {
        $s->confirm($opportunity, $r->user(), $r->all());

        return back()->with('success', 'Origem confirmada; trecho e instante preservados.');
    }

    public function contextMetadata(Request $r, Opportunity $opportunity, CaseContextEntry $entry, BriefSourceService $s)
    {
        $s->metadata($opportunity, $entry, $r->user(), $r->all());

        return back()->with('success', 'Dados da reunião atualizados.');
    }

    public function requirementStore(Request $r, Opportunity $opportunity, BriefRequirementService $s)
    {
        $s->save($opportunity, $r->user(), null, $r->all());

        return back()->with('success', 'Requisito registrado com sua classificação e origem.');
    }

    public function requirementUpdate(Request $r, Opportunity $opportunity, BriefRequirement $requirement, BriefRequirementService $s)
    {
        $s->save($opportunity, $r->user(), $requirement, $r->all());

        return back()->with('success', 'Requisito atualizado em uma nova revisão.');
    }

    public function preview(Request $r, Opportunity $opportunity, BriefRequirementService $s)
    {
        $s->previewNeeds($opportunity, $r->user());

        return back()->with('success', 'Confira os requisitos e confirme os itens que deseja cotar.');
    }

    public function confirm(Request $r, Opportunity $opportunity, BriefNeedPreview $preview, BriefRequirementService $s)
    {
        $s->confirmNeeds($opportunity, $r->user(), $preview, $r->all());

        return back()->with('success', 'Necessidades criadas; nenhuma contratação realizada.');
    }

    public function programStore(Request $r, Opportunity $opportunity, BriefProgramService $s)
    {
        $s->save($opportunity, $r->user(), null, $r->all());

        return back()->with('success', 'Bloco adicionado à programação.');
    }

    public function programUpdate(Request $r, Opportunity $opportunity, BriefProgramBlock $block, BriefProgramService $s)
    {
        $s->save($opportunity, $r->user(), $block, $r->all());

        return back()->with('success', 'Programação atualizada.');
    }

    public function programOrder(Request $r, Opportunity $opportunity, BriefProgramService $s)
    {
        $s->reorder($opportunity, $r->user(), $r->all());

        return back()->with('success', 'Ordem da programação salva.');
    }

    public function programRemove(Request $r, Opportunity $opportunity, BriefProgramBlock $block, BriefProgramService $s)
    {
        $v = $r->validate(['revision' => 'required|integer|min:0']);
        $s->remove($opportunity, $r->user(), $block, $v['revision']);

        return back()->with('success', 'Bloco removido; a revisão anterior permanece no histórico.');
    }
}
