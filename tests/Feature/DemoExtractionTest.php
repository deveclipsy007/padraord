<?php

namespace Tests\Feature;

use App\AI\DemoAiProvider;
use Tests\TestCase;

class DemoExtractionTest extends TestCase
{
    public function test_ten_explicit_fixtures_extract_only_provided_fields(): void
    {
        foreach (['Conferência Horizonte', 'Encontro comercial', 'Seminário', 'Workshop', 'Lançamento', 'Convenção', 'Feira', 'Reunião interna', 'Treinamento', 'Celebração'] as $i => $name) {
            $text = "Objetivo: $name\nPúblico: ".(50 + $i)." pessoas\nLocal: Recife\nEscopo: Palco e credenciamento";
            $result = (new DemoAiProvider)->analyzeBriefing($text, ['briefing' => ['location' => 'Olinda']]);
            $changes = collect($result->payload['suggested_changes'])->keyBy('field');
            $this->assertSame($name, $changes['objective']['suggested']);
            $this->assertSame('Olinda', $changes['location']['current']);
            $this->assertFalse($changes->has('budget'));
            $this->assertFalse($changes->has('event_date'));
            foreach ($result->payload['facts'] as $fact) {
                $this->assertStringContainsString($fact['evidence'], $text);
            }
        }
    }
}
