<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DocumentShareLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_user_creates_a_private_share_link_for_a_sent_document(): void
    {
        $actor = User::factory()->create();
        $opportunity = Opportunity::create(['title' => 'Horizonte', 'client_name' => 'Cliente', 'stage' => 'proposal']);
        $document = Document::create([
            'opportunity_id' => $opportunity->id,
            'type' => 'proposal',
            'purpose' => 'viability',
            'version' => 2,
            'status' => 'sent',
            'title' => 'Proposta Horizonte',
            'content' => ['sections' => ['objective' => 'Evento', 'scope' => 'Escopo', 'conditions' => 'Condições'], 'sources' => ['budget' => ['totalCents' => 120000]]],
            'sent_at' => now(),
        ]);

        $response = $this->actingAs($actor)->post("/opportunities/{$opportunity->id}/documents/{$document->id}/share", ['expires_at' => now()->addDays(7)->toDateString()]);

        $response->assertRedirect()->assertSessionHas('share_url');
        $this->assertDatabaseCount('document_share_links', 1);
        $this->assertStringContainsString('/shared/proposal/', (string) $response->getSession()->get('share_url'));
        $this->assertDatabaseHas('document_share_links', ['document_id' => $document->id]);
    }

    public function test_public_link_records_view_and_acceptance_for_the_exact_sent_version(): void
    {
        $actor = User::factory()->create();
        $opportunity = Opportunity::create(['title' => 'Horizonte', 'client_name' => 'Cliente', 'stage' => 'proposal']);
        $document = Document::create([
            'opportunity_id' => $opportunity->id,
            'type' => 'proposal',
            'purpose' => 'management',
            'version' => 3,
            'status' => 'sent',
            'title' => 'Gestão Horizonte',
            'notes' => 'Margem interna e observações privadas',
            'content' => ['sections' => ['objective' => 'Evento', 'scope' => 'Escopo', 'conditions' => 'Condições internas'], 'sources' => ['budget' => ['totalCents' => 250000]]],
            'sent_at' => now(),
        ]);
        $response = $this->actingAs($actor)->post("/opportunities/{$opportunity->id}/documents/{$document->id}/share")->assertRedirect();
        $share = DB::table('document_share_links')->first();
        $token = basename(parse_url($response->getSession()->get('share_url'), PHP_URL_PATH));

        $this->get("/shared/proposal/{$token}")->assertOk()->assertSee('Gestão Horizonte')->assertSee('R$ 2.500,00')->assertDontSee('Margem interna');
        $this->post("/shared/proposal/{$token}/decision", ['decision' => 'accepted', 'message' => 'Aprovado pela cliente'])->assertRedirect();
        $this->get("/shared/proposal/{$token}")->assertOk()->assertSee('Aprovado pela cliente');

        $this->assertDatabaseHas('document_share_decisions', ['document_id' => $document->id, 'decision' => 'accepted']);
        $this->assertSame('accepted', $document->fresh()->status);
        $this->assertDatabaseHas('document_share_links', ['id' => $share->id, 'views_count' => 2]);
    }

    public function test_expired_or_revoked_link_cannot_be_viewed_or_decided(): void
    {
        $actor = User::factory()->create();
        $opportunity = Opportunity::create(['title' => 'Horizonte', 'client_name' => 'Cliente', 'stage' => 'proposal']);
        $document = Document::create(['opportunity_id' => $opportunity->id, 'type' => 'proposal', 'purpose' => 'viability', 'version' => 1, 'status' => 'sent', 'title' => 'Proposta', 'content' => ['sections' => ['objective' => 'O', 'scope' => 'E', 'conditions' => 'C']], 'sent_at' => now()]);
        $response = $this->actingAs($actor)->post("/opportunities/{$opportunity->id}/documents/{$document->id}/share", ['expires_at' => now()->subDay()->toDateString()])->assertRedirect();
        $share = DB::table('document_share_links')->first();
        $token = basename(parse_url($response->getSession()->get('share_url'), PHP_URL_PATH));

        $this->get("/shared/proposal/{$token}")->assertNotFound();
        $this->post("/shared/proposal/{$token}/decision", ['decision' => 'requested_changes', 'message' => 'Ajustar'])->assertNotFound();
    }

    public function test_authorized_user_can_revoke_a_link_without_deleting_the_document(): void
    {
        $actor = User::factory()->create();
        $opportunity = Opportunity::create(['title' => 'Horizonte', 'client_name' => 'Cliente', 'stage' => 'proposal']);
        $document = Document::create(['opportunity_id' => $opportunity->id, 'type' => 'proposal', 'purpose' => 'viability', 'version' => 1, 'status' => 'sent', 'title' => 'Proposta', 'content' => ['sections' => ['objective' => 'O', 'scope' => 'E', 'conditions' => 'C']], 'sent_at' => now()]);
        $response = $this->actingAs($actor)->post("/opportunities/{$opportunity->id}/documents/{$document->id}/share")->assertRedirect();
        $token = basename(parse_url($response->getSession()->get('share_url'), PHP_URL_PATH));
        $share = DB::table('document_share_links')->first();

        $this->post("/opportunities/{$opportunity->id}/documents/{$document->id}/share/{$share->id}/revoke")->assertRedirect();
        $this->get("/shared/proposal/{$token}")->assertNotFound();
        $this->assertSame('sent', $document->fresh()->status);
    }

    public function test_share_link_rejects_an_invalid_expiration_date(): void
    {
        $actor = User::factory()->create();
        $opportunity = Opportunity::create(['title' => 'Horizonte', 'client_name' => 'Cliente', 'stage' => 'proposal']);
        $document = Document::create(['opportunity_id' => $opportunity->id, 'type' => 'proposal', 'purpose' => 'viability', 'version' => 1, 'status' => 'sent', 'title' => 'Proposta', 'content' => ['sections' => ['objective' => 'O', 'scope' => 'E', 'conditions' => 'C']], 'sent_at' => now()]);

        $this->actingAs($actor)->post("/opportunities/{$opportunity->id}/documents/{$document->id}/share", ['expires_at' => 'quando der'])->assertSessionHasErrors('expires_at');
        $this->assertDatabaseCount('document_share_links', 0);
    }
}
