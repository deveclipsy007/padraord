<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Contact;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Validation\ValidationException;

final class RecordArchiveService
{
    public function client(Client $client, User $actor, string $reason): void
    {
        if ($client->opportunities()->whereNull('archived_at')->whereNotIn('commercial_stage', ['lost', 'cancelled'])->exists()) {
            $this->fail('archive', 'Archive primeiro os casos ativos deste cliente ou conclua sua decisão comercial.');
        }
        $this->update($client, $actor, $reason, 'client');
    }

    public function contact(Contact $contact, User $actor, string $reason): void
    {
        $this->update($contact, $actor, $reason, 'contact');
    }

    public function opportunity(Opportunity $case, User $actor, string $reason): void
    {
        $this->update($case, $actor, $reason, 'opportunity');
    }

    public function restore(Client|Contact|Opportunity $record, User $actor): void
    {
        $record->update(['archived_at' => null, 'archived_by' => null, 'archive_reason' => null]);
        AuditLog::create(['user_id' => $actor->id, 'action' => strtolower(class_basename($record)).'.restored', 'subject_type' => $record::class, 'subject_id' => $record->id]);
    }

    private function update(Client|Contact|Opportunity $record, User $actor, string $reason, string $type): void
    {
        if ($record->archived_at) {
            return;
        }
        $record->update(['archived_at' => now(), 'archived_by' => $actor->id, 'archive_reason' => $reason]);
        AuditLog::create(['user_id' => $actor->id, 'action' => $type.'.archived', 'subject_type' => $record::class, 'subject_id' => $record->id, 'metadata' => ['reason' => $reason]]);
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
