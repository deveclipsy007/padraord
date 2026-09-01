<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('case_context_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('opportunity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('kind', 20);
            $table->string('phase', 30)->default('briefing');
            $table->string('status', 30)->default('received')->index();
            $table->longText('body')->nullable();
            $table->string('path')->nullable();
            $table->char('digest', 64);
            $table->json('metadata')->nullable();
            $table->unsignedInteger('revision')->default(0);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->unique(['opportunity_id', 'digest']);
        });

        Schema::create('case_context_segments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('case_context_entry_id')->constrained()->cascadeOnDelete();
            $table->string('speaker_key', 40);
            $table->string('speaker_name')->nullable();
            $table->unsignedInteger('start_ms')->default(0);
            $table->unsignedInteger('end_ms')->default(0);
            $table->text('text');
            $table->timestamps();
        });

        Schema::create('viability_projects', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('opportunity_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('revision')->default(0);
            $table->string('modality', 30)->default('express');
            $table->string('status', 30)->default('draft');
            $table->text('concept')->nullable();
            $table->text('experience')->nullable();
            $table->text('technical_assumptions')->nullable();
            $table->text('estimate_notes')->nullable();
            $table->text('supplier_needs')->nullable();
            $table->text('schedule_notes')->nullable();
            $table->text('references')->nullable();
            $table->json('snapshot')->nullable();
            $table->timestamps();
        });

        Schema::create('viability_deliverables', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('opportunity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('viability_project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('case_context_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->string('key', 40);
            $table->string('title');
            $table->string('status', 30)->default('pending');
            $table->boolean('required')->default(false);
            $table->text('content')->nullable();
            $table->json('evidence')->nullable();
            $table->timestamps();
            $table->unique(['viability_project_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('viability_deliverables');
        Schema::dropIfExists('viability_projects');
        Schema::dropIfExists('case_context_segments');
        Schema::dropIfExists('case_context_entries');
    }
};
