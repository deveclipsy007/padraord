<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('case_blockers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('opportunity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('production_task_id')->nullable()->constrained('production_tasks')->nullOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('stage', 32);
            $table->string('title', 180);
            $table->text('reason');
            $table->string('severity', 16)->default('high');
            $table->string('importance', 16)->default('normal');
            $table->string('effort', 16)->default('medium');
            $table->string('status', 16)->default('open');
            $table->string('resume_status', 24)->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('resolution_note')->nullable();
            $table->timestamp('reopened_at')->nullable();
            $table->foreignId('reopened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reopen_reason')->nullable();
            $table->unsignedInteger('revision')->default(0);
            $table->timestamps();

            $table->index(['opportunity_id', 'status', 'severity']);
            $table->index(['production_task_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_blockers');
    }
};
