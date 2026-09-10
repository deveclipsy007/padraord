<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O local do evento era uma string em opportunities.location e uma referência
 * solta em technical_validations. Produtora reusa local: cada visita técnica
 * redescobria largura de porta, energia disponível e horário limite de som.
 *
 * As medidas de acesso de carga existem porque é o que decide se a estrutura
 * contratada entra no espaço — descobrir isso na montagem custa o evento.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venues', function (Blueprint $t): void {
            $t->id();
            $t->string('name');
            $t->string('venue_type')->default('outro');
            $t->string('tax_id', 14)->nullable();

            $t->json('address')->nullable();
            $t->string('city')->nullable()->index();
            $t->string('state', 2)->nullable();

            $t->unsignedInteger('capacity_seated')->nullable();
            $t->unsignedInteger('capacity_standing')->nullable();
            $t->unsignedInteger('capacity_cocktail')->nullable();
            $t->unsignedInteger('capacity_auditorium')->nullable();

            $t->decimal('floor_area_m2', 10, 2)->nullable();
            $t->decimal('ceiling_height_m', 6, 2)->nullable();
            $t->text('column_notes')->nullable();
            $t->decimal('floor_load_kg_m2', 10, 2)->nullable();

            $t->decimal('door_width_m', 6, 2)->nullable();
            $t->decimal('door_height_m', 6, 2)->nullable();
            $t->boolean('has_loading_dock')->default(false);
            $t->boolean('has_freight_elevator')->default(false);
            $t->unsignedInteger('elevator_capacity_kg')->nullable();
            $t->text('load_in_notes')->nullable();

            $t->decimal('power_available_kva', 8, 2)->nullable();
            $t->string('power_phases')->nullable();
            $t->boolean('has_generator_area')->default(false);
            $t->text('power_notes')->nullable();

            $t->time('noise_curfew_time')->nullable();
            $t->string('load_in_window')->nullable();
            $t->string('load_out_window')->nullable();
            $t->unsignedInteger('parking_spots')->nullable();
            $t->boolean('has_kitchen')->default(false);
            $t->string('catering_policy')->default('livre');

            $t->text('restrictions')->nullable();
            $t->string('rules_document_path')->nullable();
            $t->string('contact_name')->nullable();
            $t->string('contact_phone')->nullable();
            $t->string('contact_email')->nullable();
            $t->text('notes')->nullable();
            $t->string('status')->default('ativo')->index();
            $t->unsignedInteger('revision')->default(0);
            $t->timestamps();
        });

        Schema::table('opportunities', function (Blueprint $t): void {
            $t->foreignId('venue_id')->nullable()->constrained('venues')->nullOnDelete();
            // A string original não é descartada: vira anotação até alguém
            // reconhecer o local e cadastrá-lo.
            $t->string('location_note')->nullable();
        });

        Schema::table('technical_validations', function (Blueprint $t): void {
            $t->foreignId('venue_id')->nullable()->constrained('venues')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('technical_validations', function (Blueprint $t): void {
            $t->dropConstrainedForeignId('venue_id');
        });
        Schema::table('opportunities', function (Blueprint $t): void {
            $t->dropConstrainedForeignId('venue_id');
            $t->dropColumn('location_note');
        });
        Schema::dropIfExists('venues');
    }
};
