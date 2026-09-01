<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentRevisionTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_review_preserves_pdf_and_source_change_blocks_send(): void
    {
        Storage::fake('local');
        config(['commercial.rules_approved' => true, 'commercial.rules_evidence' => 'Teste autorizado']);
        $u = User::factory()->create(['can_approve_commercial' => true]);
        $o = Opportunity::create(['title' => 'Horizonte', 'client_name' => 'Cliente', 'stage' => 'briefing', 'briefing_revision' => 1, 'briefing_approval' => ['revision' => 1, 'fields' => ['scope' => 'Iluminação']]]);
        $b = $o->budgets()->create(['version' => 1, 'revision' => 1, 'purpose' => 'execution', 'status' => 'approved', 'snapshot' => ['demo' => false, 'totalCents' => 100000]]);
        $this->actingAs($u);
        $this->post("/opportunities/$o->id/proposal", ['title' => 'Proposta de Gestão', 'purpose' => 'management', 'expected_version' => 0, 'sections' => ['objective' => 'Evento', 'scope' => 'Iluminação', 'conditions' => 'Condições validadas']])->assertRedirect();
        $d = $o->documents()->first();
        $this->postJson("/opportunities/$o->id/documents/$d->id/review")->assertRedirect();
        $d->refresh();
        $this->assertNotNull($d->pdf_hash);
        $bytes = $this->get("/opportunities/$o->id/documents/$d->id/pdf")->getContent();
        $this->assertSame($d->pdf_hash, hash('sha256', $bytes));
        $o->update(['briefing_revision' => 2]);
        $this->postJson("/opportunities/$o->id/documents/$d->id/sent", ['evidence' => 'Email enviado'])->assertUnprocessable();
        $this->assertSame($bytes, $this->get("/opportunities/$o->id/documents/$d->id/pdf")->getContent());
    }

    public function test_versions_preserve_content_and_old_status_route_cannot_send(): void
    {
        $o = Opportunity::create(['title' => 'Horizonte', 'client_name' => 'Cliente', 'stage' => 'briefing']);
        $this->actingAs(User::factory()->create());
        $data = ['title' => 'Proposta', 'purpose' => 'viability', 'expected_version' => 0, 'status' => 'draft', 'sections' => ['objective' => 'Objetivo original', 'scope' => 'Iluminação', 'conditions' => 'A confirmar']];
        $this->post("/opportunities/$o->id/proposal", $data)->assertRedirect();
        $data['expected_version'] = 1;
        $data['sections']['objective'] = 'Objetivo revisado';
        $this->post("/opportunities/$o->id/proposal", $data)->assertRedirect();
        $this->assertDatabaseCount('documents', 2);
        $this->assertSame('Objetivo original', Document::oldest('version')->first()->content['sections']['objective']);
        $data['status'] = 'sent';
        $data['expected_version'] = 2;
        $this->postJson("/opportunities/$o->id/proposal", $data)->assertUnprocessable();
        $d = Document::latest('version')->first();
        $this->postJson("/opportunities/$o->id/documents/$d->id/review", [])->assertUnprocessable();
        $this->postJson("/opportunities/$o->id/documents/$d->id/sent", ['evidence' => 'email'])->assertUnprocessable();
        $pdf = $this->get("/opportunities/$o->id/documents/$d->id/pdf");
        $pdf->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());
    }

    public function test_stale_editor_does_not_create_another_version(): void
    {
        $o = Opportunity::create(['title' => 'Evento', 'client_name' => 'Cliente', 'stage' => 'briefing']);
        $this->actingAs(User::factory()->create());
        $data = ['title' => 'Proposta', 'purpose' => 'management', 'expected_version' => 0, 'sections' => ['objective' => 'Teste', 'scope' => 'Teste', 'conditions' => 'Teste']];
        $this->postJson("/opportunities/$o->id/proposal", $data)->assertRedirect();
        $this->postJson("/opportunities/$o->id/proposal", $data)->assertUnprocessable();
        $this->assertDatabaseCount('documents',1);
    }
}
