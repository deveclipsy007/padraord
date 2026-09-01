<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('briefing_audio', function (Blueprint $t) {
            $t->id();
            $t->foreignId('opportunity_id')->constrained()->restrictOnDelete();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->string('path');
            $t->string('mime');
            $t->unsignedInteger('seconds');
            $t->string('digest', 64);
            $t->string('status')->default('waiting');
            $t->json('segments')->nullable();
            $t->json('speaker_names')->nullable();
            $t->unsignedInteger('revision')->default(0);
            $t->unsignedInteger('forwarded_revision')->nullable();
            $t->text('error')->nullable();
            $t->timestamp('expires_at');
            $t->timestamps();
            $t->unique(['opportunity_id', 'digest']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('briefing_audio');
    }
};
