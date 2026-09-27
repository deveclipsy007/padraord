<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_task_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('source_opportunity_id')->constrained('opportunities');
            $table->foreignId('created_by')->constrained('users');
            $table->string('name', 120);
            $table->json('items');
            $table->json('budget_categories')->nullable();
            $table->timestamps();
        });
        Schema::create('production_template_applications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('production_task_template_id')->constrained('production_task_templates');
            $table->foreignId('opportunity_id')->constrained('opportunities');
            $table->foreignId('applied_by')->constrained('users');
            $table->timestamps();
            $table->unique(['production_task_template_id', 'opportunity_id'], 'production_template_target_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_template_applications');
        Schema::dropIfExists('production_task_templates');
    }
};
