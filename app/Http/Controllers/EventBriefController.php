<?php

namespace App\Http\Controllers;

use App\Models\Opportunity;
use App\Services\EventBriefService;
use App\Services\OperationalControl;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EventBriefController extends Controller
{
    public function update(Request $request, Opportunity $opportunity, EventBriefService $service)
    {
        $v = $request->validate(['revision' => 'required|integer|min:0', 'fields' => 'required|array']);
        DB::transaction(function () use ($request, $opportunity, $service, $v) {
            $locked = Opportunity::whereKey($opportunity->id)->lockForUpdate()->firstOrFail();
            if ($request->filled('impact_fingerprint') && ! hash_equals(app(OperationalControl::class)->impact($locked, 'scope')['fingerprint'], (string) $request->input('impact_fingerprint'))) {
                throw ValidationException::withMessages(['impact' => 'O contexto mudou desde a prévia. Confira o impacto novamente.']);
            }
            $service->mutate($locked, $request->user(), $v['revision'], $v['fields']);
        });

        return back()->with('success', 'Briefing salvo com uma nova revisão.');
    }

    public function approve(Request $request, Opportunity $opportunity, EventBriefService $service)
    {
        $v = $request->validate(['revision' => 'required|integer|min:0']);
        $service->approve($opportunity, $request->user(), $v['revision']);

        return back()->with('success', 'Briefing aprovado. Conteúdo e fontes preservados.');
    }

    public function reopen(Request $request, Opportunity $opportunity, EventBriefService $service)
    {
        $v = $request->validate(['revision' => 'required|integer|min:0', 'reason' => 'required|string|min:10|max:2000']);
        $service->reopen($opportunity, $request->user(), $v['revision'], $v['reason']);

        return back()->with('success', 'Briefing reaberto; a versão aprovada permanece no histórico.');
    }
}
