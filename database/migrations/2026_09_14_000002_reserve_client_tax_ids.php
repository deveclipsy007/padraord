<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_tax_id_reservations', function (Blueprint $table) {
            $table->string('tax_id', 20)->primary();
            $table->foreignId('client_id')->unique()->constrained()->cascadeOnDelete();
        });
        // Preserve historical duplicates; reserve their identity to prevent new ones.
        foreach (DB::table('clients')->whereNotNull('tax_id')->where('tax_id', '!=', '')->orderBy('id')->get(['id', 'tax_id']) as $client) {
            DB::table('client_tax_id_reservations')->insertOrIgnore(['tax_id' => $client->tax_id, 'client_id' => $client->id]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('client_tax_id_reservations');
    }
};
