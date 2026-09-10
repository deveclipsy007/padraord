<?php

namespace Tests\Feature;

use App\Models\Opportunity;
use App\Models\TechnicalValidation;
use App\Models\User;
use App\Models\Venue;
use App\Services\ProductionOperations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class VenueTest extends TestCase
{
    use RefreshDatabase;

    private function venue(array $overrides = []): Venue
    {
        return Venue::create(array_merge([
            'name' => 'Casa Horizonte',
            'venue_type' => 'casa_eventos',
            'city' => 'Recife',
            'state' => 'PE',
            'door_width_m' => 2.4,
            'door_height_m' => 2.8,
        ], $overrides));
    }

    public function test_a_venue_is_registered_and_reused_across_cases(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/venues', ['name' => 'Teatro Boa Vista', 'venue_type' => 'teatro', 'city' => 'Recife', 'state' => 'pe'])
            ->assertRedirect();

        $venue = Venue::firstOrFail();
        $this->assertSame('PE', $venue->state);
        $this->assertDatabaseHas('audit_logs', ['action' => 'venue.created', 'subject_id' => $venue->id]);

        Opportunity::create(['title' => 'Evento A', 'client_name' => 'Cliente', 'stage' => 'briefing', 'venue_id' => $venue->id]);
        Opportunity::create(['title' => 'Evento B', 'client_name' => 'Cliente', 'stage' => 'briefing', 'venue_id' => $venue->id]);

        $this->actingAs($user)->get("/venues/{$venue->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('history', 2));
    }

    public function test_a_concurrent_edit_is_refused_instead_of_overwriting(): void
    {
        $user = User::factory()->create();
        $venue = $this->venue();

        $this->actingAs($user)->patch("/venues/{$venue->id}", ['name' => 'Nome novo', 'revision' => $venue->revision])
            ->assertSessionHasNoErrors();
        // Segunda pessoa ainda com a revisão antiga na tela.
        $this->actingAs($user)->patch("/venues/{$venue->id}", ['name' => 'Outro nome', 'revision' => $venue->revision])
            ->assertSessionHasErrors('revision');

        $this->assertSame('Nome novo', $venue->fresh()->name);
    }

    public function test_a_measurement_that_does_not_fit_the_load_in_is_refused_with_both_numbers(): void
    {
        $venue = $this->venue(['door_width_m' => 2.4]);
        $opportunity = Opportunity::create(['title' => 'Evento', 'client_name' => 'Cliente', 'stage' => 'production', 'venue_id' => $venue->id]);
        $actor = User::factory()->create();

        try {
            app(ProductionOperations::class)->saveTechnicalValidation($opportunity, $actor, [
                'reference' => 'Praticável do palco',
                'measurements' => ['largura' => '3,20 m'],
            ]);
            $this->fail('Uma medida maior que o acesso do local deveria ser recusada.');
        } catch (ValidationException $exception) {
            $message = $exception->errors()['measurements'][0];
            $this->assertStringContainsString('3,2', $message);
            $this->assertStringContainsString('2,4', $message);
        }

        $this->assertSame(0, TechnicalValidation::count());
    }

    public function test_a_measurement_that_fits_is_saved_and_linked_to_the_venue(): void
    {
        $venue = $this->venue(['door_width_m' => 3.5, 'door_height_m' => 3.0]);
        $opportunity = Opportunity::create(['title' => 'Evento', 'client_name' => 'Cliente', 'stage' => 'production', 'venue_id' => $venue->id]);

        $validation = app(ProductionOperations::class)->saveTechnicalValidation($opportunity, User::factory()->create(), [
            'reference' => 'Praticável do palco',
            'measurements' => ['largura' => '3,20 m', 'altura' => '2.5'],
        ]);

        $this->assertSame($venue->id, $validation->venue_id);
        $this->assertSame('pending', $validation->status);
    }

    public function test_a_case_without_a_venue_keeps_working_exactly_as_before(): void
    {
        $opportunity = Opportunity::create(['title' => 'Evento sem local', 'client_name' => 'Cliente', 'stage' => 'production']);

        $validation = app(ProductionOperations::class)->saveTechnicalValidation($opportunity, User::factory()->create(), [
            'reference' => 'Estrutura',
            'measurements' => ['largura' => '99'],
        ]);

        $this->assertNull($validation->venue_id);
    }

    public function test_only_recognised_measurement_keys_are_compared(): void
    {
        $venue = $this->venue(['door_width_m' => 1.0]);

        // "peso" não é medida de acesso: comparar tudo geraria falso bloqueio.
        $this->assertSame([], $venue->accessConflicts(['peso' => '500', 'observacao' => 'texto']));
        $this->assertCount(1, $venue->accessConflicts(['largura' => '2']));
    }
}
