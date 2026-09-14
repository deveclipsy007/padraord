<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Contact;
use App\Models\OpportunityQualification;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ClientContactService
{
    public function save(Client $client, ?Contact $contact, User $actor, array $data): Contact
    {
        return DB::transaction(function () use ($client, $contact, $actor, $data) {
            // Serializes changes to the primary contact, including concurrent creates.
            Client::whereKey($client->id)->lockForUpdate()->firstOrFail();
            $current = $contact ? $client->contacts()->whereKey($contact->id)->lockForUpdate()->firstOrFail() : new Contact(['client_id' => $client->id]);
            $creating = ! $current->exists;
            if (! $creating && (int) ($data['revision'] ?? 0) !== (int) $current->revision) {
                throw ValidationException::withMessages(['revision' => 'Este contato mudou. Atualize a página antes de salvar para preservar a edição mais recente.']);
            }
            if ($current->archived_at) {
                throw ValidationException::withMessages(['name' => 'Restaure o contato antes de editar.']);
            }
            unset($data['revision']);
            $current->fill($data);
            if ($current->is_primary) {
                $query = $client->contacts()->where('is_primary', true);
                if ($current->exists) {
                    $query->whereKeyNot($current->id);
                }
                $query->update(['is_primary' => false, 'revision' => DB::raw('revision + 1'), 'updated_at' => now()]);
            }
            if (! $creating && $current->isDirty('is_decision_maker') && ! $current->is_decision_maker) {
                $this->reopenQualifications($current);
            }
            $current->revision = (int) $current->revision + ($creating ? 0 : 1);
            $current->save();
            AuditLog::create(['user_id' => $actor->id, 'action' => $creating ? 'contact.created' : 'contact.updated', 'subject_type' => Contact::class, 'subject_id' => $current->id, 'metadata' => ['revision' => $current->revision, 'is_primary' => $current->is_primary, 'is_decision_maker' => $current->is_decision_maker]]);

            return $current;
        });
    }

    public function reopenQualifications(Contact $contact): void
    {
        OpportunityQualification::where('decision_maker_contact_id', $contact->id)->update([
            'status' => 'in_progress', 'decision_maker_status' => 'unknown', 'qualified_at' => null,
            'qualified_by' => null, 'revision' => DB::raw('revision + 1'), 'updated_at' => now(),
        ]);
    }
}
