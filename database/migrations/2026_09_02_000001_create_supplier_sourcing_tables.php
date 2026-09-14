<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_needs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('opportunity_id')->constrained()->restrictOnDelete();
            $table->string('category', 100);
            $table->text('scope');
            $table->decimal('quantity', 12, 2)->nullable();
            $table->string('unit', 40)->nullable();
            $table->date('required_date')->nullable();
            $table->string('status', 30)->default('draft');
            $table->text('technical_requirements')->nullable();
            $table->unsignedInteger('revision')->default(0);
            $table->timestamps();
            $table->index(['opportunity_id', 'status']);
        });

        Schema::create('supplier_quote_selections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('opportunity_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplier_need_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('supplier_quote_id')->constrained()->restrictOnDelete();
            $table->foreignId('selected_by')->constrained('users')->restrictOnDelete();
            $table->text('note');
            $table->timestamps();
            $table->unique(['supplier_need_id', 'supplier_quote_id'], 'supplier_need_quote_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_quote_selections');
        Schema::dropIfExists('supplier_needs');
    }
};
