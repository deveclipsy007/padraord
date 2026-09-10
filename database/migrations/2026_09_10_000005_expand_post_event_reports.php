<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('post_event_reports', function (Blueprint $table): void {
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('closed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('post_event_reports', function (Blueprint $table): void {
            $table->dropForeign(['closed_by']);
            $table->dropColumn(['closed_by', 'closed_at']);
        });
    }
};
