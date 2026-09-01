<?php

namespace App\Http\Controllers;

use App\AI\AiConfiguration;
use App\AI\AudioInspector;
use App\AI\BriefingContext;
use App\AI\BriefingPayload;
use App\Jobs\AnalyzeBriefing;
use App\Models\BriefingAudio;
use App\Models\Opportunity;
use App\Services\BriefingReview;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class BriefingController extends Controller
{
    public function show(Opportunity $opportunity): Response
    {
        return Inertia::render('Briefing', [
            'audio' => ['enabled' => config('ai.audio_validated') && app(AiConfiguration::class)->publicState()['status'] === 'ready' && config('ai.audio_price_micros_per_minute') > 0, 'maxKb' => AudioInspector::maxKilobytes(), 'priceMicros' => (int) config('ai.audio_price_micros_per_minute'), 'files' => BriefingAudio::where('opportunity_id', $opportunity->id)->latest('id')->get()->map(fn ($a) => ['id' => $a->id, 'status' => $a->status, 'seconds' => $a->seconds, 'segments' => $a->segments ?? [], 'names' => $a->speaker_names ?? new \stdClass, 'revision' => $a->revision, 'forwarded' => $a->forwarded_revision === $a->revision, 'error' => $a->error, 'expired' => $a->expires_at->isPast()])],
            'briefing' => ['fields' => app(BriefingContext::class)->fields($opportunity), 'revision' => $opportunity->briefing_revision, 'gaps' => app(BriefingContext::class)->gaps($opportunity), 'approved' => $opportunity->briefing_status === 'complete'],
            'runs' => $opportunity->aiRuns()->latest('id')->limit(10)->get()->map(fn ($r) => ['id' => $r->id, 'status' => $r->status, 'mode' => $r->provider, 'payload' => $r->output_payload, 'decisions' => $r->decisions ?? [], 'sourceId' => $r->briefing_message_id, 'error' => $r->error]),
            'opportunity' => [
                'id' => $opportunity->id,
                'title' => $opportunity->title,
                'clientName' => $opportunity->client_name,
                'briefingStatus' => $opportunity->briefing_status,
            ],
            'messages' => $opportunity->briefingMessages()
                ->oldest()
                ->get()
                ->map(fn ($message): array => [
                    'id' => $message->id,
                    'role' => $message->role,
                    'source' => $message->source,
                    'body' => $message->body,
                    'createdAt' => $message->created_at->format('d/m/Y H:i'),
                ])
                ->values(),
        ]);
    }

    public function store(Request $request, Opportunity $opportunity): RedirectResponse
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'min:3', 'max:20000'],
        ]);

        $message = $opportunity->briefingMessages()->create([
            'role' => 'user',
            'source' => 'manual',
            'body' => $validated['body'],
        ]);

        $state = app(AiConfiguration::class)->publicState();
        $canProcess = in_array($state['status'], ['ready', 'demo']);
        $opportunity->update(['briefing_status' => $canProcess ? 'processing' : 'manual']);
        if ($canProcess) {
            AnalyzeBriefing::dispatch($message->id);
        }

        return back();
    }

    public function review(Request $request, Opportunity $opportunity): RedirectResponse
    {
        $rules = ['revision' => ['required', 'integer', 'min:0'], 'action' => ['required', Rule::in(['save', 'accept', 'reject', 'approve'])], 'fields' => ['sometimes', 'array:'.implode(',', BriefingPayload::FIELDS)], 'run_id' => ['required_if:action,accept,reject', 'integer'], 'indices' => ['required_if:action,accept,reject', 'array', 'min:1', 'max:100'], 'indices.*' => ['integer', 'min:0', 'distinct']];
        foreach (BriefingPayload::FIELDS as $field) {
            $rules['fields.'.$field] = ['sometimes', 'nullable', 'string', 'max:4000'];
        }
        $v = $request->validate($rules);
        if (isset($v['fields'])) {
            $v['fields'] = array_map(fn ($s) => $s ?? '', $v['fields']);
        }
        app(BriefingReview::class)->apply($opportunity, $request->user(), $v);

        return back()->with('success', 'Briefing atualizado; decisão registrada no histórico.');
    }
}
