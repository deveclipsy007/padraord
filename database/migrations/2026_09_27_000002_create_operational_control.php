<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_pending_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('opportunity_id')->constrained()->cascadeOnDelete();
            $t->foreignId('owner_id')->constrained('users');
            $t->string('title', 180);
            $t->string('kind', 30);
            $t->string('awaiting', 16);
            $t->string('status', 20)->default('open');
            $t->date('due_date')->nullable();
            $t->date('next_contact_at')->nullable();
            $t->text('resolution')->nullable();
            $t->unsignedInteger('revision')->default(0);
            $t->timestamps();
            $t->index(['status', 'due_date']);
        });
        Schema::create('client_portal_links', function (Blueprint $t) {
            $t->id();
            $t->foreignId('opportunity_id')->constrained()->cascadeOnDelete();
            $t->foreignId('created_by')->constrained('users');
            $t->char('token_hash', 64)->unique();
            $t->json('snapshot');
            $t->timestamp('expires_at');
            $t->timestamp('revoked_at')->nullable();
            $t->timestamps();
        });
        Schema::create('client_portal_responses', function (Blueprint $t) {
            $t->id();
            $t->foreignId('client_portal_link_id')->constrained()->cascadeOnDelete();
            $t->string('item_key', 80);
            $t->string('decision', 24);
            $t->string('name', 160);
            $t->text('message')->nullable();
            $t->timestamps();
            $t->unique(['client_portal_link_id', 'item_key'], 'portal_response_once');
        });
        Schema::create('client_portal_uploads', function (Blueprint $t) {
            $t->id();
            $t->foreignId('client_portal_link_id')->constrained()->cascadeOnDelete();
            $t->string('name', 160);
            $t->string('original_name');
            $t->string('path');
            $t->unsignedInteger('size');
            $t->timestamps();
        });
        Schema::create('operational_rules', function (Blueprint $t) {
            $t->id();
            $t->string('rule_key', 60)->unique();
            $t->boolean('enabled')->default(false);
            $t->foreignId('enabled_by')->nullable()->constrained('users');
            $t->timestamp('last_run_at')->nullable();
            $t->timestamps();
        });
        Schema::create('operational_rule_runs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('operational_rule_id')->constrained()->cascadeOnDelete();
            $t->string('source_key', 100);
            $t->foreignId('activity_id')->nullable()->constrained()->nullOnDelete();
            $t->json('original');
            $t->timestamp('undone_at')->nullable();
            $t->timestamps();
            $t->unique(['operational_rule_id', 'source_key'], 'operational_rule_once');
        });
        Schema::table('users', fn (Blueprint $t) => $t->string('workspace_focus', 20)->nullable());
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('workspace_focus'));
        Schema::dropIfExists('operational_rule_runs');
        Schema::dropIfExists('operational_rules');
        Schema::dropIfExists('client_portal_uploads');
        Schema::dropIfExists('client_portal_responses');
        Schema::dropIfExists('client_portal_links');
        Schema::dropIfExists('client_pending_items');
    }
};
