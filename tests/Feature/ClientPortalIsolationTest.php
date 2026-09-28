<?php

namespace Tests\Feature;

use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ClientPortalIsolationTest extends TestCase
{
    use RefreshDatabase;

    private function publish(User $user, Opportunity $case): string
    {
        $this->actingAs($user)->post('/opportunities/'.$case->id.'/control/portal', ['message' => 'Projeto', 'milestones' => 'Em preparação', 'deliverables' => [], 'document_ids' => [], 'expires_days' => 7])->assertSessionHasNoErrors();

        return session('portal_url');
    }

    public function test_portal_upload_is_private_and_expired_link_is_unavailable(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $case = Opportunity::create(['title' => 'Privado', 'client_name' => 'Cliente', 'stage' => 'briefing', 'owner_id' => $owner->id]);
        $url = $this->publish($owner, $case);
        $this->post($url.'/upload', ['name' => 'Cliente', 'file' => UploadedFile::fake()->image('referencia.png')])->assertSessionHasNoErrors();
        $upload = DB::table('client_portal_uploads')->first();
        Storage::disk('local')->assertExists($upload->path);
        $this->actingAs($other)->get('/opportunities/'.$case->id.'/control/uploads/'.$upload->id)->assertForbidden();
        $this->actingAs($owner)->get('/opportunities/'.$case->id.'/control/uploads/'.$upload->id)->assertOk();
        $this->travel(8)->days();
        $this->get($url)->assertNotFound();
    }

    public function test_non_owner_cannot_publish_and_other_case_document_is_rejected(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $a = Opportunity::create(['title' => 'A', 'client_name' => 'A', 'stage' => 'briefing', 'owner_id' => $owner->id]);
        $b = Opportunity::create(['title' => 'B', 'client_name' => 'B', 'stage' => 'briefing', 'owner_id' => $other->id]);
        $doc = $b->documents()->create(['type' => 'proposal', 'version' => 1, 'status' => 'sent', 'sent_at' => now(), 'title' => 'Segredo', 'content' => []]);
        $data = ['message' => 'Oi', 'milestones' => 'Revisar', 'deliverables' => [], 'document_ids' => [$doc->id], 'expires_days' => 7];
        $this->actingAs($other)->post('/opportunities/'.$a->id.'/control/portal', $data)->assertForbidden();
        $this->actingAs($owner)->post('/opportunities/'.$a->id.'/control/portal', $data)->assertSessionHasErrors('document_ids');
        $this->assertDatabaseCount('client_portal_links', 0);
    }

    public function test_published_proposal_uses_only_released_public_sections(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $case = Opportunity::create(['title' => 'Evento', 'client_name' => 'Cliente', 'stage' => 'proposal']);
        $release = ['html' => '<p>Preview</p>', 'sections' => ['objective' => 'Objetivo público', 'scope' => 'Escopo aprovado', 'internal' => 'NAO_PUBLICAR'], 'sources' => ['budget' => ['totalCents' => 10000, 'items' => [['unit_cost_cents' => 4900, 'notes' => 'MARGEM_INTERNA']]]]];
        $document = $case->documents()->create(['type' => 'proposal', 'version' => 1, 'status' => 'sent', 'sent_at' => now(), 'title' => 'Proposta', 'content' => [], 'release_snapshot' => $release, 'release_hash' => hash('sha256', json_encode($release, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))]);
        $this->actingAs($user)->post('/opportunities/'.$case->id.'/control/portal', ['message' => 'Acompanhe', 'milestones' => 'Proposta liberada', 'deliverables' => [], 'document_ids' => [$document->id], 'expires_days' => 7])->assertSessionHasNoErrors();
        $url = session('portal_url');
        $response = $this->get($url)->assertOk()->assertSee('Objetivo público')->assertSee('100,00')->assertDontSee('NAO_PUBLICAR')->assertDontSee('MARGEM_INTERNA')->assertDontSee('unit_cost_cents');
        $response->assertHeader('Referrer-Policy', 'no-referrer');
    }
}
