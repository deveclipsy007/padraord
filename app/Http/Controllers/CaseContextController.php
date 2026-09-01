<?php

namespace App\Http\Controllers;

use App\Models\AssistantPreview;
use App\Models\CaseContextEntry;
use App\Models\Opportunity;
use App\Services\CaseContextService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CaseContextController extends Controller
{
    public function store(Request $request, Opportunity $opportunity, CaseContextService $service)
    {
        $data = $request->validate(['kind' => ['required', Rule::in(['text', 'transcript'])], 'phase' => ['required', Rule::in(['commercial', 'briefing', 'technical', 'production', 'post_event'])], 'body' => 'required|string|min:3|max:40000']);
        $preview = $service->createPreview($opportunity, $request->user(), $data);

        return back()->with('success', count($preview->actions).' alterações organizadas para revisão.');
    }

    public function show(Opportunity $opportunity, CaseContextEntry $entry)
    {
        abort_unless($entry->opportunity_id === $opportunity->id, 404);
        $entry->load(['audioAsset', 'segments']);
        $preview = AssistantPreview::where('id', data_get($entry->metadata, 'preview_id'))->first();

        return response()->json(['entry' => [
            ...$entry->only(['id', 'kind', 'phase', 'status', 'revision']),
            'audio' => $entry->audioAsset ? $entry->audioAsset->only(['status', 'duration_ms', 'original_mime', 'original_bytes', 'prepared_bytes', 'error_code', 'error_message']) : null,
            'segments' => $entry->segments->map(fn ($segment) => $segment->only(['id', 'sequence', 'speaker_key', 'speaker_name', 'start_ms', 'end_ms', 'text']))->values(),
        ], 'preview' => $preview?->only(['id', 'status', 'actions', 'result'])]);
    }

    public function confirm(Request $request, Opportunity $opportunity, CaseContextEntry $entry, AssistantPreview $preview, CaseContextService $service)
    {
        $data = $request->validate([
            'modules' => 'required|array|min:1',
            'modules.*' => ['string', Rule::in(['case', 'briefing', 'viability', 'budget', 'documents', 'production', 'post_event'])],
            'changes' => 'sometimes|array|min:1',
            'changes.*' => 'integer|min:0',
        ]);
        $service->confirm($opportunity, $entry, $preview, $request->user(), $data['modules'], $data['changes'] ?? null);

        return back()->with('success', 'Alterações confirmadas como rascunhos revisáveis.');
    }
}
