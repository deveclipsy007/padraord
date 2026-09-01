<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('case_journeys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opportunity_id')->unique()->constrained()->restrictOnDelete();
            $table->unsignedInteger('revision')->default(0);
            $table->string('mode')->default('demo');
            $table->string('modality')->default('express');
            $table->string('cycle')->default('commercial');
            $table->string('viability_status')->default('not_contracted');
            $table->string('management_status')->default('not_contracted');
            $table->string('outcome')->nullable();
            $table->json('deliverables')->nullable();
            $table->json('evidence')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_journeys');
    }
};
