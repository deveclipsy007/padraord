<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_quote_comparisons', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('opportunity_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('decision_quote_id')->nullable()->constrained('supplier_quotes')->restrictOnDelete();
            $table->string('title', 180);
            $table->text('scope_difference')->nullable();
            $table->text('justification')->nullable();
            $table->string('status', 20)->default('draft');
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
        });

        Schema::create('supplier_quote_comparison_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('comparison_id')->constrained('supplier_quote_comparisons')->cascadeOnDelete();
            $table->foreignId('source_quote_id')->constrained('supplier_quotes')->restrictOnDelete();
            $table->unsignedBigInteger('normalized_total_cents')->nullable();
            $table->json('snapshot');
            $table->timestamps();

            $table->unique(['comparison_id', 'source_quote_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_quote_comparison_items');
        Schema::dropIfExists('supplier_quote_comparisons');
    }
};
