<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attachments', function (Blueprint $table): void {
            $table->string('module', 30)->default('documents');
            $table->string('linked_type', 40)->nullable();
            $table->unsignedBigInteger('linked_id')->nullable();
            $table->string('sha256', 64)->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->text('archive_reason')->nullable();
            $table->index(['opportunity_id', 'module']);
        });
    }

    public function down(): void
    {
        Schema::table('attachments', function (Blueprint $table): void {
            $table->dropIndex(['opportunity_id', 'module']);
            $table->dropColumn(['module', 'linked_type', 'linked_id', 'sha256', 'archived_at', 'archive_reason']);
        });
    }
};
