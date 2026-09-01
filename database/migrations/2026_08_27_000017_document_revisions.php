<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $t) {
            $t->string('purpose')->nullable();
            $t->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->timestamp('reviewed_at')->nullable();
            $t->string('pdf_path')->nullable();
            $t->string('pdf_hash', 64)->nullable();
            $t->text('send_evidence')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $t) {
            $t->dropForeign(['reviewed_by']);
            $t->dropColumn(['purpose', 'reviewed_by', 'reviewed_at', 'pdf_path', 'pdf_hash', 'send_evidence']);
        });
    }
};
