<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Centro de Privacidad · Ensancha las columnas que pasan a cifrarse.
 *
 * El payload de AsEncryptedString (iv + valor + MAC en base64) ocupa ~200-360
 * caracteres, muy por encima de los varchar(40)/varchar(20) originales. Se
 * convierten a TEXT. Además se elimina el índice sobre `document` (ahora guarda
 * texto cifrado, inindexable e inútil): las búsquedas usan `document_hash`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_players', function (Blueprint $table) {
            $table->dropIndex(['document']);
        });
        Schema::table('team_players', function (Blueprint $table) {
            $table->dropIndex(['document']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->text('document')->nullable()->change();
            $table->text('phone_whatsapp')->nullable()->change();
        });
        Schema::table('club_players', function (Blueprint $table) {
            $table->text('document')->nullable()->change();
        });
        Schema::table('team_players', function (Blueprint $table) {
            $table->text('document')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('document', 40)->nullable()->change();
            $table->string('phone_whatsapp', 20)->nullable()->change();
        });
        Schema::table('club_players', function (Blueprint $table) {
            $table->string('document', 40)->nullable()->change();
            $table->index('document');
        });
        Schema::table('team_players', function (Blueprint $table) {
            $table->string('document', 40)->nullable()->change();
            $table->index('document');
        });
    }
};
