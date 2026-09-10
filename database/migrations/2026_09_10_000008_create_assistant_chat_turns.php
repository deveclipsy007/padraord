<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assistant_chat_turns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->uuid('request_id');
            $table->string('digest', 64);
            $table->foreignId('opportunity_id')->nullable()->constrained();
            $table->string('mode');
            $table->text('message');
            $table->string('status')->default('pending');
            $table->json('result')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'request_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assistant_chat_turns');
    }
};
