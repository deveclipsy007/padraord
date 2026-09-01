<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('opportunity_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('briefing_message_id')->nullable()->constrained('briefing_messages')->nullOnDelete();
            $table->string('action', 60);
            $table->string('provider', 40);
            $table->string('model', 80)->nullable();
            $table->string('prompt_version', 40);
            $table->string('status', 20)->index();
            $table->char('input_hash', 64)->index();
            $table->longText('input_text');
            $table->json('output_payload')->nullable();
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->unsignedInteger('duration_ms')->nullable();
            $table->unsignedBigInteger('cost_micros')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
            $table->unique(['briefing_message_id', 'action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_runs');
    }
};
