<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $t) {
            $t->string('status')->default('active');
            $t->unsignedInteger('revision')->default(0);
        });
        Schema::table('supplier_quotes', function (Blueprint $t) {
            $t->string('price_basis')->nullable();
            $t->decimal('quantity', 12, 2)->nullable();
            $t->string('unit', 40)->nullable();
            $t->foreignId('supersedes_id')->nullable()->constrained('supplier_quotes')->restrictOnDelete();
        });
        Schema::create('supplier_inquiries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $t->foreignId('opportunity_id')->constrained()->restrictOnDelete();
            $t->string('service');
            $t->string('status')->default('requested');
            $t->text('notes')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_inquiries');
        Schema::table('supplier_quotes', function (Blueprint $t) {
            $t->dropConstrainedForeignId('supersedes_id');
            $t->dropColumn(['price_basis', 'quantity', 'unit']);
        });
        Schema::table('suppliers', fn (Blueprint $t) => $t->dropColumn(['status', 'revision']));
    }
};
