<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * S3-A · Modo rápido: una oportunidad "express" tiene vigencia corta (24-48h)
 * para necesidades de último momento ("necesito rival para mañana"). La marca
 * es una columna booleana queryable para destacarla en el listado y el Feed.
 * El vencimiento sigue resolviéndose por `expires_at` (no requiere lógica nueva).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opportunities', function (Blueprint $table) {
            $table->boolean('is_express')->default(false)->after('status');
            $table->index('is_express');
        });
    }

    public function down(): void
    {
        Schema::table('opportunities', function (Blueprint $table) {
            $table->dropIndex(['is_express']);
            $table->dropColumn('is_express');
        });
    }
};
