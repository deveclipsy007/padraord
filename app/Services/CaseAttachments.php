<?php

namespace App\Services;

use App\Models\Attachment;
use App\Models\AuditLog;
use App\Models\BriefRequirement;
use App\Models\Opportunity;
use App\Models\ProductionChecklistItem;
use App\Models\TechnicalValidation;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class CaseAttachments
{
    public const MODULES = ['documents', 'briefing', 'production', 'finance', 'venue'];

    private const MIME_TYPES = [
        'application/pdf' => ['pdf'],
        'image/png' => ['png'],
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/webp' => ['webp'],
        'text/plain' => ['txt'],
    ];

    public function upload(Opportunity $case, User $actor, UploadedFile $file, array $input): Attachment
    {
        $validated = Validator::make($input, [
            'module' => 'required|in:'.implode(',', self::MODULES),
            'linked_type' => 'nullable|in:requirement,technical_validation,production_checklist_item,payable,venue',
            'linked_id' => 'nullable|integer|min:1',
        ])->validate();

        $mimeType = (new \finfo(FILEINFO_MIME_TYPE))->file($file->getRealPath());
        $extension = strtolower($file->getClientOriginalExtension());

        if (
            ! $file->isValid()
            || ! isset(self::MIME_TYPES[$mimeType])
            || ! in_array($extension, self::MIME_TYPES[$mimeType], true)
            || $file->getSize() > (int) config('attachments.max_file_bytes')
        ) {
            $this->fail('Use PDF, PNG, JPEG, WebP ou TXT com até 20 MB e extensão correspondente.');
        }

        if (in_array($mimeType, ['image/png', 'image/jpeg', 'image/webp'], true) && ! getimagesize($file->getRealPath())) {
            $this->fail('A imagem enviada não é válida.');
        }

        $this->assertValidBinding(
            $case,
            $validated['module'],
            $validated['linked_type'] ?? null,
            $validated['linked_id'] ?? null,
        );

        $path = 'case-attachments/'.$case->id.'/'.Str::uuid().'.'.$extension;

        try {
            return DB::transaction(function () use ($case, $actor, $file, $validated, $mimeType, $path): Attachment {
                Opportunity::whereKey($case->id)->lockForUpdate()->firstOrFail();

                $usedBytes = (int) Attachment::where('opportunity_id', $case->id)->sum('size_bytes');
                if ($usedBytes + $file->getSize() > (int) config('attachments.case_quota_bytes')) {
                    $this->fail('A quota de arquivos deste caso foi atingida. Arquivos arquivados também preservam espaço e histórico.');
                }

                if (! Storage::disk('local')->put($path, file_get_contents($file->getRealPath()))) {
                    $this->fail('Não foi possível preservar o arquivo.');
                }

                $attachment = Attachment::create($validated + [
                    'opportunity_id' => $case->id,
                    'uploaded_by' => $actor->id,
                    'original_name' => Str::limit(basename($file->getClientOriginalName()), 200, ''),
                    'path' => $path,
                    'mime_type' => $mimeType,
                    'size_bytes' => $file->getSize(),
                    'sha256' => hash_file('sha256', $file->getRealPath()),
                ]);

                $this->audit($case, $actor, 'attachment.uploaded', [
                    'id' => $attachment->id,
                    'module' => $attachment->module,
                    'linked_type' => $attachment->linked_type,
                    'linked_id' => $attachment->linked_id,
                    'sha256' => $attachment->sha256,
                ]);

                return $attachment;
            }, 3);
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($path);

            throw $exception;
        }
    }

    public function list(Opportunity $case): array
    {
        $links = collect($this->links($case))->keyBy('key');

        return Attachment::where('opportunity_id', $case->id)
            ->latest('id')
            ->get()
            ->map(function (Attachment $attachment) use ($case, $links): array {
                $link = null;
                if ($attachment->linked_type && $attachment->linked_id) {
                    $candidate = $links->get($attachment->linked_type.':'.$attachment->linked_id);
                    $link = [
                        'type' => $attachment->linked_type,
                        'id' => (int) $attachment->linked_id,
                        'label' => $candidate['label'] ?? 'Registro vinculado indisponível',
                    ];
                }

                return [
                    'id' => $attachment->id,
                    'originalName' => $attachment->original_name,
                    'mimeType' => $attachment->mime_type,
                    'sizeBytes' => (int) $attachment->size_bytes,
                    'module' => $attachment->module,
                    'link' => $link,
                    'isArchived' => $attachment->archived_at !== null,
                    'archivedAt' => $attachment->archived_at?->toIso8601String(),
                    'archiveReason' => $attachment->archive_reason,
                    'createdAt' => $attachment->created_at?->toIso8601String(),
                    'downloadUrl' => "/opportunities/{$case->id}/attachments/{$attachment->id}",
                ];
            })
            ->all();
    }

    public function links(Opportunity $case): array
    {
        $links = [];

        foreach (BriefRequirement::whereIn(
            'event_brief_id',
            DB::table('event_briefs')->where('opportunity_id', $case->id)->select('id'),
        )->get() as $requirement) {
            $links[] = [
                'key' => 'requirement:'.$requirement->id,
                'module' => 'briefing',
                'label' => $requirement->requirement,
            ];
        }

        foreach (TechnicalValidation::where('opportunity_id', $case->id)->get() as $validation) {
            $links[] = [
                'key' => 'technical_validation:'.$validation->id,
                'module' => 'production',
                'label' => $validation->reference,
            ];
        }

        foreach (ProductionChecklistItem::query()
            ->whereHas('checklist', fn ($query) => $query->where('opportunity_id', $case->id))
            ->with('checklist:id,title,phase')
            ->get() as $item) {
            $links[] = [
                'key' => 'production_checklist_item:'.$item->id,
                'module' => 'production',
                'label' => ($item->checklist?->title ?? 'Checklist de produção').' · '.$item->title,
            ];
        }

        foreach (DB::table('payables')->where('opportunity_id', $case->id)->get() as $payable) {
            $links[] = [
                'key' => 'payable:'.$payable->id,
                'module' => 'finance',
                'label' => $payable->label,
            ];
        }

        if ($case->venue_id) {
            $links[] = [
                'key' => 'venue:'.$case->venue_id,
                'module' => 'venue',
                'label' => $case->venue?->name ?? 'Local do evento',
            ];
        }

        return $links;
    }

    public function archive(Opportunity $case, Attachment $attachment, User $actor, ?string $reason): void
    {
        abort_unless($attachment->opportunity_id === $case->id, 404);

        if ($reason !== null) {
            Validator::make(['reason' => $reason], [
                'reason' => 'required|string|min:3|max:2000',
            ])->validate();
        }

        DB::transaction(function () use ($case, $attachment, $actor, $reason): void {
            Opportunity::whereKey($case->id)->lockForUpdate()->firstOrFail();

            $attachment->update([
                'archived_at' => $reason ? now() : null,
                'archive_reason' => $reason,
            ]);

            $this->audit($case, $actor, $reason ? 'attachment.archived' : 'attachment.restored', [
                'id' => $attachment->id,
                'reason' => $reason,
            ]);
        });
    }

    private function assertValidBinding(Opportunity $case, string $module, ?string $type, ?int $id): void
    {
        if (! $type && ! $id) {
            return;
        }

        if (! $type || ! $id) {
            $this->fail('Selecione o tipo e o registro a vincular.');
        }

        $isValid = match ($type) {
            'requirement' => $module === 'briefing'
                && BriefRequirement::whereKey($id)
                    ->whereIn(
                        'event_brief_id',
                        DB::table('event_briefs')->where('opportunity_id', $case->id)->select('id'),
                    )
                    ->exists(),
            'technical_validation' => $module === 'production'
                && TechnicalValidation::whereKey($id)->where('opportunity_id', $case->id)->exists(),
            'production_checklist_item' => $module === 'production'
                && ProductionChecklistItem::query()
                    ->whereKey($id)
                    ->whereHas('checklist', fn ($query) => $query->where('opportunity_id', $case->id))
                    ->exists(),
            'payable' => $module === 'finance'
                && DB::table('payables')->where('id', $id)->where('opportunity_id', $case->id)->exists(),
            'venue' => $module === 'venue' && $case->venue_id === $id,
            default => false,
        };

        abort_unless($isValid, 404);
    }

    private function audit(Opportunity $case, User $actor, string $action, array $metadata): void
    {
        AuditLog::create([
            'user_id' => $actor->id,
            'action' => $action,
            'subject_type' => Opportunity::class,
            'subject_id' => $case->id,
            'metadata' => $metadata,
        ]);
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['file' => $message]);
    }
}
