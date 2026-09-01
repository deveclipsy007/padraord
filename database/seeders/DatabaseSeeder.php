<?php

namespace Database\Seeders;

use App\Enums\OpportunityStage;
use App\Models\Client;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (! app()->environment('local') && ! (bool) env('DEMO_DATA', false)) {
            return;
        }

        User::query()->updateOrCreate(
            ['email' => 'test@example.com'],
            ['name' => 'Test User', 'password' => Hash::make('password'), 'role' => 'admin', 'is_active' => true],
        );

        $opportunities = [
            [
                'title' => 'Conferência Horizonte 2026',
                'client_name' => 'Horizonte Educação',
                'contact_name' => 'Marina Costa',
                'contact_email' => 'marina@horizonte.test',
                'stage' => OpportunityStage::BRIEFING,
                'next_action' => 'Responder lacunas do briefing',
                'next_action_at' => now()->addDay()->setTime(10, 0),
                'event_date' => '2026-10-22',
                'estimated_value_cents' => 4850000,
                'briefing_status' => 'processing',
            ],
            [
                'title' => 'Experiência de marca · Aurora',
                'client_name' => 'Aurora Cosméticos',
                'contact_name' => 'Rafael Nunes',
                'contact_email' => 'rafael@aurora.test',
                'stage' => OpportunityStage::PROPOSAL,
                'next_action' => 'Revisar escopo da proposta',
                'next_action_at' => now()->addDays(2)->setTime(14, 0),
                'event_date' => '2026-09-18',
                'estimated_value_cents' => 1850000,
                'briefing_status' => 'complete',
            ],
            [
                'title' => 'Festival Vértice',
                'client_name' => 'Vértice Cultura',
                'contact_name' => 'João Prado',
                'contact_email' => 'joao@vertice.test',
                'stage' => OpportunityStage::LEAD,
                'next_action' => 'Marcar conversa inicial',
                'next_action_at' => now()->addDay()->setTime(16, 30),
                'event_date' => '2026-11-07',
                'estimated_value_cents' => 4200000,
                'briefing_status' => 'not_started',
            ],
            [
                'title' => 'Convenção Orla Sul',
                'client_name' => 'Orla Sul Tecnologia',
                'contact_name' => 'Beatriz Lima',
                'contact_email' => 'beatriz@orlasul.test',
                'stage' => OpportunityStage::BUDGET,
                'next_action' => 'Comparar cotações de audiovisual',
                'next_action_at' => now()->addDays(3)->setTime(9, 30),
                'event_date' => '2026-08-29',
                'estimated_value_cents' => 9800000,
                'briefing_status' => 'complete',
            ],
            [
                'title' => 'Jantar Mosaico',
                'client_name' => 'Mosaico Relações',
                'contact_name' => 'Clara Mendes',
                'contact_email' => 'clara@mosaico.test',
                'stage' => OpportunityStage::PRE_PRODUCTION,
                'next_action' => 'Confirmar fornecedores finais',
                'next_action_at' => now()->addDay()->setTime(11, 30),
                'event_date' => '2026-08-27',
                'estimated_value_cents' => 6700000,
                'briefing_status' => 'complete',
            ],
        ];

        foreach ($opportunities as $opportunity) {
            $client = Client::query()->firstOrCreate(['name' => $opportunity['client_name']]);
            $record = Opportunity::updateOrCreate(
                ['title' => $opportunity['title']],
                $opportunity + ['client_id' => $client->id, 'owner_id' => User::query()->where('email', 'test@example.com')->value('id')],
            );

            if ($record->title === 'Conferência Horizonte 2026' && ! $record->briefingMessages()->exists()) {
                $record->briefingMessages()->createMany([
                    ['role' => 'user', 'source' => 'manual', 'body' => 'Precisamos de uma conferência para 800 pessoas em outubro. O objetivo é aproximar a comunidade de educadores, com plenária, trilhas paralelas, credenciamento e uma experiência de encerramento.'],
                    ['role' => 'assistant', 'source' => 'ai_demo', 'body' => "CONTEXTO IDENTIFICADO\n\nObjetivo\n• Aproximar a comunidade de educadores\n\nFormato\n• Conferência para 800 pessoas\n• Plenária e trilhas paralelas\n• Credenciamento\n• Experiência de encerramento\n\nLACUNAS\n• Local e restrições técnicas\n• Faixa de investimento\n• Fornecedores já aprovados"],
                ]);
                $record->update(['briefing_status' => 'awaiting_review']);
            }
        }
    }
}
