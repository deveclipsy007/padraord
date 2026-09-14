<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Client;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ClientRegistrationService
{
    public function save(?Client $client, User $actor, array $data): Client
    {
        try {
            return DB::transaction(function () use ($client, $actor, $data) {
                $current = $client ? Client::whereKey($client->id)->lockForUpdate()->firstOrFail() : new Client;
                $creating = ! $current->exists;
                $taxId = $data['tax_id'] ?? null;
                $changingIdentity = array_key_exists('tax_id', $data) && ($creating || $taxId !== $current->tax_id);
                if ($changingIdentity && filled($taxId)) {
                    $existing = Client::where('tax_id', $taxId)->first();
                    if ($existing) {
                        throw ValidationException::withMessages(['tax_id' => "Este documento pertence a {$existing->name} (cadastro #{$existing->id}). Abra o cadastro existente."]);
                    }
                }
                $current->fill($data)->save();
                if ($changingIdentity) {
                    DB::table('client_tax_id_reservations')->where('client_id', $current->id)->delete();
                    if (filled($taxId)) {
                        DB::table('client_tax_id_reservations')->insert(['tax_id' => $taxId, 'client_id' => $current->id]);
                    }
                }
                AuditLog::create(['user_id' => $actor->id, 'action' => $creating ? 'client.created' : 'client.updated', 'subject_type' => Client::class, 'subject_id' => $current->id]);

                return $current;
            });
        } catch (UniqueConstraintViolationException $e) {
            throw ValidationException::withMessages(['tax_id' => 'Este documento acaba de ser cadastrado. Atualize a lista de clientes.']);
        }
    }
}
