<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opportunities', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('client_name');
            $table->string('contact_name')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('stage')->default('lead')->index();
            $table->string('next_action')->nullable();
            $table->dateTime('next_action_at')->nullable()->index();
            $table->date('event_date')->nullable();
            $table->unsignedBigInteger('estimated_value_cents')->nullable();
            $table->string('briefing_status')->default('not_started');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opportunities');
    }
};
