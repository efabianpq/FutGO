<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournament_phases', function (Blueprint $table) {
            // pending  → fase aún no jugada / abierta
            // active   → fase en curso (sincronizada con is_active)
            // completed→ fase cerrada: clasificados ya avanzados, no editable
            $table->enum('status', ['pending', 'active', 'completed'])
                  ->default('pending')
                  ->after('is_active');
            $table->index('status');
        });

        // Backfill: las fases actualmente activas pasan a 'active'.
        DB::table('tournament_phases')->where('is_active', true)->update(['status' => 'active']);
    }

    public function down(): void
    {
        Schema::table('tournament_phases', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn('status');
        });
    }
};
