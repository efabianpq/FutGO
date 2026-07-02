<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Centro de Privacidad · Versionado de documentos legales (Ley 1581/2012).
 *
 * Las políticas NUNCA se sobrescriben: cada cambio publica una versión nueva
 * (is_current=true) y marca la anterior como histórica. Un usuario que aceptó
 * la 1.0 debe re-aceptar cuando se publica la 1.1 (ver EnsureConsentUpToDate).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legal_documents', function (Blueprint $table) {
            $table->id();
            // privacy | terms | cookies | content | minors  (STRING, extensible sin migración)
            $table->string('type', 30);
            $table->string('version', 20);            // "1.0", "1.1", "2.0"
            $table->string('title');
            $table->longText('content');              // markdown
            $table->text('summary_of_changes')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->boolean('is_current')->default(false);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['type', 'version']);
            $table->index(['type', 'is_current']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legal_documents');
    }
};
