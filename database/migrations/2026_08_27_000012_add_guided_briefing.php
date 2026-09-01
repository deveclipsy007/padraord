<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opportunities', function (Blueprint $t) {
            $t->json('briefing_data')->nullable();
            $t->unsignedInteger('briefing_revision')->default(0);
            $t->json('briefing_approval')->nullable();
        });
        Schema::table('ai_runs', function (Blueprint $t) {
            $t->unsignedInteger('context_revision')->nullable();
            $t->json('decisions')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('opportunities', fn (Blueprint $t) => $t->dropColumn(['briefing_data', 'briefing_revision', 'briefing_approval']));
        Schema::table('ai_runs', fn (Blueprint $t) => $t->dropColumn(['context_revision', 'decisions']));
    }
};
