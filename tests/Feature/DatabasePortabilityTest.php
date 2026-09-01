<?php

namespace Tests\Feature;

use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DatabasePortabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_case_insensitive_supplier_filter_and_coalesce_query_work_on_the_active_driver(): void
    {
        $supplier = Supplier::create([
            'name' => 'Luz Horizonte',
            'service' => 'Iluminação',
        ]);

        $matchedId = Supplier::query()
            ->whereRaw('LOWER(name) = ?', ['luz horizonte'])
            ->value('id');

        $this->assertSame($supplier->id, $matchedId);

        DB::table('ai_consumptions')->insert([
            'request_key' => 'portability-test',
            'month' => now()->format('Y-m'),
            'action' => 'test',
            'status' => 'charged',
            'reserved_micros' => 400,
            'charged_micros' => null,
            'input_price' => 1,
            'output_price' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $amount = DB::table('ai_consumptions')
            ->selectRaw('COALESCE(charged_micros, reserved_micros) AS amount')
            ->value('amount');

        $this->assertSame(400, (int) $amount);
    }
}
