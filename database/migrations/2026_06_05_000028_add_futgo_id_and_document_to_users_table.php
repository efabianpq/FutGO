<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sesión D — Credencial QR antifraude.
 *
 * - futgo_id: identificador público ÚNICO y permanente del jugador en el
 *   ecosistema FUTGO (formato FG-XXXXXX). Es el valor que viaja en el QR de la
 *   credencial y el que un árbitro puede ingresar a mano para validar.
 * - document: número de documento de identidad del usuario registrado (opcional,
 *   recomendado). NO se expone en el QR ni en URLs — es dato sensible.
 *
 * Backfill: se puebla futgo_id en todos los usuarios existentes garantizando
 * unicidad (alfabeto sin caracteres ambiguos 0/O/1/I/L/U).
 */
return new class extends Migration
{
    /** Alfabeto sin caracteres ambiguos (Crockford-ish). */
    private const ALPHABET = '23456789ABCDEFGHJKMNPQRSTVWXYZ';

    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('futgo_id', 12)->nullable()->unique()->after('id');
            $table->string('document', 40)->nullable()->after('phone_whatsapp');
        });

        // Backfill: asignar un identificador único a cada usuario existente.
        $used = [];
        DB::table('users')->whereNull('futgo_id')->orderBy('id')->select('id')->chunkById(200, function ($rows) use (&$used) {
            foreach ($rows as $row) {
                $id = $this->generateUnique($used);
                $used[$id] = true;
                DB::table('users')->where('id', $row->id)->update(['futgo_id' => $id]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['futgo_id']);
            $table->dropColumn(['futgo_id', 'document']);
        });
    }

    /** Genera un FG-XXXXXX libre, evitando los ya tomados (BD + lote actual). */
    private function generateUnique(array $used): string
    {
        do {
            $id = $this->random();
        } while (isset($used[$id]) || DB::table('users')->where('futgo_id', $id)->exists());

        return $id;
    }

    private function random(): string
    {
        $body = '';
        $max = strlen(self::ALPHABET) - 1;
        for ($i = 0; $i < 6; $i++) {
            $body .= self::ALPHABET[random_int(0, $max)];
        }

        return 'FG-' . $body;
    }
};
