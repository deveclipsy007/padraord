<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audio_upload_sessions', function (Blueprint $table): void {
            $table->foreignId('prepared_for')->nullable()->constrained('case_context_entries')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('audio_upload_sessions', fn (Blueprint $table) => $table->dropConstrainedForeignId('prepared_for'));
    }
};
