<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('post_event_reports', function (Blueprint $table): void {
            $table->unsignedBigInteger('planned_total_cents')->nullable()->after('actual_total_cents');
            $table->json('supplier_evaluations')->nullable()->after('learnings');
            $table->json('closure_items')->nullable()->after('supplier_evaluations');
        });
    }

    public function down(): void
    {
        Schema::table('post_event_reports', function (Blueprint $table): void {
            $table->dropColumn(['planned_total_cents', 'supplier_evaluations', 'closure_items']);
        });
    }
};
