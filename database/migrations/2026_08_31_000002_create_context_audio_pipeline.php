<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audio_upload_sessions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('opportunity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('case_context_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->string('original_name');
            $table->string('declared_mime', 120);
            $table->unsignedBigInteger('expected_bytes');
            $table->unsignedBigInteger('received_bytes')->default(0);
            $table->unsignedInteger('expected_chunks');
            $table->unsignedInteger('received_chunks')->default(0);
            $table->char('expected_digest', 64)->nullable();
            $table->char('calculated_digest', 64)->nullable();
            $table->string('temporary_path');
            $table->string('status', 30)->default('uploading')->index();
            $table->timestamp('expires_at')->index();
            $table->timestamps();
        });

        Schema::create('context_audio_assets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('case_context_entry_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('original_path');
            $table->string('prepared_path')->nullable();
            $table->string('original_mime', 120);
            $table->string('prepared_mime', 120)->nullable();
            $table->unsignedBigInteger('original_bytes');
            $table->unsignedBigInteger('prepared_bytes')->nullable();
            $table->unsignedInteger('duration_ms');
            $table->char('digest', 64)->index();
            $table->string('status', 30)->default('inspecting')->index();
            $table->string('provider_request_id')->nullable()->index();
            $table->string('transcription_model', 100)->nullable();
            $table->unsignedInteger('transcript_revision')->default(0);
            $table->string('error_code', 80)->nullable();
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::table('case_context_segments', function (Blueprint $table): void {
            $table->unsignedInteger('sequence')->default(0)->after('case_context_entry_id');
            $table->unsignedInteger('source_chunk')->default(0)->after('sequence');
            $table->string('provider_segment_id')->nullable()->after('source_chunk');
        });
        $sequences = [];
        foreach (DB::table('case_context_segments')->orderBy('case_context_entry_id')->orderBy('id')->get(['id', 'case_context_entry_id']) as $segment) {
            $sequence = $sequences[$segment->case_context_entry_id] ?? 0;
            DB::table('case_context_segments')->where('id', $segment->id)->update(['sequence' => $sequence]);
            $sequences[$segment->case_context_entry_id] = $sequence + 1;
        }
        Schema::table('case_context_segments', function (Blueprint $table): void {
            $table->unique(['case_context_entry_id', 'sequence'], 'context_segments_entry_sequence_unique');
        });

        Schema::table('case_context_entries', function (Blueprint $table): void {
            $table->unsignedInteger('case_revision')->default(0)->after('revision');
            $table->string('schema_version', 40)->nullable()->after('case_revision');
        });

        Schema::create('ai_cost_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('opportunity_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('case_context_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ai_run_id')->nullable()->constrained()->nullOnDelete();
            $table->string('operation', 50);
            $table->string('provider', 50);
            $table->string('model', 100)->nullable();
            $table->string('provider_request_id')->nullable()->index();
            $table->unsignedBigInteger('audio_seconds')->default(0);
            $table->unsignedBigInteger('input_tokens')->default(0);
            $table->unsignedBigInteger('cached_input_tokens')->default(0);
            $table->unsignedBigInteger('output_tokens')->default(0);
            $table->unsignedBigInteger('estimated_amount_micros')->default(0);
            $table->unsignedBigInteger('reported_amount_micros')->nullable();
            $table->unsignedBigInteger('reconciled_amount_micros')->nullable();
            $table->char('currency', 3)->default('USD');
            $table->string('pricing_version', 40);
            $table->char('idempotency_key', 64)->unique();
            $table->string('status', 30)->default('reserved')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('odoo_cost_outbox', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('opportunity_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('case_context_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->char('idempotency_key', 64)->unique();
            $table->unsignedInteger('payload_version')->default(1);
            $table->json('payload');
            $table->string('status', 30)->default('pending')->index();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('next_attempt_at')->nullable()->index();
            $table->string('external_id')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('exported_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('odoo_cost_outbox');
        Schema::dropIfExists('ai_cost_entries');

        Schema::table('case_context_entries', function (Blueprint $table): void {
            $table->dropColumn(['case_revision', 'schema_version']);
        });
        Schema::table('case_context_segments', function (Blueprint $table): void {
            $table->dropUnique('context_segments_entry_sequence_unique');
            $table->dropColumn(['sequence', 'source_chunk', 'provider_segment_id']);
        });

        Schema::dropIfExists('context_audio_assets');
        Schema::dropIfExists('audio_upload_sessions');
    }
};
