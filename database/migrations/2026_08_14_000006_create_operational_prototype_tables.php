<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('role', 30)->default('producer')->after('email');
            $table->boolean('is_active')->default(true)->after('role');
        });

        Schema::create('clients', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('industry')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('contacts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('role')->nullable();
            $table->timestamps();
        });

        Schema::table('opportunities', function (Blueprint $table): void {
            $table->foreignId('client_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->foreignId('owner_id')->nullable()->after('client_id')->constrained('users')->nullOnDelete();
            $table->string('location')->nullable()->after('event_date');
            $table->text('objective')->nullable()->after('location');
            $table->text('stage_note')->nullable()->after('briefing_status');
            $table->timestamp('last_viewed_at')->nullable()->after('stage_note');
        });

        Schema::create('activities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('opportunity_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('type', 30)->default('task');
            $table->string('priority', 20)->default('normal');
            $table->dateTime('due_at')->nullable()->index();
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('budgets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('opportunity_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->string('status', 30)->default('draft');
            $table->text('notes')->nullable();
            $table->dateTime('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['opportunity_id', 'version']);
        });

        Schema::create('budget_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('budget_id')->constrained()->cascadeOnDelete();
            $table->string('category');
            $table->string('description');
            $table->decimal('quantity', 10, 2)->default(1);
            $table->string('unit', 30)->default('unidade');
            $table->unsignedBigInteger('unit_cost_cents')->default(0);
            $table->unsignedBigInteger('tax_cents')->default(0);
            $table->unsignedBigInteger('contingency_cents')->default(0);
            $table->decimal('margin_percent', 6, 2)->default(0);
            $table->string('supplier')->nullable();
            $table->date('quote_valid_until')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('opportunity_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30);
            $table->unsignedInteger('version')->default(1);
            $table->string('status', 30)->default('draft');
            $table->string('title');
            $table->json('content')->nullable();
            $table->text('notes')->nullable();
            $table->dateTime('sent_at')->nullable();
            $table->dateTime('accepted_at')->nullable();
            $table->dateTime('signed_at')->nullable();
            $table->timestamps();
            $table->unique(['opportunity_id', 'type', 'version']);
        });

        Schema::create('production_tasks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('opportunity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status', 20)->default('todo');
            $table->string('priority', 20)->default('normal');
            $table->date('due_date')->nullable()->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('post_event_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('opportunity_id')->unique()->constrained()->cascadeOnDelete();
            $table->text('summary')->nullable();
            $table->json('occurrences')->nullable();
            $table->unsignedBigInteger('actual_total_cents')->nullable();
            $table->text('learnings')->nullable();
            $table->string('status', 30)->default('draft');
            $table->timestamps();
        });

        Schema::create('attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('opportunity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('briefing_message_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('original_name');
            $table->string('path');
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 100)->index();
            $table->string('subject_type', 120)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['subject_type', 'subject_id']);
        });

        Schema::create('onboarding_progress', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->json('completed_steps')->nullable();
            $table->boolean('dismissed')->default(false);
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('usage_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event', 100)->index();
            $table->json('context')->nullable();
            $table->timestamps();
        });

        Schema::create('prototype_feedback', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->string('category', 40);
            $table->text('comment')->nullable();
            $table->string('context')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['prototype_feedback', 'usage_events', 'onboarding_progress', 'audit_logs', 'attachments', 'post_event_reports', 'production_tasks', 'documents', 'budget_items', 'budgets', 'activities'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::table('opportunities', function (Blueprint $table): void {
            $table->dropForeign(['client_id']);
            $table->dropForeign(['owner_id']);
            $table->dropColumn(['client_id', 'owner_id', 'location', 'objective', 'stage_note', 'last_viewed_at']);
        });
        Schema::dropIfExists('contacts');
        Schema::dropIfExists('clients');
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['role', 'is_active']);
        });
    }
};
