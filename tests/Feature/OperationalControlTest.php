<?php

namespace Tests\Feature;

use App\Models\Opportunity;
use App\Models\User;
use App\Services\OperationalControl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OperationalControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_project_is_not_ready_and_orbital_is_only_a_locked_presentation(): void
    {
        $user = User::factory()->create();
        $case = Opportunity::create(['title' => 'Teste', 'client_name' => 'Cliente', 'stage' => 'briefing']);
        $this->assertCount(0, array_filter(app(OperationalControl::class)->readiness($case), fn ($c) => $c['ready']));
        $this->actingAs($user)->get('/orbital')->assertInertia(fn ($p) => $p->component('Orbital'));
    }

    public function test_pending_item_cannot_be_resolved_through_another_project_or_stale_revision(): void
    {
        $user = User::factory()->create();
        $case = Opportunity::create(['title' => 'A', 'client_name' => 'A', 'stage' => 'briefing']);
        $other = Opportunity::create(['title' => 'B', 'client_name' => 'B', 'stage' => 'briefing']);
        $this->actingAs($user)->post("/opportunities/{$case->id}/control/pending", ['title' => 'Enviar identidade', 'kind' => 'document', 'awaiting' => 'client', 'owner_id' => $user->id])->assertRedirect();
        $id = DB::table('client_pending_items')->value('id');
        $this->post("/opportunities/{$other->id}/control/pending/{$id}", ['revision' => 0, 'status' => 'resolved', 'resolution' => 'Recebido'])->assertNotFound();
        $this->post("/opportunities/{$case->id}/control/pending/{$id}", ['revision' => 0, 'status' => 'resolved', 'resolution' => 'Recebido'])->assertRedirect();
        $this->post("/opportunities/{$case->id}/control/pending/{$id}", ['revision' => 0, 'status' => 'open'])->assertSessionHasErrors('revision');
    }

    public function test_portal_is_explicit_scoped_expiring_and_revocable(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $case = Opportunity::create(['title' => 'Evento A', 'client_name' => 'A', 'stage' => 'briefing', 'objective' => 'SEGREDO INTERNO']);
        $this->actingAs($user)->post("/opportunities/{$case->id}/control/portal", ['message' => 'Bem-vindo', 'milestones' => "Briefing recebido\nProposta em preparação", 'deliverables' => ['Layout para revisão'], 'document_ids' => [], 'expires_days' => 7])->assertRedirect();
        $url = session('portal_url');
        $this->assertNotEmpty($url);
        $record = DB::table('client_portal_links')->first();
        $this->assertStringNotContainsString('SEGREDO INTERNO', $record->snapshot);
        $this->assertStringNotContainsString($record->token_hash, $url);
        $this->app['auth']->forgetGuards();
        $this->get($url)->assertOk()->assertSee('Evento A')->assertDontSee('SEGREDO INTERNO');
        $this->post($url.'/response', ['item_key' => 'delivery-0', 'decision' => 'approved', 'name' => 'Cliente', 'message' => 'Tudo certo'])->assertRedirect();
        $this->post($url.'/response', ['item_key' => 'delivery-0', 'decision' => 'approved', 'name' => 'Cliente', 'message' => 'Tudo certo'])->assertRedirect();
        $this->assertDatabaseCount('client_portal_responses', 1);
        $this->post($url.'/response', ['item_key' => 'delivery-99', 'decision' => 'approved', 'name' => 'Cliente'])->assertNotFound();
        DB::table('client_portal_links')->where('id', $record->id)->update(['revoked_at' => now()]);
        $this->get($url)->assertNotFound();
    }

    public function test_non_admin_cannot_activate_automations(): void
    {
        $this->actingAs(User::factory()->create())->post('/operations/rules/overdue/enable', ['fingerprint' => 'x'])->assertForbidden();
    }
}
