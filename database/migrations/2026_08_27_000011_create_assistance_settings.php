<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_settings', function (Blueprint $t) {
            $t->unsignedInteger('id')->primary();
            $t->string('mode')->default('manual');
            $t->string('credential_source')->default('settings');
            $t->text('api_key')->nullable();
            $t->boolean('policy_approved')->default(false);
            $t->unsignedBigInteger('monthly_micros')->default(0);
            $t->unsignedBigInteger('processing_micros')->default(0);
            $t->unsignedBigInteger('input_price')->default(0);
            $t->unsignedBigInteger('output_price')->default(0);
            $t->unsignedInteger('retention_days')->default(30);
            $t->timestamps();
        });
        Schema::create('ai_consumptions', function (Blueprint $t) {
            $t->id();
            $t->string('request_key', 64)->unique();
            $t->string('month', 7)->index();
            $t->string('action');
            $t->string('status')->default('reserved');
            $t->unsignedBigInteger('reserved_micros');
            $t->unsignedBigInteger('charged_micros')->nullable();
            $t->unsignedBigInteger('input_price');
            $t->unsignedBigInteger('output_price');
            $t->json('result')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_consumptions');
        Schema::dropIfExists('ai_settings');
    }
};
