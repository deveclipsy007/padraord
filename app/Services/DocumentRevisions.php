<?php

namespace App\Services;

use App\Enums\Ability;
use App\Models\AuditLog;
use App\Models\Document;
use App\Models\Opportunity;
use App\Models\User;
use Dompdf\Dompdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DocumentRevisions
{
    public function externalSignature(Opportunity $case, Document $document, User $actor, array $input): void
    {
        $this->belongs($case, $document);
        Gate::forUser($actor)->authorize(Ability::ApproveCommercial->value);
        $data = Validator::make($input, [
            'signer_name' => 'required|string|max:160',
            'signed_at' => 'required|date|before_or_equal:today',
            'method' => 'required|string|max:80',
            'evidence' => 'required|string|min:3|max:5000',
        ])->validate();

        DB::transaction(function () use ($case, $document, $actor, $data): void {
            $locked = Document::whereKey($document->id)->lockForUpdate()->firstOrFail();
            $previous = DB::table('external_signature_records')->where('document_id', $locked->id)->first();
            if ($previous) {
                foreach ($data as $key => $value) {
                    if ((string) $previous->{$key} !== (string) $value) {
                        $this->fail('Esta assinatura já foi registrada com outros dados. Consulte o histórico.');
                    }
                }

                return;
            }
            if ($locked->type !== 'contract' || $locked->status !== 'sent' || ! $locked->sent_at) {
                $this->fail('Registre a assinatura somente de um contrato enviado.');
            }
            $client = $case->client;
            if ($client && ! $client->readyForContract()) {
                $this->fail('Complete o cadastro do cliente antes de registrar a assinatura: falta '.implode(', ', $client->missingContractData()).'.');
            }
            DB::table('external_signature_records')->insert($data + [
                'document_id' => $locked->id,
                'recorded_by' => $actor->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $locked->update(['status' => 'signed_external', 'signed_at' => $data['signed_at']]);
            $this->audit($actor, $locked, 'document.external_signature_recorded');
        });
    }

    public function draft(Opportunity $case, User $user, string $type, array $input): Document
    {
        $rules = [
            'title' => 'required|string|max:180',
            'expected_version' => 'required|integer|min:0',
            'status' => 'sometimes|in:draft',
            'notes' => 'nullable|string|max:5000',
            'sections' => 'required|array:objective,scope,inclusions,exclusions,clauses,conditions',
            'sections.objective' => 'required|string|max:10000',
            'sections.scope' => 'required|string|max:15000',
            'sections.conditions' => 'required|string|max:10000',
            'sections.inclusions' => 'nullable|string|max:15000',
            'sections.exclusions' => 'nullable|string|max:15000',
            'sections.clauses' => 'nullable|string|max:15000',
        ];
        $rules['purpose'] = $type === 'contract'
            ? 'required|in:contract'
            : 'required|in:viability,management';
        $v = Validator::make($input, $rules)->validate();

        return DB::transaction(function () use ($case, $user, $type, $v): Document {
            DB::table('opportunities')->where('id', $case->id)->update(['updated_at' => now()]);
            $o = $case->fresh();
            $latest = $o->documents()->where('type', $type)->latest('version')->first();
            if (($latest?->version ?? 0) !== $v['expected_version']) {
                $this->fail('O documento mudou. Reabra a versão atual antes de salvar.');
            }
            if ($type === 'contract' && $latest?->status === 'signed_external' && ! $latest->reopened_at) {
                $this->fail('O contrato assinado está preservado. Registre a reabertura antes de preparar um aditivo.');
            }

            $budget = $o->budgets()->latest('version')->first();
            $document = $o->documents()->create([
                'type' => $type,
                'purpose' => $v['purpose'],
                'version' => ($latest?->version ?? 0) + 1,
                'status' => 'draft',
                'title' => $v['title'],
                'notes' => $v['notes'] ?? null,
                'content' => [
                    'schema' => 'document-content-v2',
                    'sections' => $v['sections'],
                    'sources' => [
                        'briefing_revision' => $o->briefing_revision,
                        'briefing' => $o->briefing_approval,
                        'budget_id' => $budget?->id,
                        'budget_revision' => $budget?->revision,
                        'budget' => $budget?->snapshot,
                        'case_title' => $o->title,
                        'client' => $o->client_name,
                        'payment_plan' => app(EventFinance::class)->plan($o),
                    ],
                ],
            ]);
            $this->audit($user, $document, 'document.draft_created');

            return $document;
        }, 3);
    }

    public function review(Opportunity $case, Document $document, User $user): void
    {
        DB::transaction(function () use ($case, $document, $user): void {
            DB::table('opportunities')->where('id', $case->id)->update(['updated_at' => now()]);
            $case = $case->fresh();
            $document = $document->fresh();
            $this->belongs($case, $document);
            if ($document->status !== 'draft') {
                $this->fail('Somente um rascunho pode ser revisado.');
            }

            $budget = $this->assertSourcesReady($case, $document, $user);
            foreach ($budget->items as $item) {
                if (! $item->quote_valid_until || $item->quote_valid_until->lt(today())) {
                    $this->fail('Há cotação vencida ou sem validade. Prepare nova revisão.');
                }
            }

            $document->status = 'reviewed';
            $document->reviewed_by = $user->id;
            $document->reviewed_at = now();
            $release = $this->buildRelease($document);
            $document->release_snapshot = $release;
            $document->release_hash = $this->releaseHash($release);
            $document->released_at = now();
            $bytes = $this->pdfFromHtml($release['html']);
            $path = 'documents/'.Str::uuid().'.pdf';
            if (! Storage::disk('local')->put($path, $bytes)) {
                $this->fail('Falha ao preservar o PDF. Nenhuma liberação registrada.');
            }
            $document->pdf_path = $path;
            $document->pdf_hash = hash('sha256', $bytes);
            $document->save();
            $this->audit($user, $document, 'document.reviewed');
        }, 3);
    }

    public function sent(Opportunity $case, Document $document, User $user, string $evidence): void
    {
        DB::transaction(function () use ($case, $document, $user, $evidence): void {
            DB::table('opportunities')->where('id', $case->id)->update(['updated_at' => now()]);
            $case = $case->fresh();
            $document = $document->fresh();
            $this->belongs($case, $document);
            $this->current($case, $document);
            if ($document->status !== 'reviewed' || ! $document->pdf_path || ! $document->reviewed_by) {
                $this->fail('Somente uma versão liberada pode ter envio registrado.');
            }
            $this->released($document);
            $document = $document->fresh();
            $bytes = Storage::disk('local')->get($document->pdf_path);
            if (hash('sha256', $bytes) !== $document->pdf_hash) {
                $this->fail('A cópia preservada não confere. Solicite revisão.');
            }
            $document->update(['status' => 'sent', 'sent_at' => now(), 'send_evidence' => $evidence]);
            $this->audit($user, $document, 'document.manual_send_recorded');
        }, 3);
    }

    public function reopen(Opportunity $case, Document $document, User $actor, string $reason): void
    {
        Gate::forUser($actor)->authorize(Ability::ApproveCommercial->value);
        if (mb_strlen(trim($reason)) < 3) {
            $this->fail('Explique por que o contrato assinado precisa ser reaberto.');
        }

        DB::transaction(function () use ($case, $document, $actor, $reason): void {
            $locked = Document::whereKey($document->id)->lockForUpdate()->firstOrFail();
            $this->belongs($case, $locked);
            if ($locked->type !== 'contract' || $locked->status !== 'signed_external') {
                $this->fail('Somente um contrato com assinatura registrada pode ser reaberto.');
            }
            $locked->update([
                'status' => 'reopened',
                'reopened_by' => $actor->id,
                'reopened_at' => now(),
                'reopen_reason' => trim($reason),
            ]);
            $this->audit($actor, $locked, 'document.contract_reopened');
        }, 3);
    }

    /** @return array<string, mixed> */
    public function released(Document $document): array
    {
        $document->refresh();
        if ($this->isValidRelease($document)) {
            return $document->release_snapshot;
        }
        if ($document->release_snapshot || ! in_array($document->status, ['reviewed', 'sent', 'accepted', 'changes_requested', 'signed_external', 'reopened'], true)) {
            $this->fail('A versão liberada não confere. Crie e revise uma nova versão.');
        }

        $release = $this->buildRelease($document);
        $document->update([
            'release_snapshot' => $release,
            'release_hash' => $this->releaseHash($release),
            'released_at' => now(),
        ]);

        return $release;
    }

    public function pdf(Document $document): string
    {
        if ($document->pdf_path) {
            return Storage::disk('local')->get($document->pdf_path);
        }

        $release = $this->isValidRelease($document)
            ? $document->release_snapshot
            : $this->buildRelease($document);

        return $this->pdfFromHtml($release['html']);
    }

    public function current(Opportunity $case, Document $document): void
    {
        $sources = $document->content['sources'] ?? [];
        $budget = $case->budgets()->latest('version')->first();
        if (
            (int) $case->documents()->where('type', $document->type)->max('version') !== $document->version
            || ($sources['briefing_revision'] ?? null) !== $case->briefing_revision
            || ($sources['budget_id'] ?? null) !== $budget?->id
            || ($sources['budget_revision'] ?? null) !== $budget?->revision
            || (array_key_exists('payment_plan', $sources)
                && data_get($sources, 'payment_plan.revision') !== data_get(app(EventFinance::class)->plan($case), 'revision'))
        ) {
            $this->fail('As fontes mudaram ou existe uma versão mais recente. Crie e revise um novo rascunho.');
        }
    }

    public function belongs(Opportunity $case, Document $document): void
    {
        abort_unless($document->opportunity_id === $case->id, 404);
    }

    private function assertSourcesReady(Opportunity $case, Document $document, User $user): object
    {
        $isContract = $document->type === 'contract';
        $approved = $isContract
            ? config('commercial.contract_template_approved') && filled(config('commercial.contract_template_evidence'))
            : config('commercial.rules_approved') && filled(config('commercial.rules_evidence'));
        if (! $approved) {
            $this->fail($isContract
                ? 'Revisão contratual bloqueada: confirme o modelo e a evidência jurídica aplicável.'
                : 'Revisão bloqueada: confirme autoridade comercial, regras e modelo aplicável.');
        }
        Gate::forUser($user)->authorize(Ability::ApproveCommercial->value);
        $this->current($case, $document);

        $budget = $case->budgets()->latest('version')->first();
        if (! $case->briefing_approval || ($case->briefing_approval['revision'] ?? null) !== $case->briefing_revision || ! $budget || $budget->status !== 'approved' || data_get($budget->snapshot, 'demo') !== false) {
            $this->fail('Revise o briefing e aprove os valores aplicáveis antes de liberar o documento.');
        }

        if (! $isContract) {
            if (($document->purpose === 'viability' && $budget->purpose !== 'viability') || ($document->purpose === 'management' && ! in_array($budget->purpose, ['management', 'execution'], true))) {
                $this->fail('A finalidade do orçamento aprovado não corresponde à proposta. Não use estimativas de execução como preço de Viabilidade.');
            }
            $this->requireSections($document, ['objective', 'scope', 'inclusions', 'exclusions', 'conditions']);

            return $budget;
        }

        $client = $case->client;
        if (! $client || ! $client->readyForContract()) {
            $this->fail('Complete o cadastro empresarial antes de liberar o contrato.');
        }
        if (! $case->event_date) {
            $this->fail('Defina a data do evento antes de liberar o contrato.');
        }
        $plan = app(EventFinance::class)->plan($case);
        if (! $plan || ($plan['status'] ?? null) !== 'accepted' || (int) ($plan['total_cents'] ?? 0) !== (int) data_get($budget->snapshot, 'totalCents')) {
            $this->fail('O contrato exige um plano de pagamento aceito e compatível com o orçamento aprovado.');
        }
        $this->requireSections($document, ['objective', 'scope', 'clauses', 'conditions']);

        return $budget;
    }

    /** @param list<string> $keys */
    private function requireSections(Document $document, array $keys): void
    {
        $sections = $document->content['sections'] ?? [];
        $missing = array_values(array_filter($keys, fn (string $key): bool => blank($sections[$key] ?? null)));
        if ($missing !== []) {
            $this->fail('Complete as seções antes de liberar: '.implode(', ', $missing).'.');
        }
    }

    /** @return array<string, mixed> */
    private function buildRelease(Document $document): array
    {
        $release = [
            'schema' => 'document-release-v1',
            'presentation' => [
                'template' => 'document-release-v1',
                'asset_fingerprint' => hash('sha256', 'document-release-v1|embedded-css'),
            ],
            'document' => [
                'id' => $document->id,
                'type' => $document->type,
                'purpose' => $document->purpose,
                'version' => $document->version,
                'title' => $document->title,
            ],
            'sections' => $document->content['sections'] ?? [],
            'sources' => $document->content['sources'] ?? [],
        ];
        $release['html'] = view('documents.release-content', ['release' => $release])->render();

        return $release;
    }

    /** @param array<string, mixed> $release */
    private function releaseHash(array $release): string
    {
        return hash('sha256', json_encode($release, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function isValidRelease(Document $document): bool
    {
        return is_array($document->release_snapshot)
            && filled($document->release_hash)
            && isset($document->release_snapshot['html'])
            && hash_equals($document->release_hash, $this->releaseHash($document->release_snapshot));
    }

    private function pdfFromHtml(string $html): string
    {
        $pdf = new Dompdf([
            'isRemoteEnabled' => false,
            'isPhpEnabled' => false,
            'isJavascriptEnabled' => false,
            'defaultFont' => 'DejaVu Sans',
        ]);
        $pdf->loadHtml($html);
        $pdf->setPaper('A4');
        $pdf->render();

        return $pdf->output();
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['document' => $message]);
    }

    private function audit(User $user, Document $document, string $action): void
    {
        AuditLog::create([
            'user_id' => $user->id,
            'action' => $action,
            'subject_type' => Opportunity::class,
            'subject_id' => $document->opportunity_id,
            'metadata' => [
                'document_id' => $document->id,
                'version' => $document->version,
                'purpose' => $document->purpose,
                'release_hash' => $document->release_hash,
            ],
        ]);
    }
}
