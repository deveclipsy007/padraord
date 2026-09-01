<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('briefing_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('opportunity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reply_to_message_id')->nullable()->constrained('briefing_messages')->nullOnDelete();
            $table->string('role', 20)->index();
            $table->string('source', 30)->default('manual');
            $table->longText('body');
            $table->json('metadata')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('briefing_messages');
    }
};
