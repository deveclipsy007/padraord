<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('can_approve_commercial')->default(false);
        });
        Schema::table('opportunities', function (Blueprint $table): void {
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
        });
        Schema::table('activities', function (Blueprint $table): void {
            $table->string('status')->default('todo');
        });
    }

    public function down(): void
    {
        Schema::table('activities', fn (Blueprint $table) => $table->dropColumn('status'));
        Schema::table('opportunities', fn (Blueprint $table) => $table->dropConstrainedForeignId('contact_id'));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('can_approve_commercial'));
    }
};
