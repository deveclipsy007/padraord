<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Opportunity;
use App\Services\DocumentRevisions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class DocumentController extends Controller
{
    public function show(Request $request, Opportunity $opportunity, string $type): Response
    {
        abort_unless(in_array($type, ['proposal', 'contract'], true), 404);
        $purpose = $type === 'proposal' && in_array($request->query('purpose'), ['viability', 'management'], true) ? $request->query('purpose') : null;
        $versions = $opportunity->documents()->where('type', $type)->when($purpose, fn ($query) => $query->where('purpose', $purpose))->latest('version')->get();
        $latest = $versions->first();
        $document = $request->filled('version') ? $versions->firstWhere('version', (int) $request->version) : $latest;
        abort_if($request->filled('version') && ! $document, 404);
        $stale = false;
        if ($document) {
            try {
                app(DocumentRevisions::class)->current($opportunity, $document);
            } catch (ValidationException) {
                $stale = true;
            }
        }

        return Inertia::render('DocumentWorkspace', [
            'latestVersion' => $latest?->version ?? 0, 'stale' => $stale,
            'versions' => $versions->map(fn ($v) => ['version' => $v->version, 'status' => $v->status, 'purpose' => $v->purpose, 'sections' => $v->content['sections'] ?? []]),
            'canReview' => (bool) ($request->user()->can_approve_commercial && config('commercial.rules_approved') && filled(config('commercial.rules_evidence'))),
            'opportunity' => ['id' => $opportunity->id, 'title' => $opportunity->title, 'clientName' => $opportunity->client_name, 'stage' => $opportunity->stage->value],
            'document' => [
                'id' => $document?->id, 'purpose' => $document?->purpose ?? $purpose ?? 'viability',
                'sections' => $document?->content['sections'] ?? ['objective' => $opportunity->briefing_approval['fields']['objective'] ?? '', 'scope' => $opportunity->briefing_approval['fields']['scope'] ?? '', 'conditions' => 'Condições comerciais pendentes de validação.'],
                'sources' => $document?->content['sources'] ?? null,
                'type' => $type,
                'title' => $document?->title ?? ($type === 'proposal' ? 'Proposta comercial' : 'Contrato de prestação de serviços'),
                'status' => $document?->status ?? 'draft',
                'version' => $document?->version ?? 0,
                'notes' => $document?->notes,
            ],
        ]);
    }

    public function update(Request $request, Opportunity $opportunity, string $type): RedirectResponse
    {
        abort_unless(in_array($type, ['proposal', 'contract'], true), 404);
        app(DocumentRevisions::class)->draft($opportunity, $request->user(), $type, $request->all());

        $purpose = in_array($request->input('purpose'), ['viability', 'management'], true) ? $request->input('purpose') : 'viability';

        return redirect("/opportunities/$opportunity->id/$type".($type === 'proposal' ? '?purpose='.$purpose : ''))->with('success', 'Novo rascunho salvo. A versão anterior foi preservada.');
    }

    public function review(Request $r, Opportunity $opportunity, Document $document, DocumentRevisions $s)
    {
        $s->review($opportunity, $document, $r->user());

        return back()->with('success', 'Versão revisada. PDF preservado.');
    }

    public function sent(Request $r, Opportunity $opportunity, Document $document, DocumentRevisions $s)
    {
        $v = $r->validate(['evidence' => 'required|string|min:3|max:5000']);
        $s->sent($opportunity, $document, $r->user(), $v['evidence']);

        return back()->with('success', 'Envio manual registrado. Nenhum e-mail foi disparado pelo sistema.');
    }

    public function pdf(Opportunity $opportunity, Document $document, DocumentRevisions $s)
    {
        $s->belongs($opportunity, $document);

        return response($s->pdf($document), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="proposta-'.$document->id.'-v'.$document->version.'.pdf"', 'Cache-Control' => 'private, no-store']);
    }
}
