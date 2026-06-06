<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Patrocinadores del torneo (Sesión G) — espacio de monetización futura.
 * Solo el ESPACIO y la gestión básica: nombre, logo y enlace. SIN lógica de cobro.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournament_sponsors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained('tournaments')->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('logo_url', 255)->nullable();
            $table->string('link_url', 255)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tournament_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournament_sponsors');
    }
};
