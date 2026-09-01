<?php

use App\Enums\CommercialStage;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table): void {
            $table->timestamp('archived_at')->nullable()->index();
            $table->foreignId('archived_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('archive_reason')->nullable();
        });

        Schema::table('contacts', function (Blueprint $table): void {
            $table->timestamp('archived_at')->nullable()->index();
            $table->foreignId('archived_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('archive_reason')->nullable();
        });

        Schema::table('opportunities', function (Blueprint $table): void {
            $table->string('commercial_stage', 40)->default(CommercialStage::LEAD->value)->index();
            $table->string('origin', 40)->default('other')->index();
            $table->string('priority', 20)->default('normal')->index();
            $table->unsignedInteger('commercial_revision')->default(0);
            $table->timestamp('archived_at')->nullable()->index();
            $table->foreignId('archived_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('archive_reason')->nullable();
            $table->string('reason_category', 40)->nullable();
            $table->text('reason_note')->nullable();
        });

        Schema::table('activities', function (Blueprint $table): void {
            $table->boolean('is_next_action')->default(false)->index();
        });

        Schema::create('opportunity_qualifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('opportunity_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('revision')->default(0);
            $table->text('need_summary')->nullable();
            $table->string('decision_maker_status', 20)->default('unknown');
            $table->foreignId('decision_maker_contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->string('event_date_status', 20)->default('unknown');
            $table->string('budget_status', 20)->default('unknown');
            $table->string('fit_status', 20)->default('unknown');
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('not_started');
            $table->timestamp('qualified_at')->nullable();
            $table->foreignId('qualified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        DB::table('opportunities')->where('stage', 'qualification')->update(['commercial_stage' => 'qualification']);
        DB::table('opportunities')->where('stage', 'briefing')->update(['commercial_stage' => 'initial_briefing']);
        DB::table('opportunities')->whereIn('stage', ['budget', 'proposal', 'negotiation'])->update(['commercial_stage' => 'viability_offer']);
        DB::table('opportunities')->whereIn('stage', ['contract', 'pre_production', 'production', 'post_event', 'closed'])->update(['commercial_stage' => 'viability_contracted']);
        DB::table('opportunities')->where('stage', 'lost')->update(['commercial_stage' => 'lost']);
        DB::table('opportunities')->where('stage', 'cancelled')->update(['commercial_stage' => 'cancelled']);

        DB::table('opportunities')->whereNotNull('next_action')->where('next_action', '!=', '')->get(['id', 'owner_id', 'next_action', 'next_action_at'])->each(function (object $opportunity): void {
            $exists = DB::table('activities')->where('opportunity_id', $opportunity->id)->where('title', $opportunity->next_action)->exists();
            if ($exists) {
                return;
            }

            DB::table('activities')->insert([
                'opportunity_id' => $opportunity->id,
                'user_id' => $opportunity->owner_id,
                'title' => $opportunity->next_action,
                'type' => 'follow_up',
                'priority' => 'normal',
                'due_at' => $opportunity->next_action_at,
                'status' => 'todo',
                'is_next_action' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opportunity_qualifications');
        Schema::table('activities', fn (Blueprint $table) => $table->dropColumn('is_next_action'));
        Schema::table('opportunities', function (Blueprint $table): void {
            $table->dropForeign(['archived_by']);
            $table->dropColumn(['commercial_stage', 'origin', 'priority', 'commercial_revision', 'archived_at', 'archived_by', 'archive_reason', 'reason_category', 'reason_note']);
        });
        foreach (['contacts', 'clients'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropForeign(['archived_by']);
                $table->dropColumn(['archived_at', 'archived_by', 'archive_reason']);
            });
        }
    }
};
