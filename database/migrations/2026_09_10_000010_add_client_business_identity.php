<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cliente tinha nome, setor e notas. Com isso não se emite contrato nem nota:
 * falta razão social, documento fiscal e endereço de faturamento.
 *
 * Migração aditiva: nenhum cliente existente é invalidado. O documento fiscal
 * passa a ser exigido apenas no momento de liberar contrato.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $t): void {
            $t->string('legal_name')->nullable();
            $t->string('tax_id', 14)->nullable();
            $t->string('tax_id_type')->default('cnpj');
            $t->string('state_registration')->nullable();
            $t->string('municipal_registration')->nullable();
            $t->string('billing_email')->nullable();
            $t->json('billing_address')->nullable();
            $t->unsignedSmallInteger('default_payment_terms_days')->default(30);
            $t->string('segment')->default('corporativo');
            $t->string('tier')->default('prospect');
            $t->string('website')->nullable();
            $t->string('instagram')->nullable();
        });

        // Índice, não restrição única: dois cadastros podem coexistir enquanto a
        // equipe decide qual manter. A duplicidade é apontada, não imposta.
        Schema::table('clients', function (Blueprint $t): void {
            $t->index('tax_id');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $t): void {
            $t->dropIndex(['tax_id']);
            $t->dropColumn([
                'legal_name', 'tax_id', 'tax_id_type', 'state_registration', 'municipal_registration',
                'billing_email', 'billing_address', 'default_payment_terms_days', 'segment', 'tier',
                'website', 'instagram',
            ]);
        });
    }
};
