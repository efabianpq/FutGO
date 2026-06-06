<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sesión D — Auditoría de validaciones de credencial.
 *
 * Cada vez que un árbitro/admin valida una credencial (por QR o ingreso manual)
 * queda un registro: quién validó, a quién, en qué torneo, con qué resultado y
 * por qué método. Permite trazabilidad antifraude.
 *
 * result:
 *  - 'habilitado'    = el jugador está inscrito y activo en el torneo indicado.
 *  - 'no_habilitado' = identificado, pero no habilitado para ese torneo.
 *  - 'no_encontrado' = el identificador no corresponde a ningún jugador.
 * method: 'qr' (escaneo de la credencial) | 'manual' (ingreso del identificador).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credential_validations', function (Blueprint $table) {
            $table->id();
            $table->string('futgo_id', 12);                 // lo que se consultó (snapshot)
            $table->foreignId('validated_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('validated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('tournament_id')->nullable()->constrained('tournaments')->nullOnDelete();
            $table->enum('result', ['habilitado', 'no_habilitado', 'no_encontrado']);
            $table->enum('method', ['qr', 'manual'])->default('manual');
            $table->timestamps();

            $table->index(['validated_user_id', 'created_at']);
            $table->index(['tournament_id', 'result']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credential_validations');
    }
};
