<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * S3-B · Venues (canchas): catálogo compartido de instalaciones deportivas.
 * Cualquier usuario registrado puede proponer una cancha; no pertenece a ningún
 * club ni torneo. Se vincula opcionalmente a amistosos y oportunidades.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venues', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('city');
            $table->string('address')->nullable();
            $table->string('surface_type')->nullable(); // cesped_natural, cesped_sintetico, tierra, cemento, parquet, otro
            $table->unsignedSmallInteger('approx_capacity')->nullable();
            $table->string('maps_url', 1000)->nullable();
            $table->json('photos')->nullable(); // array de paths relativos
            $table->foreignId('registered_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('city');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venues');
    }
};
