<?php

namespace Tests\Feature;

use App\Models\BriefProgramBlock;
use App\Models\Opportunity;
use App\Models\User;
use App\Services\EventBriefService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BriefProgramTest extends TestCase
{
    use RefreshDatabase;

    public function test_overlap_is_visible_but_blocks_outside_event_are_refused(): void
    {
        $actor = User::factory()->create();
        $this->actingAs($actor);
        $case = Opportunity::create(['title' => 'Evento', 'client_name' => 'Cliente']);
        app(EventBriefService::class)->mutate($case, $actor, 0, ['starts_at' => '2026-10-10T09:00', 'ends_at' => '2026-10-10T18:00']);
        $url = "/opportunities/{$case->id}/event-brief/program";
        $payload = ['revision' => 1, 'title' => 'Abertura', 'starts_at' => '2026-10-10T09:00', 'ends_at' => '2026-10-10T11:00', 'responsible_area' => 'palco', 'source' => 'Cliente'];
        $this->post($url, $payload)->assertSessionDoesntHaveErrors();
        $this->post($url, [...$payload, 'revision' => 2, 'title' => 'Sessão paralela', 'starts_at' => '2026-10-10T10:00', 'ends_at' => '2026-10-10T12:00'])->assertSessionDoesntHaveErrors();
        $this->assertDatabaseCount('brief_program_blocks', 2);
        $this->get("/opportunities/{$case->id}/briefing")->assertInertia(fn ($p) => $p->has('programWarnings', 1));
        $this->post($url, [...$payload, 'revision' => 3, 'starts_at' => '2026-10-09T09:00'])->assertSessionHasErrors('starts_at');
        $this->assertDatabaseCount('brief_program_blocks', 2);
    }

    public function test_reordering_checks_complete_same_case_set_and_revision(): void
    {
        $actor = User::factory()->create();
        $this->actingAs($actor);
        $case = Opportunity::create(['title' => 'Evento', 'client_name' => 'Cliente']);
        app(EventBriefService::class)->mutate($case, $actor, 0, ['starts_at' => '2026-10-10T09:00', 'ends_at' => '2026-10-10T18:00']);
        foreach ([1, 2] as $revision) {
            $this->post("/opportunities/{$case->id}/event-brief/program", ['revision' => $revision, 'title' => "Bloco $revision", 'starts_at' => '2026-10-10T09:00', 'ends_at' => '2026-10-10T10:00', 'source' => 'Cliente'])->assertSessionDoesntHaveErrors();
        }
        $ids = BriefProgramBlock::orderBy('id')->pluck('id')->all();
        $url = "/opportunities/{$case->id}/event-brief/program-order";
        $this->patch($url, ['revision' => 3, 'ids' => [$ids[0], 999999]])->assertSessionHasErrors('ids');
        $this->patch($url, ['revision' => 3, 'ids' => array_reverse($ids)])->assertSessionDoesntHaveErrors();
        $this->assertSame(array_reverse($ids), BriefProgramBlock::orderBy('sequence')->pluck('id')->all());
        $this->patch($url, ['revision' => 3, 'ids' => $ids])->assertSessionHasErrors('revision');
    }
}
