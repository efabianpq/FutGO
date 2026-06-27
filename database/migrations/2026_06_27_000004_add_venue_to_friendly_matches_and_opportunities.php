<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * S3-B · Vincular cancha (venue_id) a amistosos y oportunidades.
 * Nullable: el campo libre location/zone/city sigue disponible como fallback.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('friendly_matches', function (Blueprint $table) {
            $table->foreignId('venue_id')->nullable()->after('location')
                  ->constrained('venues')->nullOnDelete();
        });

        Schema::table('opportunities', function (Blueprint $table) {
            $table->foreignId('venue_id')->nullable()->after('zone')
                  ->constrained('venues')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('opportunities', function (Blueprint $table) {
            $table->dropForeign(['venue_id']);
            $table->dropColumn('venue_id');
        });

        Schema::table('friendly_matches', function (Blueprint $table) {
            $table->dropForeign(['venue_id']);
            $table->dropColumn('venue_id');
        });
    }
};
