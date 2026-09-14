<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_briefs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('opportunity_id')->unique()->constrained()->cascadeOnDelete();
            $t->unsignedInteger('revision')->default(0);
            $t->unsignedInteger('schema_version')->default(1);
            $t->string('status', 20)->default('draft');
            $t->string('event_name', 200);
            $t->string('event_type', 40)->nullable();
            $t->string('event_format', 20)->default('presencial');
            $t->string('edition', 80)->nullable();
            $t->boolean('is_recurring')->default(false);
            $t->foreignId('previous_opportunity_id')->nullable()->constrained('opportunities')->nullOnDelete();
            foreach (['starts_at', 'ends_at', 'setup_starts_at', 'teardown_ends_at'] as $field) {
                $t->dateTime($field)->nullable();
            }
            $t->string('timezone', 80)->default('America/Sao_Paulo');
            foreach (['date_confidence', 'audience_confidence', 'budget_confidence'] as $field) {
                $t->string($field, 20)->default('unknown');
            }
            $t->foreignId('venue_id')->nullable()->constrained()->nullOnDelete();
            $t->string('venue_status', 20)->default('unknown');
            $t->string('city', 120)->nullable();
            $t->string('state', 2)->nullable();
            foreach (['audience_expected_min', 'audience_expected_max'] as $field) {
                $t->unsignedInteger($field)->nullable();
            }
            foreach (['budget_declared_cents', 'budget_range_min_cents', 'budget_range_max_cents'] as $field) {
                $t->unsignedBigInteger($field)->nullable();
            }
            $t->boolean('budget_includes_taxes')->default(true);
            $t->boolean('has_vip')->default(false);
            foreach (['location_note', 'venue_requirements', 'audience_profile', 'vip_notes', 'accessibility_requirements', 'objective', 'key_message', 'tone', 'brand_notes', 'payment_expectation', 'scope_summary', 'budget_notes', 'restrictions_notes', 'references_notes', 'legacy_date_note'] as $field) {
                $t->text($field)->nullable();
            }
            foreach (['alternative_dates', 'audience_segments', 'success_criteria', 'brand_assets', 'constraints', 'risks', 'deadlines', 'references', 'legacy_snapshot', 'missing_critical'] as $field) {
                $t->json($field)->nullable();
            }
            $t->unsignedSmallInteger('completeness_score')->default(0);
            $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->dateTime('approved_at')->nullable();
            $t->timestamps();
        });
        Schema::create('event_brief_revisions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('event_brief_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('revision');
            $t->string('action', 30);
            $t->json('snapshot');
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->text('reason')->nullable();
            $t->timestamps();
            $t->unique(['event_brief_id', 'revision']);
        });
        DB::table('opportunities')->orderBy('id')->chunkById(200, function ($rows) {
            foreach ($rows as $o) {
                $legacy = json_decode($o->briefing_data ?? '{}', true) ?? [];
                DB::table('event_briefs')->insert(['opportunity_id' => $o->id, 'revision' => $o->briefing_revision, 'event_name' => $o->title, 'objective' => $legacy['objective'] ?? $o->objective,
                    'audience_profile' => $legacy['audience'] ?? null, 'location_note' => $legacy['location'] ?? $o->location, 'legacy_date_note' => $legacy['event_date'] ?? $o->event_date,
                    'budget_notes' => $legacy['budget'] ?? null, 'scope_summary' => $legacy['scope'] ?? null, 'restrictions_notes' => $legacy['restrictions'] ?? null, 'references_notes' => $legacy['references'] ?? null,
                    'legacy_snapshot' => json_encode($legacy), 'created_at' => now(), 'updated_at' => now()]);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_brief_revisions');
        Schema::dropIfExists('event_briefs');
    }
};
