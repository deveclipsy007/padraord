<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_tasks', function (Blueprint $table): void {
            $table->string('phase', 24)->default('preparation')->index();
            $table->foreignId('dependency_id')->nullable()->constrained('production_tasks')->nullOnDelete();
            $table->text('blocked_reason')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('production_tasks', function (Blueprint $table): void {
            $table->dropForeign(['dependency_id']);
            $table->dropIndex(['phase']);
            $table->dropColumn(['phase', 'dependency_id', 'blocked_reason', 'started_at', 'completed_at']);
        });
    }
};
