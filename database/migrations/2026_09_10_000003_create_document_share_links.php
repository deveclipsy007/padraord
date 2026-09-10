<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_share_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->dateTime('expires_at')->nullable();
            $table->dateTime('revoked_at')->nullable();
            $table->dateTime('first_viewed_at')->nullable();
            $table->dateTime('last_viewed_at')->nullable();
            $table->unsignedInteger('views_count')->default(0);
            $table->timestamps();
            $table->index(['document_id', 'expires_at']);
        });

        Schema::create('document_share_decisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('share_link_id')->constrained('document_share_links')->cascadeOnDelete();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->string('decision', 32);
            $table->string('message', 5000)->nullable();
            $table->string('decided_by_name', 160)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->dateTime('decided_at');
            $table->timestamps();
            $table->unique('share_link_id');
            $table->index(['document_id', 'decision']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_share_decisions');
        Schema::dropIfExists('document_share_links');
    }
};
