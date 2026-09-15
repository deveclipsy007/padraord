<?php

namespace App\Http\Controllers;

use App\Models\CaseBlocker;
use App\Models\Opportunity;
use App\Services\DecisionQueue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DecisionQueueController extends Controller
{
    public function store(Request $request, Opportunity $opportunity, DecisionQueue $queue): RedirectResponse
    {
        $queue->openBlocker($opportunity, $request->user(), $request->all());

        return back()->with('success', 'Bloqueio registrado na fila de decisões.');
    }

    public function resolve(Request $request, Opportunity $opportunity, CaseBlocker $blocker, DecisionQueue $queue): RedirectResponse
    {
        abort_unless($blocker->opportunity_id === $opportunity->id, 404);
        $data = $request->validate(['resolution' => ['required', 'string', 'min:3', 'max:5000']]);
        $queue->resolveBlocker($opportunity, $blocker, $request->user(), $data['resolution']);

        return back()->with('success', 'Bloqueio resolvido; a fila foi recalculada.');
    }

    public function reopen(Request $request, Opportunity $opportunity, CaseBlocker $blocker, DecisionQueue $queue): RedirectResponse
    {
        abort_unless($blocker->opportunity_id === $opportunity->id, 404);
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:5000']]);
        $queue->reopenBlocker($opportunity, $blocker, $request->user(), $data['reason']);

        return back()->with('success', 'Bloqueio reaberto e devolvido à fila de decisões.');
    }
}
