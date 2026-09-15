<?php

namespace App\Http\Controllers;

use App\Services\DocumentRevisions;
use App\Services\DocumentSharing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DocumentShareController extends Controller
{
    public function show(Request $request, string $token, DocumentSharing $sharing): View
    {
        $resolved = $sharing->resolve($token, $request);
        $document = $resolved['document'];
        $release = app(DocumentRevisions::class)->released($document);

        return view('documents.shared', [
            'token' => $token,
            'document' => $document,
            'opportunity' => $document->opportunity,
            'release' => $release,
            'expiresAt' => $resolved['share']->expires_at,
            'decision' => DB::table('document_share_decisions')->where('share_link_id', $resolved['share']->id)->first(),
        ]);
    }

    public function decide(Request $request, string $token, DocumentSharing $sharing): RedirectResponse
    {
        $sharing->decide($token, $request);

        return redirect('/shared/proposal/'.$token)->with('success', 'Decisão registrada. A equipe recebeu seu retorno.');
    }
}
