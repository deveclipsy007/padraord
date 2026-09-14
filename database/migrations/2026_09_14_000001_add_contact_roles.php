<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_decision_maker')->default(false);
            $table->string('department', 160)->nullable();
            $table->string('whatsapp', 60)->nullable();
            $table->string('preferred_channel', 20)->default('email');
            $table->unsignedInteger('revision')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('contacts', fn (Blueprint $table) => $table->dropColumn(['is_primary', 'is_decision_maker', 'department', 'whatsapp', 'preferred_channel', 'revision']));
    }
};
