<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('technical_validations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('opportunity_id')->constrained()->cascadeOnDelete();
            $table->string('reference', 180);
            $table->json('measurements')->nullable();
            $table->string('drawing_path')->nullable();
            $table->text('evidence')->nullable();
            $table->string('supplier_name')->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->unsignedInteger('revision')->default(0);
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('confirmed_at')->nullable();
            $table->dateTime('invalidated_at')->nullable();
            $table->text('invalidated_reason')->nullable();
            $table->text('confirmation_evidence')->nullable();
            $table->timestamps();
        });

        Schema::create('production_task_previews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('opportunity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->char('source_hash', 64);
            $table->json('source')->nullable();
            $table->json('items');
            $table->string('status', 20)->default('pending')->index();
            $table->json('result')->nullable();
            $table->dateTime('confirmed_at')->nullable();
            $table->timestamps();
            $table->unique(['opportunity_id', 'source_hash']);
        });

        Schema::table('production_tasks', function (Blueprint $table): void {
            $table->foreignId('technical_validation_id')->nullable()->constrained('technical_validations')->nullOnDelete();
            $table->foreignId('source_preview_id')->nullable()->constrained('production_task_previews')->nullOnDelete();
            $table->unsignedInteger('source_item_index')->nullable();
            $table->unsignedInteger('revision')->default(0);
            $table->unique(['source_preview_id', 'source_item_index']);
        });
    }

    public function down(): void
    {
        Schema::table('production_tasks', function (Blueprint $table): void {
            $table->dropUnique(['source_preview_id', 'source_item_index']);
            $table->dropForeign(['technical_validation_id']);
            $table->dropForeign(['source_preview_id']);
            $table->dropColumn(['technical_validation_id', 'source_preview_id', 'source_item_index', 'revision']);
        });
        Schema::dropIfExists('production_task_previews');
        Schema::dropIfExists('technical_validations');
    }
};
