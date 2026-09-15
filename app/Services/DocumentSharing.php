<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Document;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DocumentSharing
{
    /** @return array{token:string,url:string,expires_at:?string} */
    public function create(Opportunity $case, Document $document, User $actor, ?string $expiresAt = null): array
    {
        app(DocumentRevisions::class)->belongs($case, $document);
        if ($document->status !== 'sent' || ! $document->sent_at || $document->type !== 'proposal') {
            $this->fail('Compartilhe somente uma proposta enviada e preservada.');
        }
        app(DocumentRevisions::class)->released($document);
        $document->refresh();
        if ($expiresAt) {
            $expiresAt = Validator::make(['expires_at' => $expiresAt], ['expires_at' => 'date'])->validate()['expires_at'];
        }
        $expires = $expiresAt ? now()->parse($expiresAt)->endOfDay() : now()->addDays(7)->endOfDay();
        if ($expires->lte(now())) {
            $this->fail('A validade do link deve estar no futuro.');
        }
        $token = Str::random(64);
        DB::table('document_share_links')->insert([
            'document_id' => $document->id,
            'created_by' => $actor->id,
            'token_hash' => hash('sha256', $token),
            'expires_at' => $expires,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ['token' => $token, 'url' => url('/shared/proposal/'.$token), 'expires_at' => $expires->toIso8601String()];
    }

    /** @return array{share:object,document:Document} */
    public function resolve(string $token, Request $request, bool $recordView = true): array
    {
        $share = DB::table('document_share_links')->where('token_hash', hash('sha256', $token))->first();
        if (! $share || $share->revoked_at || ($share->expires_at && now()->gte($share->expires_at))) {
            abort(404);
        }
        $document = Document::with('opportunity')->find($share->document_id);
        if (! $document || $document->type !== 'proposal' || ! in_array($document->status, ['sent', 'accepted', 'changes_requested'], true) || ! $document->sent_at) {
            abort(404);
        }
        if ($recordView) {
            DB::table('document_share_links')->where('id', $share->id)->update([
                'first_viewed_at' => $share->first_viewed_at ?: now(),
                'last_viewed_at' => now(),
                'views_count' => ((int) $share->views_count) + 1,
                'updated_at' => now(),
            ]);
        }

        return ['share' => $share, 'document' => $document];
    }

    public function revoke(Opportunity $case, Document $document, User $actor, int $shareId): void
    {
        app(DocumentRevisions::class)->belongs($case, $document);
        $share = DB::table('document_share_links')->where('id', $shareId)->where('document_id', $document->id)->first();
        abort_unless($share, 404);
        DB::table('document_share_links')->where('id', $share->id)->update(['revoked_at' => now(), 'updated_at' => now()]);
        AuditLog::create([
            'user_id' => $actor->id,
            'action' => 'document.share_link_revoked',
            'subject_type' => Opportunity::class,
            'subject_id' => $case->id,
            'metadata' => ['document_id' => $document->id, 'share_id' => $share->id],
        ]);
    }

    public function decide(string $token, Request $request): Document
    {
        $resolved = $this->resolve($token, $request, false);
        $data = $request->validate(['decision' => 'required|in:accepted,requested_changes', 'message' => 'nullable|string|max:5000', 'decided_by_name' => 'nullable|string|max:160']);
        $document = $resolved['document'];
        try {
            DB::transaction(function () use ($resolved, $data, $request, $document): void {
                $locked = Document::whereKey($document->id)->lockForUpdate()->firstOrFail();
                $existing = DB::table('document_share_decisions')->where('share_link_id', $resolved['share']->id)->first();
                if ($existing) {
                    if ($existing->decision !== $data['decision'] || (string) $existing->message !== (string) ($data['message'] ?? '')) {
                        $this->fail('Este link já recebeu uma decisão diferente.');
                    }
                    if ($existing->decision === 'accepted') {
                        app(CommercialAcceptance::class)->record(
                            $locked,
                            Opportunity::findOrFail($locked->opportunity_id),
                            $existing,
                        );
                    }

                    return;
                }
                if ($locked->status !== 'sent') {
                    $this->fail('Esta versão não está mais disponível para decisão.');
                }
                $decisionId = DB::table('document_share_decisions')->insertGetId([
                    'share_link_id' => $resolved['share']->id,
                    'document_id' => $locked->id,
                    'decision' => $data['decision'],
                    'message' => $data['message'] ?? null,
                    'decided_by_name' => $data['decided_by_name'] ?? null,
                    'ip_address' => $request->ip(),
                    'user_agent' => substr((string) $request->userAgent(), 0, 5000),
                    'decided_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $locked->update(['status' => $data['decision'] === 'accepted' ? 'accepted' : 'changes_requested']);
                if ($data['decision'] === 'accepted') {
                    $decision = DB::table('document_share_decisions')->find($decisionId);
                    app(CommercialAcceptance::class)->record(
                        $locked->fresh(),
                        Opportunity::findOrFail($locked->opportunity_id),
                        $decision,
                    );
                }
            }, 3);
        } catch (QueryException $e) {
            if (! str_contains(strtolower($e->getMessage()), 'unique')) {
                throw $e;
            }
        }

        return $document->fresh();
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['share' => $message]);
    }
}
