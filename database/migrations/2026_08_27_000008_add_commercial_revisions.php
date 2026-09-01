<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('service')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
        Schema::create('supplier_quotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('opportunity_id')->constrained()->restrictOnDelete();
            $table->string('service');
            $table->unsignedBigInteger('unit_cost_cents');
            $table->date('valid_until');
            $table->text('conditions')->nullable();
            $table->text('evidence');
            $table->timestamps();
        });
        Schema::table('budgets', function (Blueprint $table) {
            $table->unsignedInteger('revision')->default(0);
            $table->string('purpose')->default('preliminary');
            $table->string('calculation_mode')->default('legacy');
            $table->json('snapshot')->nullable();
            $table->timestamp('reviewed_at')->nullable();
        });
        Schema::table('budget_items', function (Blueprint $table) {
            $table->foreignId('supplier_quote_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedInteger('management_bps')->default(0);
            $table->unsignedInteger('administration_bps')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('budget_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supplier_quote_id');
            $table->dropColumn(['management_bps', 'administration_bps']);
        });
        Schema::table('budgets', fn (Blueprint $table) => $table->dropColumn(['revision', 'purpose', 'calculation_mode', 'snapshot', 'reviewed_at']));
        Schema::dropIfExists('supplier_quotes');
        Schema::dropIfExists('suppliers');
    }
};
