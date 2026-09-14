<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brief_requirements', function (Blueprint $t) {
            $t->id();
            $t->foreignId('event_brief_id')->constrained()->cascadeOnDelete();
            $t->string('area', 40);
            $t->text('requirement');
            $t->decimal('quantity', 12, 2)->default(1);
            $t->string('unit', 40)->default('pacote');
            $t->string('priority', 20)->default('obrigatorio');
            $t->string('status', 20)->default('draft');
            $t->string('classification', 20)->default('unknown');
            $t->text('source');
            $t->json('evidence_segment_ids')->nullable();
            $t->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->dateTime('confirmed_at')->nullable();
            $t->foreignId('supplier_need_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamps();
        });
        Schema::create('brief_need_previews', function (Blueprint $t) {
            $t->id();
            $t->foreignId('event_brief_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('brief_revision');
            $t->foreignId('created_by')->constrained('users');
            $t->json('items');
            $t->string('status', 20)->default('pending');
            $t->json('result')->nullable();
            $t->timestamps();
        });
        Schema::create('brief_program_blocks', function (Blueprint $t) {
            $t->id();
            $t->foreignId('event_brief_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('sequence');
            $t->dateTime('starts_at');
            $t->dateTime('ends_at');
            $t->string('title', 200);
            $t->text('description')->nullable();
            $t->text('location_note')->nullable();
            $t->string('responsible_area', 40)->nullable();
            $t->unsignedInteger('attendees_estimate')->nullable();
            $t->text('source');
            $t->json('evidence_segment_ids')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brief_program_blocks');
        Schema::dropIfExists('brief_need_previews');
        Schema::dropIfExists('brief_requirements');
    }
};
