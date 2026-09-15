<?php

namespace App\Http\Controllers;

use App\Models\Opportunity;
use App\Services\CaseAttachments;
use Inertia\Inertia;
use Inertia\Response;

class DocumentsHubController extends Controller
{
    public function __invoke(Opportunity $opportunity, CaseAttachments $attachments): Response
    {
        $documents = $opportunity->documents()->latest('version')->get()->map(fn ($document) => [
            'id' => $document->id, 'type' => $document->type, 'purpose' => $document->purpose,
            'title' => $document->title, 'version' => $document->version, 'status' => $document->status,
            'createdAt' => $document->created_at->format('d/m/Y H:i'),
            'stale' => data_get($document->content, 'sources.briefing_revision') !== $opportunity->briefing_revision,
        ]);

        return Inertia::render('DocumentsHub', [
            'opportunity' => ['id' => $opportunity->id, 'title' => $opportunity->title, 'clientName' => $opportunity->client_name],
            'documents' => $documents,
            'attachments' => $attachments->list($opportunity),
            'attachmentLinks' => $attachments->links($opportunity),
            'attachmentQuota' => config('attachments.case_quota_bytes'),
        ]);
    }
}
