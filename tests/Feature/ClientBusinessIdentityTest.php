<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Client;
use App\Models\Document;
use App\Models\Opportunity;
use App\Models\User;
use App\Rules\TaxId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientBusinessIdentityTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_dossier_shows_recent_work_and_financial_totals_only_for_its_cases(): void
    {
        $user = User::factory()->create();
        $client = Client::create(['name' => 'Cliente principal']);
        $case = Opportunity::create(['title' => 'Evento A', 'client_id' => $client->id, 'client_name' => $client->name, 'stage' => 'production']);
        $other = Opportunity::create(['title' => 'Evento externo', 'client_name' => 'Outro', 'stage' => 'production']);
        Activity::create(['opportunity_id' => $case->id, 'title' => 'Reunião concluída', 'type' => 'meeting', 'status' => 'done', 'completed_at' => now()]);
        Activity::create(['opportunity_id' => $other->id, 'title' => 'Reunião alheia', 'type' => 'meeting', 'status' => 'done', 'completed_at' => now()]);
        \DB::table('financial_settlements')->insert(['opportunity_id' => $case->id, 'ledger' => 'receivables', 'entry_id' => 1, 'request_key' => 'cliente-a', 'amount_cents' => 12000, 'paid_at' => today(), 'evidence' => 'Comprovante', 'recorded_by' => $user->id, 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($user)->get("/clients/{$client->id}")->assertInertia(fn ($page) => $page
            ->where('relationship.receivedCents', 12000)
            ->where('relationship.recentActions.0.title', 'Reunião concluída')
            ->missing('relationship.recentActions.1'));
    }

    private function payload(array $overrides = []): array
    {
        return array_replace_recursive([
            'name' => 'Instituto Horizonte',
            'legal_name' => 'Instituto Horizonte Cultural Ltda',
            'tax_id' => '11.222.333/0001-81',
            'tax_id_type' => 'cnpj',
            'billing_email' => 'financeiro@horizonte.com.br',
            'billing_address' => [
                'cep' => '01310-100', 'logradouro' => 'Avenida Paulista', 'numero' => '1000',
                'bairro' => 'Bela Vista', 'cidade' => 'São Paulo', 'uf' => 'sp',
            ],
            'segment' => 'institucional',
            'tier' => 'ativo',
        ], $overrides);
    }

    public function test_the_document_is_stored_as_digits_and_the_state_is_normalised(): void
    {
        $this->actingAs(User::factory()->create())->post('/clients', $this->payload())->assertRedirect();

        $client = Client::firstOrFail();
        // Comparar documentos formatados de jeitos diferentes esconde duplicidade.
        $this->assertSame('11222333000181', $client->tax_id);
        $this->assertSame('SP', $client->billing_address['uf']);
        $this->assertSame('Instituto Horizonte Cultural Ltda', $client->legal_name);
    }

    public function test_an_invalid_check_digit_is_refused(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/clients', $this->payload(['tax_id' => '11.222.333/0001-99']))
            ->assertSessionHasErrors('tax_id');
        $this->actingAs($user)->post('/clients', $this->payload(['tax_id' => '111.111.111-11', 'tax_id_type' => 'cpf']))
            ->assertSessionHasErrors('tax_id');

        $this->assertSame(0, Client::count());
    }

    public function test_a_client_without_a_document_can_still_be_registered(): void
    {
        // Um lead entra antes de ter cadastro completo; o documento só é
        // exigido quando o contrato precisa sair.
        $this->actingAs(User::factory()->create())
            ->post('/clients', ['name' => 'Lead sem cadastro'])
            ->assertRedirect();

        $client = Client::firstOrFail();
        $this->assertNull($client->tax_id);
        $this->assertFalse($client->readyForContract());
    }

    public function test_contract_readiness_names_exactly_what_is_missing(): void
    {
        $client = Client::create(['name' => 'Sem dados']);

        $this->assertSame(['razão social', 'CNPJ ou CPF', 'endereço de faturamento', 'cidade', 'estado'], $client->missingContractData());

        $client->update([
            'legal_name' => 'Sem dados Ltda',
            'tax_id' => '11222333000181',
            'billing_address' => ['logradouro' => 'Rua A', 'cidade' => 'Recife', 'uf' => 'PE'],
        ]);

        $this->assertTrue($client->fresh()->readyForContract());
    }

    public function test_the_profile_points_out_another_client_with_the_same_document(): void
    {
        $user = User::factory()->create();
        $first = Client::create(['name' => 'Matriz', 'tax_id' => '11222333000181']);
        $second = Client::create(['name' => 'Cadastro repetido', 'tax_id' => '11222333000181']);

        $this->actingAs($user)->get("/clients/{$second->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('duplicates.0.id', $first->id));
    }

    public function test_an_incomplete_client_blocks_recording_the_contract_signature(): void
    {
        $actor = User::factory()->create(['can_approve_commercial' => true]);
        $client = Client::create(['name' => 'Sem cadastro completo']);
        $opportunity = Opportunity::create([
            'title' => 'Horizonte', 'client_name' => $client->name, 'client_id' => $client->id, 'stage' => 'contract',
        ]);
        $document = Document::create([
            'opportunity_id' => $opportunity->id, 'type' => 'contract', 'version' => 1, 'status' => 'sent',
            'title' => 'Contrato', 'content' => ['sections' => []], 'sent_at' => now(),
        ]);
        $payload = ['signer_name' => 'Rômulo', 'signed_at' => now()->toDateString(), 'method' => 'plataforma_externa', 'evidence' => 'protocolo 123'];

        $this->actingAs($actor)
            ->post("/opportunities/{$opportunity->id}/documents/{$document->id}/external-signature", $payload)
            ->assertSessionHasErrors();
        $this->assertDatabaseCount('external_signature_records', 0);

        $client->update([
            'legal_name' => 'Sem cadastro completo Ltda',
            'tax_id' => '11222333000181',
            'billing_address' => ['logradouro' => 'Rua A', 'cidade' => 'Recife', 'uf' => 'PE'],
        ]);

        $this->actingAs($actor)
            ->post("/opportunities/{$opportunity->id}/documents/{$document->id}/external-signature", $payload)
            ->assertSessionHasNoErrors();
        $this->assertDatabaseCount('external_signature_records', 1);
    }

    public function test_the_check_digit_rule_accepts_valid_documents_and_rejects_repeated_digits(): void
    {
        $this->assertTrue(TaxId::isCnpj('11222333000181'));
        $this->assertTrue(TaxId::isCpf('52998224725'));
        $this->assertFalse(TaxId::isCnpj('11111111111111'));
        $this->assertFalse(TaxId::isCpf('00000000000'));
        $this->assertFalse(TaxId::isCnpj('1122233300018'));
    }
}
