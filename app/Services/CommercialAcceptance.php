<?php

namespace App\Services;

use App\Contracts\CommercialChannel;
use App\Models\AuditLog;
use App\Models\Document;
use App\Models\Opportunity;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CommercialAcceptance
{
    /**
     * @param  object{id: int, decided_at: mixed, decided_by_name: ?string}  $decision
     * @return array{receipt_id: int, delivery_project_id: int}
     */
    public function record(Document $document, Opportunity $case, object $decision): array
    {
        $release = $document->release_snapshot;
        if (! is_array($release) || blank($document->release_hash)) {
            $this->fail('A proposta não possui uma versão congelada para aceite. Revise e libere uma nova versão.');
        }

        $key = hash('sha256', "commercial-acceptance|{$document->id}|{$document->release_hash}");
        $existing = DB::table('commercial_acceptance_receipts')->where('document_id', $document->id)->first();
        if ($existing) {
            if ($existing->document_hash !== $document->release_hash) {
                $this->fail('O aceite existente aponta para outra versão liberada. Consulte o histórico.');
            }

            return ['receipt_id' => $existing->id, 'delivery_project_id' => $existing->delivery_project_id];
        }

        $otherProject = DB::table('delivery_projects')->where('opportunity_id', $case->id)->first();
        if ($otherProject) {
            $this->fail('Este caso já possui um projeto criado por outro aceite comercial.');
        }

        $now = now();
        $scope = [
            'document' => $release['document'] ?? [],
            'sections' => $release['sections'] ?? [],
            'sources' => $release['sources'] ?? [],
        ];
        $projectId = DB::table('delivery_projects')->insertGetId([
            'opportunity_id' => $case->id,
            'source_document_id' => $document->id,
            'source_hash' => $document->release_hash,
            'status' => 'onboarding',
            'title' => $document->title,
            'scope_snapshot' => json_encode($scope, JSON_THROW_ON_ERROR),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        foreach ([
            ['scope_confirmation', 'Confirmar escopo, fontes e responsáveis'],
            ['financial_alignment', 'Conferir condições e plano de pagamento'],
            ['kickoff', 'Preparar o kickoff do projeto'],
        ] as [$step, $title]) {
            DB::table('delivery_project_onboarding_steps')->insert([
                'delivery_project_id' => $projectId,
                'key' => $step,
                'title' => $title,
                'status' => 'pending',
                'payload' => json_encode(['document_id' => $document->id, 'document_hash' => $document->release_hash], JSON_THROW_ON_ERROR),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $receipt = [
            'document_id' => $document->id,
            'document_version' => $document->version,
            'document_hash' => $document->release_hash,
            'share_decision_id' => $decision->id,
            'delivery_project_id' => $projectId,
            'accepted_at' => (string) $decision->decided_at,
        ];
        $receiptId = DB::table('commercial_acceptance_receipts')->insertGetId([
            'document_id' => $document->id,
            'share_decision_id' => $decision->id,
            'delivery_project_id' => $projectId,
            'document_hash' => $document->release_hash,
            'idempotency_key' => $key,
            'accepted_by_name' => $decision->decided_by_name,
            'accepted_at' => $decision->decided_at,
            'receipt' => json_encode($receipt, JSON_THROW_ON_ERROR),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $prepared = app(CommercialChannel::class)->prepare('commercial.accepted', [
            'receipt_id' => $receiptId,
            'delivery_project_id' => $projectId,
            'opportunity_id' => $case->id,
            'document_id' => $document->id,
            'document_version' => $document->version,
            'document_hash' => $document->release_hash,
        ]);
        DB::table('commercial_acceptance_outbox')->insert([
            'commercial_acceptance_receipt_id' => $receiptId,
            'idempotency_key' => hash('sha256', "commercial-acceptance-outbox|{$key}"),
            'channel' => $prepared['channel'],
            'event' => $prepared['event'],
            'payload' => json_encode($prepared['payload'], JSON_THROW_ON_ERROR),
            'status' => 'pending',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        AuditLog::create([
            'user_id' => null,
            'action' => 'commercial.acceptance_recorded',
            'subject_type' => Opportunity::class,
            'subject_id' => $case->id,
            'metadata' => $receipt,
        ]);

        return ['receipt_id' => $receiptId, 'delivery_project_id' => $projectId];
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['acceptance' => $message]);
    }
}
