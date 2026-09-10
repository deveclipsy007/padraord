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
            'signer_name' => 'required|string|max:160', 'signed_at' => 'required|date|before_or_equal:today',
            'method' => 'required|string|max:80', 'evidence' => 'required|string|min:3|max:5000',
        ])->validate();
        DB::transaction(function () use ($case, $document, $actor, $data): void {
            $locked = Document::whereKey($document->id)->lockForUpdate()->firstOrFail();
            $previous = DB::table('external_signature_records')->where('document_id', $locked->id)->first();
            if ($previous) {
                foreach ($data as $key => $value) {
                    if ((string) $previous->$key !== (string) $value) {
                        $this->fail('Esta assinatura já foi registrada com outros dados. Consulte o histórico.');
                    }
                }

                return;
            }
            if ($locked->type !== 'contract' || $locked->status !== 'sent' || ! $locked->sent_at) {
                $this->fail('Registre a assinatura somente de um contrato enviado.');
            }
            // Contrato assinado sem as partes identificadas não serve como
            // instrumento. Conferido aqui, no registro, e não só na tela.
            $client = $case->client;
            if ($client && ! $client->readyForContract()) {
                $this->fail('Complete o cadastro do cliente antes de registrar a assinatura: falta '.implode(', ', $client->missingContractData()).'.');
            }
            DB::table('external_signature_records')->insert($data + [
                'document_id' => $locked->id, 'recorded_by' => $actor->id, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $locked->update(['status' => 'signed_external', 'signed_at' => $data['signed_at']]);
            $this->audit($actor, $locked, 'document.external_signature_recorded');
        });
    }

    public function draft(Opportunity $case, User $user, string $type, array $input): Document
    {
        $v = Validator::make($input, ['title' => 'required|string|max:180', 'purpose' => 'required|in:viability,management', 'expected_version' => 'required|integer|min:0', 'status' => 'sometimes|in:draft', 'notes' => 'nullable|string|max:5000', 'sections' => 'required|array:objective,scope,conditions', 'sections.objective' => 'required|string|max:10000', 'sections.scope' => 'required|string|max:15000', 'sections.conditions' => 'required|string|max:10000'])->validate();

        return DB::transaction(function () use ($case, $user, $type, $v) {
            DB::table('opportunities')->where('id', $case->id)->update(['updated_at' => now()]);
            $o = $case->fresh();
            $latest = $o->documents()->where('type', $type)->latest('version')->first();
            if (($latest?->version ?? 0) !== $v['expected_version']) {
                $this->fail('O documento mudou. Reabra a versão atual antes de salvar.');
            }$b = $o->budgets()->latest('version')->first();
            $d = $o->documents()->create(['type' => $type, 'purpose' => $v['purpose'], 'version' => ($latest?->version ?? 0) + 1, 'status' => 'draft', 'title' => $v['title'], 'notes' => $v['notes'] ?? null, 'content' => ['sections' => $v['sections'], 'sources' => ['briefing_revision' => $o->briefing_revision, 'briefing' => $o->briefing_approval, 'budget_id' => $b?->id, 'budget_revision' => $b?->revision, 'budget' => $b?->snapshot, 'case_title' => $o->title, 'client' => $o->client_name]]]);
            $this->audit($user, $d, 'document.draft_created');

            return $d;
        }, 3);
    }

    public function review(Opportunity $o, Document $doc, User $user): void
    {
        DB::transaction(function () use ($o, $doc, $user) {
            DB::table('opportunities')->where('id', $o->id)->update(['updated_at' => now()]);
            $o = $o->fresh();
            $d = $doc->fresh();
            $this->belongs($o, $d);
            if ($d->type !== 'proposal' || $d->status !== 'draft' || ! $user->can_approve_commercial || ! config('commercial.rules_approved') || blank(config('commercial.rules_evidence'))) {
                $this->fail('Revisão bloqueada: confirme autoridade comercial, regras e modelo aplicável.');
            }
            $this->current($o, $d);
            $b = $o->budgets()->latest('version')->first();
            if (! $o->briefing_approval || ($o->briefing_approval['revision'] ?? null) !== $o->briefing_revision || ! $b || $b->status !== 'approved' || data_get($b->snapshot, 'demo') !== false) {
                $this->fail('Revise o briefing e aprove os valores aplicáveis antes de liberar a proposta.');
            }
            if (($d->purpose === 'viability' && $b->purpose !== 'viability') || ($d->purpose === 'management' && ! in_array($b->purpose, ['management', 'execution']))) {
                $this->fail('A finalidade do orçamento aprovado não corresponde à proposta. Não use estimativas de execução como preço de Viabilidade.');
            }
            foreach ($b->items as $item) {
                if (! $item->quote_valid_until || $item->quote_valid_until->lt(today())) {
                    $this->fail('Há cotação vencida ou sem validade. Prepare nova revisão.');
                }
            }
            $d->status = 'reviewed';
            $d->reviewed_by = $user->id;
            $d->reviewed_at = now();
            $bytes = $this->pdf($d);
            $path = 'documents/'.Str::uuid().'.pdf';
            if (! Storage::disk('local')->put($path, $bytes)) {
                $this->fail('Falha ao preservar o PDF. Nenhuma liberação registrada.');
            }$d->pdf_path = $path;
            $d->pdf_hash = hash('sha256', $bytes);
            $d->save();
            $this->audit($user, $d, 'document.reviewed');
        }, 3);
    }

    public function sent(Opportunity $o, Document $doc, User $user, string $evidence): void
    {
        DB::transaction(function () use ($o, $doc, $user, $evidence) {
            DB::table('opportunities')->where('id', $o->id)->update(['updated_at' => now()]);
            $o = $o->fresh();
            $d = $doc->fresh();
            $this->belongs($o, $d);
            $this->current($o, $d);
            if ($d->status !== 'reviewed' || ! $d->pdf_path || ! $d->reviewed_by) {
                $this->fail('Somente uma versão liberada pode ter envio registrado.');
            }$bytes = Storage::disk('local')->get($d->pdf_path);
            if (hash('sha256', $bytes) !== $d->pdf_hash) {
                $this->fail('A cópia preservada não confere. Solicite revisão.');
            }$d->update(['status' => 'sent', 'sent_at' => now(), 'send_evidence' => $evidence]);
            $this->audit($user, $d, 'document.manual_send_recorded');
        }, 3);
    }

    public function pdf(Document $d): string
    {
        if ($d->pdf_path) {
            return Storage::disk('local')->get($d->pdf_path);
        }
        $pdf = new Dompdf(['isRemoteEnabled' => false, 'isPhpEnabled' => false, 'isJavascriptEnabled' => false, 'defaultFont' => 'DejaVu Sans']);
        $pdf->loadHtml(view('documents.proposal', ['document' => $d])->render());
        $pdf->setPaper('A4');
        $pdf->render();

        return $pdf->output();
    }

    public function current(Opportunity $o, Document $d): void
    {
        $s = $d->content['sources'] ?? [];
        $b = $o->budgets()->latest('version')->first();
        if ((int) $o->documents()->where('type', $d->type)->max('version') !== $d->version || ($s['briefing_revision'] ?? null) !== $o->briefing_revision || ($s['budget_id'] ?? null) !== $b?->id || ($s['budget_revision'] ?? null) !== $b?->revision) {
            $this->fail('As fontes mudaram ou existe uma versão mais recente. Crie e revise um novo rascunho.');
        }
    }

    public function belongs(Opportunity $o, Document $d): void
    {
        abort_unless($d->opportunity_id === $o->id, 404);
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['document' => $message]);
    }

    private function audit(User $user, Document $d, string $action): void
    {
        AuditLog::create(['user_id' => $user->id, 'action' => $action, 'subject_type' => Opportunity::class, 'subject_id' => $d->opportunity_id, 'metadata' => ['document_id' => $d->id, 'version' => $d->version, 'purpose' => $d->purpose]]);
    }
}
