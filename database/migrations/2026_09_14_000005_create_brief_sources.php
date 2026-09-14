<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('case_context_entries', function (Blueprint $t) {
            $t->string('title', 200)->nullable();
            $t->dateTime('meeting_date')->nullable();
            $t->string('meeting_kind', 40)->nullable();
            $t->boolean('retain_forever')->default(false);
            $t->json('participants')->nullable();
            $t->string('retention_reason', 80)->nullable();
        });
        Schema::create('brief_field_sources', function (Blueprint $t) {
            $t->id();
            $t->foreignId('event_brief_id')->constrained()->cascadeOnDelete();
            $t->string('field_path', 150);
            $t->foreignId('case_context_entry_id')->constrained()->restrictOnDelete();
            $t->json('segment_ids');
            $t->json('source_snapshot');
            $t->unsignedInteger('transcript_revision')->default(0);
            $t->char('field_value_hash', 64);
            $t->dateTime('extracted_at');
            $t->foreignId('confirmed_by')->constrained('users');
            $t->dateTime('confirmed_at');
            $t->dateTime('approved_at')->nullable();
            $t->timestamps();
            $t->index(['event_brief_id', 'field_path']);
        });
        Schema::create('brief_retained_contexts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('event_brief_id')->constrained()->cascadeOnDelete();
            $t->foreignId('case_context_entry_id')->constrained()->restrictOnDelete();
            $t->unsignedInteger('approved_revision');
            $t->timestamps();
            $t->unique(['event_brief_id', 'case_context_entry_id'], 'brief_retained_entry_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brief_retained_contexts');
        Schema::dropIfExists('brief_field_sources');
        Schema::table('case_context_entries', fn (Blueprint $t) => $t->dropColumn(['title', 'meeting_date', 'meeting_kind', 'retain_forever', 'participants', 'retention_reason']));
    }
};
