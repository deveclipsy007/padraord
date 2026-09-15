<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_tasks', function (Blueprint $table): void {
            $table->dateTime('scheduled_starts_at')->nullable()->index();
            $table->dateTime('scheduled_ends_at')->nullable()->index();
        });

        Schema::create('production_task_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('production_task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 120)->nullable();
            $table->boolean('is_responsible')->default(false);
            $table->timestamps();
            $table->unique(['production_task_id', 'user_id']);
            $table->index(['user_id', 'production_task_id']);
        });

        Schema::create('production_checklists', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('opportunity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('production_task_id')->nullable()->constrained()->nullOnDelete();
            $table->string('phase', 24)->index();
            $table->string('title', 180);
            $table->string('status', 20)->default('open')->index();
            $table->unsignedInteger('revision')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('production_checklist_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('production_checklist_id')->constrained()->cascadeOnDelete();
            $table->string('title', 240);
            $table->boolean('requires_photo')->default(false);
            $table->foreignId('photo_attachment_id')->nullable()->constrained('attachments')->nullOnDelete();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('completed_at')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('production_service_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('opportunity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('source_quote_id')->nullable()->constrained('supplier_quotes')->nullOnDelete();
            $table->string('code', 64)->unique();
            $table->string('title', 180);
            $table->unsignedBigInteger('amount_cents')->nullable();
            $table->json('scope');
            $table->char('scope_hash', 64);
            $table->string('status', 20)->default('draft')->index();
            $table->unsignedInteger('revision')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('issued_at')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('receipt_attachment_id')->nullable()->constrained('attachments')->nullOnDelete();
            $table->dateTime('received_at')->nullable();
            $table->timestamps();
            $table->index(['opportunity_id', 'supplier_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_service_orders');
        Schema::dropIfExists('production_checklist_items');
        Schema::dropIfExists('production_checklists');
        Schema::dropIfExists('production_task_assignments');

        Schema::table('production_tasks', function (Blueprint $table): void {
            $table->dropIndex(['scheduled_starts_at']);
            $table->dropIndex(['scheduled_ends_at']);
            $table->dropColumn(['scheduled_starts_at', 'scheduled_ends_at']);
        });
    }
};
