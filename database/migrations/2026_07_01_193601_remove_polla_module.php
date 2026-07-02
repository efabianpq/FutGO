<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Elimina el módulo Polla Mundial (migrado a otra aplicación) y el gate de
 * activación por código de invitación: unifica el modelo de usuario a un solo
 * rol especial (admin maestro) + usuario general, sin distinción organizador/
 * jugador ni activación previa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('predictions');
        Schema::dropIfExists('matches');
        Schema::dropIfExists('invitation_codes');
        Schema::dropIfExists('rankings');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('match_notifications');

        // Sin diferenciación organizador/jugador: cualquier torneo_admin pasa a
        // usuario general (el acceso por-torneo ya se rige por tournament_admins).
        DB::table('users')->where('role', 'torneo_admin')->update(['role' => 'user']);

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_active', 'invitation_code', 'modules']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['user', 'admin'])->default('user')->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['user', 'admin', 'torneo_admin'])->default('user')->change();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('invitation_code', 20)->nullable();
            $table->boolean('is_active')->default(false);
            $table->string('modules', 50)->notNull()->default('torneos')->after('role');
        });
    }
};
