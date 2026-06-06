<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * H7: estado de validación del club permanente.
 *
 * - 'validado': club creado por su propio capitán (flujo normal).
 * - 'por_validar': club creado por el ADMIN de un torneo (sin capitán todavía);
 *   queda pendiente hasta que se le asigne un capitán (usuario del sistema).
 *
 * Los clubs existentes (creados por capitanes) se backfillean a 'validado'.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clubs', function (Blueprint $table) {
            $table->string('status', 20)->default('validado')->after('captain_user_id');
        });

        // Backfill explícito (todos los clubs previos fueron creados por capitanes).
        DB::table('clubs')->update(['status' => 'validado']);
    }

    public function down(): void
    {
        Schema::table('clubs', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
