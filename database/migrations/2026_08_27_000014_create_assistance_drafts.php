<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assistance_drafts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('opportunity_id')->constrained()->restrictOnDelete();
            $t->unsignedInteger('revision');
            $t->json('payload');
            $t->timestamps();
            $t->unique(['opportunity_id', 'revision']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assistance_drafts');
    }
};
