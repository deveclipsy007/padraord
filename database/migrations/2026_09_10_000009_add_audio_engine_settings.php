<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_settings', function (Blueprint $t): void {
            $t->boolean('audio_enabled')->default(false);
            $t->unsignedBigInteger('audio_price_micros_per_minute')->default(0);
        });

        // Carrega o que já estava no ambiente para que uma instalação em uso
        // não perca a autorização de áudio ao passar a ler do banco.
        DB::table('ai_settings')->where('id', 1)->update([
            'audio_enabled' => (bool) config('ai.audio_validated', false),
            'audio_price_micros_per_minute' => max(0, (int) config('ai.audio_price_micros_per_minute', 0)),
        ]);
    }

    public function down(): void
    {
        Schema::table('ai_settings', function (Blueprint $t): void {
            $t->dropColumn(['audio_enabled', 'audio_price_micros_per_minute']);
        });
    }
};
