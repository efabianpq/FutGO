<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Centro de Privacidad · Campos aditivos en users.
 *
 * - birthdate: para edad mínima (14) y política de menores.
 * - document_hash: blind index (HMAC) para dedupe/reclamo, ya que `document`
 *   pasa a cifrarse con AsEncryptedString y no admite WHERE directo.
 * - current_privacy_version / current_terms_version: cache de la última versión
 *   aceptada, para detectar re-consentimiento sin joins.
 * - guardian_email / pending_guardian_consent: consentimiento parental de menores.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->date('birthdate')->nullable()->after('document');
            $table->string('document_hash', 64)->nullable()->after('document');
            $table->string('current_privacy_version', 20)->nullable()->after('delete_requested_at');
            $table->string('current_terms_version', 20)->nullable()->after('current_privacy_version');
            $table->string('guardian_email', 150)->nullable()->after('current_terms_version');
            $table->boolean('pending_guardian_consent')->default(false)->after('guardian_email');

            $table->index('document_hash');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['document_hash']);
            $table->dropColumn([
                'birthdate',
                'document_hash',
                'current_privacy_version',
                'current_terms_version',
                'guardian_email',
                'pending_guardian_consent',
            ]);
        });
    }
};
