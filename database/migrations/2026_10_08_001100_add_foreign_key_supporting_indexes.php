<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tipos_documentos', function (Blueprint $table) {
            $table->index('created_by');
        });

        Schema::table('documentos', function (Blueprint $table) {
            $table->index('document_type_id');
            $table->index('annulled_by');
        });

        Schema::table('destinatarios_documentos', function (Blueprint $table) {
            $table->index('received_by');
            $table->index('decided_by');
        });

        Schema::table('versiones_documentos', function (Blueprint $table) {
            $table->index('uploaded_by');
        });

        Schema::table('archivos_documentos', function (Blueprint $table) {
            $table->index('uploaded_by');
        });

        Schema::table('observaciones_documentos', function (Blueprint $table) {
            $table->index('author_id');
        });

        Schema::table('concesiones_acceso_documentos', function (Blueprint $table) {
            $table->index('granted_by');
            $table->index('revoked_by');
        });

        Schema::table('eventos_documentos', function (Blueprint $table) {
            $table->index('document_recipient_id');
        });

        Schema::table('delegaciones_temporales', function (Blueprint $table) {
            $table->index('authorized_by');
            $table->index('revoked_by');
        });
    }

    public function down(): void
    {
        Schema::table('delegaciones_temporales', function (Blueprint $table) {
            $table->dropIndex(['authorized_by']);
            $table->dropIndex(['revoked_by']);
        });

        Schema::table('eventos_documentos', function (Blueprint $table) {
            $table->dropIndex(['document_recipient_id']);
        });

        Schema::table('concesiones_acceso_documentos', function (Blueprint $table) {
            $table->dropIndex(['granted_by']);
            $table->dropIndex(['revoked_by']);
        });

        Schema::table('observaciones_documentos', function (Blueprint $table) {
            $table->dropIndex(['author_id']);
        });

        Schema::table('archivos_documentos', function (Blueprint $table) {
            $table->dropIndex(['uploaded_by']);
        });

        Schema::table('versiones_documentos', function (Blueprint $table) {
            $table->dropIndex(['uploaded_by']);
        });

        Schema::table('destinatarios_documentos', function (Blueprint $table) {
            $table->dropIndex(['received_by']);
            $table->dropIndex(['decided_by']);
        });

        Schema::table('documentos', function (Blueprint $table) {
            $table->dropIndex(['document_type_id']);
            $table->dropIndex(['annulled_by']);
        });

        Schema::table('tipos_documentos', function (Blueprint $table) {
            $table->dropIndex(['created_by']);
        });
    }
};
