<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * S1-F Moderación: suspensión de usuario, ocultamiento de contenido y
 * trazabilidad de la acción tomada sobre cada content_report.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Suspensión de usuario: temporal con vencimiento opcional.
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_suspended')->default(false)->after('role');
            $table->timestamp('suspended_until')->nullable()->after('is_suspended');
            $table->string('suspended_reason', 255)->nullable()->after('suspended_until');
        });

        // Ocultamiento de contenido sin borrado (trazabilidad).
        Schema::table('opportunities', function (Blueprint $table) {
            $table->boolean('is_hidden')->default(false)->after('status');
            $table->index('is_hidden');
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->boolean('is_hidden')->default(false)->after('type');
        });

        // Trazabilidad de la decisión administrativa sobre cada reporte.
        Schema::table('content_reports', function (Blueprint $table) {
            // 'dismissed' (reporte infundado) | 'hidden' (contenido oculto) | 'suspended' (usuario suspendido)
            $table->string('resolution_action', 30)->nullable()->after('reviewed_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_suspended', 'suspended_until', 'suspended_reason']);
        });

        Schema::table('opportunities', function (Blueprint $table) {
            $table->dropIndex(['is_hidden']);
            $table->dropColumn('is_hidden');
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn('is_hidden');
        });

        Schema::table('content_reports', function (Blueprint $table) {
            $table->dropColumn('resolution_action');
        });
    }
};
