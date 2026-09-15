<?php

namespace Tests\Feature;

use App\Models\BriefRequirement;
use App\Models\Opportunity;
use App\Models\TechnicalValidation;
use App\Models\User;
use App\Models\Venue;
use App\Services\EventBriefService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CaseAttachmentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_private_attachment_exposes_only_the_case_safe_presentation_contract_and_refuses_another_case(): void
    {
        Storage::fake('local');
        $this->actingAs(User::factory()->create());
        $case = Opportunity::create(['title' => 'Evento', 'client_name' => 'Cliente']);
        $other = Opportunity::create(['title' => 'Outro', 'client_name' => 'Outro']);

        $this->post("/opportunities/{$case->id}/attachments", [
            'file' => UploadedFile::fake()->createWithContent('referencia.pdf', "%PDF-1.4\nTeste"),
            'module' => 'documents',
        ])->assertSessionDoesntHaveErrors();

        $attachment = DB::table('attachments')->first();

        $this->assertNotNull($attachment);
        $this->get("/opportunities/{$case->id}/attachments/{$attachment->id}")
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get("/opportunities/{$other->id}/attachments/{$attachment->id}")->assertNotFound();

        $this->get("/opportunities/{$case->id}/documents")
            ->assertDontSee($attachment->path)
            ->assertInertia(fn ($page) => $page
                ->has('attachments', 1)
                ->where('attachments.0.id', $attachment->id)
                ->where('attachments.0.originalName', 'referencia.pdf')
                ->where('attachments.0.mimeType', 'application/pdf')
                ->where('attachments.0.sizeBytes', $attachment->size_bytes)
                ->where('attachments.0.module', 'documents')
                ->where('attachments.0.link', null)
                ->where('attachments.0.isArchived', false)
                ->where('attachments.0.downloadUrl', "/opportunities/{$case->id}/attachments/{$attachment->id}")
                ->missing('attachments.0.path')
                ->missing('attachments.0.sha256')
                ->missing('attachments.0.uploaded_by')
                ->missing('attachments.0.uploaded_by_id'));

        auth()->logout();
        $this->get("/opportunities/{$case->id}/attachments/{$attachment->id}")->assertRedirect('/login');
    }

    public function test_spoofed_extension_quota_and_cross_case_binding_are_rejected(): void
    {
        Storage::fake('local');
        $this->actingAs(User::factory()->create());
        $case = Opportunity::create(['title' => 'Evento', 'client_name' => 'Cliente']);
        $other = Opportunity::create(['title' => 'Outro', 'client_name' => 'Outro']);
        $brief = app(EventBriefService::class)->ensure($other);
        $requirement = BriefRequirement::create([
            'event_brief_id' => $brief->id,
            'area' => 'som',
            'requirement' => 'Som',
            'quantity' => 1,
            'unit' => 'pacote',
            'source' => 'Cliente',
        ]);

        $this->post("/opportunities/{$case->id}/attachments", [
            'file' => UploadedFile::fake()->createWithContent('malicioso.pdf', '<?php echo "test";'),
            'module' => 'documents',
        ])->assertSessionHasErrors('file');
        $this->post("/opportunities/{$case->id}/attachments", [
            'file' => UploadedFile::fake()->image('ref.png'),
            'module' => 'briefing',
            'linked_type' => 'requirement',
            'linked_id' => $requirement->id,
        ])->assertNotFound();
        config(['attachments.case_quota_bytes' => 10]);
        $this->post("/opportunities/{$case->id}/attachments", [
            'file' => UploadedFile::fake()->image('ref.png'),
            'module' => 'documents',
        ])->assertSessionHasErrors('file');

        $this->assertDatabaseCount('attachments', 0);
    }

    public function test_an_attachment_can_link_only_to_compatible_records_from_its_own_case(): void
    {
        Storage::fake('local');
        $this->actingAs(User::factory()->create());
        $venue = Venue::create(['name' => 'Casa de testes']);
        $case = Opportunity::create([
            'title' => 'Evento',
            'client_name' => 'Cliente',
            'venue_id' => $venue->id,
        ]);
        $brief = app(EventBriefService::class)->ensure($case);
        $requirement = BriefRequirement::create([
            'event_brief_id' => $brief->id,
            'area' => 'som',
            'requirement' => 'Sonorização da plenária',
            'quantity' => 1,
            'unit' => 'pacote',
            'source' => 'Cliente',
        ]);
        $validation = TechnicalValidation::create([
            'opportunity_id' => $case->id,
            'reference' => 'Visita técnica do palco',
            'status' => 'pending',
        ]);
        $payableId = DB::table('payables')->insertGetId([
            'opportunity_id' => $case->id,
            'label' => 'Som aprovado',
            'amount_cents' => 20000,
            'origin_type' => 'quote',
            'origin_id' => 999,
            'origin_snapshot' => json_encode(['source' => 'teste']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ([
            ['briefing.txt', 'briefing', 'requirement', $requirement->id],
            ['producao.txt', 'production', 'technical_validation', $validation->id],
            ['financeiro.txt', 'finance', 'payable', $payableId],
            ['local.txt', 'venue', 'venue', $venue->id],
        ] as [$name, $module, $linkedType, $linkedId]) {
            $this->post("/opportunities/{$case->id}/attachments", [
                'file' => UploadedFile::fake()->createWithContent($name, 'Referência privada válida.'),
                'module' => $module,
                'linked_type' => $linkedType,
                'linked_id' => $linkedId,
            ])->assertSessionDoesntHaveErrors();
        }

        $this->get("/opportunities/{$case->id}/documents")
            ->assertInertia(fn ($page) => $page
                ->has('attachmentLinks', 4)
                ->has('attachments', 4)
                ->where('attachments.0.link.type', 'venue')
                ->where('attachments.0.link.label', 'Casa de testes')
                ->where('attachments.1.link.type', 'payable')
                ->where('attachments.1.link.label', 'Som aprovado')
                ->where('attachments.2.link.type', 'technical_validation')
                ->where('attachments.2.link.label', 'Visita técnica do palco')
                ->where('attachments.3.link.type', 'requirement')
                ->where('attachments.3.link.label', 'Sonorização da plenária'));
    }

    public function test_archive_preserves_bytes_and_audits_restore(): void
    {
        Storage::fake('local');
        $this->actingAs(User::factory()->create());
        $case = Opportunity::create(['title' => 'Evento', 'client_name' => 'Cliente']);

        $this->post("/opportunities/{$case->id}/attachments", [
            'file' => UploadedFile::fake()->image('ref.png'),
            'module' => 'documents',
        ])->assertSessionDoesntHaveErrors();

        $attachment = DB::table('attachments')->first();

        $this->post("/opportunities/{$case->id}/attachments/{$attachment->id}/archive", [
            'reason' => 'Referência substituída',
        ])->assertSessionDoesntHaveErrors();
        Storage::disk('local')->assertExists($attachment->path);
        $this->post("/opportunities/{$case->id}/attachments/{$attachment->id}/restore")
            ->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('audit_logs', ['action' => 'attachment.restored']);
    }
}
