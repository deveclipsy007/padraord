<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('external_signature_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('document_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->string('signer_name', 160);
            $table->date('signed_at');
            $table->string('method', 80);
            $table->text('evidence');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_signature_records');
    }
};
