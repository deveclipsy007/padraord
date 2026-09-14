<?php

namespace Database\Seeders;

use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class FinanceE2ESeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('testing') || ! str_ends_with((string) config('database.connections.sqlite.database'), '/e2e.sqlite')) {
            throw new \RuntimeException('Fixture permitida somente no banco e2e descartável.');
        }
        User::updateOrCreate(['email' => 'finance@example.test'], ['name' => 'Financeiro de teste', 'password' => Hash::make('password'), 'is_active' => true, 'role' => 'producer', 'can_approve_commercial' => true]);
        foreach (['desktop', 'tablet', 'mobile'] as $device) {
            $case = Opportunity::create(['title' => "Financeiro $device", 'client_id' => 1, 'client_name' => 'Cliente de teste']);
            $budget = $case->budgets()->create(['version' => 1, 'status' => 'approved']);
            $budget->items()->create(['category' => 'som', 'description' => 'Som de teste', 'quantity' => 1, 'unit_cost_cents' => 10000]);
        }
    }
}
