<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_plans', function (Blueprint $t) {
            $t->id();
            $t->foreignId('opportunity_id')->unique()->constrained()->restrictOnDelete();
            $t->unsignedBigInteger('total_cents');
            $t->unsignedInteger('revision')->default(1);
            $t->string('status', 20)->default('draft');
            $t->foreignId('accepted_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->timestamp('accepted_at')->nullable();
            $t->text('acceptance_evidence')->nullable();
            $t->timestamps();
        });
        Schema::create('payment_plan_installments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('payment_plan_id')->constrained()->restrictOnDelete();
            $t->unsignedInteger('sequence');
            $t->string('label', 180);
            $t->unsignedInteger('share_bps');
            $t->unsignedBigInteger('amount_cents');
            $t->string('trigger', 30);
            $t->integer('offset_days')->nullable();
            $t->date('due_at')->nullable();
            $t->string('milestone', 180)->nullable();
            $t->timestamps();
            $t->unique(['payment_plan_id', 'sequence'], 'plan_installment_sequence');
        });
        Schema::create('receivable_previews', function (Blueprint $t) {
            $t->id();
            $t->foreignId('opportunity_id')->constrained()->restrictOnDelete();
            $t->foreignId('payment_plan_id')->constrained()->restrictOnDelete();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->unsignedInteger('plan_revision');
            $t->string('context_hash', 64);
            $t->json('items');
            $t->timestamp('confirmed_at')->nullable();
            $t->timestamps();
        });
        foreach (['receivables', 'payables'] as $table) {
            Schema::create($table, function (Blueprint $t) use ($table) {
                $t->id();
                $t->foreignId('opportunity_id')->constrained()->restrictOnDelete();
                $t->string('label', 180);
                $t->string('category', 100)->nullable();
                $t->unsignedBigInteger('amount_cents');
                $t->date('due_at')->nullable();
                $t->string('status', 20)->default('open');
                $t->unsignedInteger('revision')->default(0);
                $t->text('cancellation_reason')->nullable();
                $t->timestamp('cancelled_at')->nullable();
                if ($table === 'receivables') {
                    $t->foreignId('payment_plan_installment_id')->unique()->constrained()->restrictOnDelete();
                    $t->string('trigger', 30);
                    $t->string('milestone', 180)->nullable();
                } else {
                    $t->string('origin_type', 30);
                    $t->unsignedBigInteger('origin_id');
                    $t->json('origin_snapshot');
                    $t->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
                    $t->timestamp('approved_at')->nullable();
                    $t->unique(['opportunity_id', 'origin_type', 'origin_id'], 'payable_origin');
                }
                $t->timestamps();
                $t->index(['opportunity_id', 'status']);
            });
        }
        Schema::create('financial_settlements', function (Blueprint $t) {
            $t->id();
            $t->foreignId('opportunity_id')->constrained()->restrictOnDelete();
            $t->string('ledger', 20);
            $t->unsignedBigInteger('entry_id');
            $t->string('request_key', 100);
            $t->unsignedBigInteger('amount_cents');
            $t->date('paid_at');
            $t->text('evidence');
            $t->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $t->timestamps();
            $t->unique(['opportunity_id', 'request_key'], 'settlement_idempotency');
            $t->index(['ledger', 'entry_id']);
        });
        Schema::create('event_cost_results', function (Blueprint $t) {
            $t->id();
            $t->foreignId('opportunity_id')->constrained()->restrictOnDelete();
            $t->string('category', 100);
            $t->unsignedBigInteger('actual_cents');
            $t->text('variance_reason')->nullable();
            $t->unsignedInteger('revision')->default(1);
            $t->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $t->timestamps();
            $t->unique(['opportunity_id', 'category'], 'event_cost_category');
        });
    }

    public function down(): void
    {
        foreach (['event_cost_results', 'financial_settlements', 'payables', 'receivables', 'receivable_previews', 'payment_plan_installments', 'payment_plans'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
