<?php

namespace App\Http\Controllers;

use App\Models\Opportunity;
use App\Services\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PostEventController extends Controller
{
    public function show(Opportunity $opportunity): Response
    {
        $report = $opportunity->postEventReport;

        return Inertia::render('PostEvent', ['opportunity' => ['id' => $opportunity->id, 'title' => $opportunity->title, 'clientName' => $opportunity->client_name], 'report' => ['summary' => $report?->summary, 'learnings' => $report?->learnings, 'actualTotalCents' => $report?->actual_total_cents, 'status' => $report?->status ?? 'draft']]);
    }

    public function store(Request $request, Opportunity $opportunity): RedirectResponse
    {
        $data = $request->validate(['summary' => ['nullable', 'string', 'max:5000'], 'learnings' => ['nullable', 'string', 'max:5000'], 'actual_total' => ['nullable', 'string', 'max:20'], 'actual_total_cents' => ['nullable', 'integer', 'min:0'], 'status' => ['required', 'in:draft,review,closed']]);
        if (filled($data['actual_total'] ?? null)) {
            $data['actual_total_cents'] = Money::decimal($data['actual_total'], 'actual_total');
        }
        unset($data['actual_total']);
        $opportunity->postEventReport()->updateOrCreate([], $data);
        if ($data['status'] === 'closed') {
            $opportunity->update(['stage' => 'closed']);
        }

        return back()->with('success', 'Memória do evento salva.');
    }
}
