<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assistant_previews', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->string('mode');
            $t->text('message');
            $t->json('context');
            $t->json('actions');
            $t->json('result')->nullable();
            $t->string('status')->default('preview');
            $t->timestamps();
        });
        Schema::create('budget_intakes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('opportunity_id')->constrained()->restrictOnDelete();
            $t->string('request_key', 100);
            $t->json('result');
            $t->timestamps();
            $t->unique(['opportunity_id', 'request_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_intakes');
        Schema::dropIfExists('assistant_previews');
    }
};
