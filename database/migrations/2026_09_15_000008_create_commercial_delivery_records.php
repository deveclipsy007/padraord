<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table): void {
            $table->json('release_snapshot')->nullable()->after('content');
            $table->char('release_hash', 64)->nullable()->index()->after('release_snapshot');
            $table->timestamp('released_at')->nullable()->after('release_hash');
            $table->foreignId('reopened_by')->nullable()->after('released_at')->constrained('users')->nullOnDelete();
            $table->timestamp('reopened_at')->nullable()->after('reopened_by');
            $table->text('reopen_reason')->nullable()->after('reopened_at');
        });

        Schema::create('delivery_projects', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('opportunity_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('source_document_id')->unique()->constrained('documents')->restrictOnDelete();
            $table->char('source_hash', 64);
            $table->string('status', 30)->default('onboarding')->index();
            $table->string('title', 180);
            $table->json('scope_snapshot');
            $table->timestamps();
        });

        Schema::create('delivery_project_onboarding_steps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('delivery_project_id')->constrained('delivery_projects')->cascadeOnDelete();
            $table->string('key', 60);
            $table->string('title', 180);
            $table->string('status', 30)->default('pending')->index();
            $table->json('payload')->nullable();
            $table->timestamps();
            $table->unique(['delivery_project_id', 'key'], 'delivery_onboarding_step_key');
        });

        Schema::create('commercial_acceptance_receipts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('document_id')->unique()->constrained('documents')->restrictOnDelete();
            $table->foreignId('share_decision_id')->unique()->constrained('document_share_decisions')->restrictOnDelete();
            $table->foreignId('delivery_project_id')->unique()->constrained('delivery_projects')->restrictOnDelete();
            $table->char('document_hash', 64);
            $table->char('idempotency_key', 64)->unique();
            $table->string('accepted_by_name', 160)->nullable();
            $table->timestamp('accepted_at');
            $table->json('receipt');
            $table->timestamps();
        });

        Schema::create('commercial_acceptance_outbox', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('commercial_acceptance_receipt_id')->unique()->constrained()->cascadeOnDelete();
            $table->char('idempotency_key', 64)->unique();
            $table->string('channel', 60);
            $table->string('event', 100);
            $table->json('payload');
            $table->string('status', 30)->default('pending')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commercial_acceptance_outbox');
        Schema::dropIfExists('commercial_acceptance_receipts');
        Schema::dropIfExists('delivery_project_onboarding_steps');
        Schema::dropIfExists('delivery_projects');

        Schema::table('documents', function (Blueprint $table): void {
            $table->dropForeign(['reopened_by']);
            $table->dropIndex(['release_hash']);
            $table->dropColumn([
                'release_snapshot',
                'release_hash',
                'released_at',
                'reopened_by',
                'reopened_at',
                'reopen_reason',
            ]);
        });
    }
};
