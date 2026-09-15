<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\Opportunity;
use App\Services\CaseAttachments;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CaseAttachmentController extends Controller
{
    public function store(Request $request, Opportunity $opportunity, CaseAttachments $attachments): RedirectResponse
    {
        $request->validate(['file' => 'required|file|max:20480']);

        $attachments->upload($opportunity, $request->user(), $request->file('file'), $request->all());

        return back()->with('success', 'Anexo privado preservado e vinculado.');
    }

    public function download(Opportunity $opportunity, Attachment $attachment)
    {
        abort_unless($attachment->opportunity_id === $opportunity->id, 404);

        $disk = Storage::disk('local');
        abort_unless($disk->exists($attachment->path), 404);

        if ($attachment->sha256) {
            abort_unless(
                hash_file('sha256', $disk->path($attachment->path)) === $attachment->sha256,
                409,
                'O arquivo preservado não confere com o registro.',
            );
        }

        return $disk->download($attachment->path, $attachment->original_name, [
            'Content-Type' => $attachment->mime_type ?? 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function archive(Request $request, Opportunity $opportunity, Attachment $attachment, CaseAttachments $attachments): RedirectResponse
    {
        $validated = $request->validate(['reason' => 'required|string|min:3|max:2000']);
        $attachments->archive($opportunity, $attachment, $request->user(), $validated['reason']);

        return back()->with('success', 'Anexo arquivado; arquivo preservado.');
    }

    public function restore(Request $request, Opportunity $opportunity, Attachment $attachment, CaseAttachments $attachments): RedirectResponse
    {
        $attachments->archive($opportunity, $attachment, $request->user(), null);

        return back()->with('success', 'Anexo restaurado.');
    }
}
