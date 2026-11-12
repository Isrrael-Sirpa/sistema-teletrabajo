<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documentos', function (Blueprint $table) {
            $table->softDeletesTz();
            $table->index('deleted_at');
        });

        Schema::table('destinatarios_documentos', function (Blueprint $table) {
            $table->softDeletesTz();
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        if (DB::table('documentos')->whereNotNull('deleted_at')->exists()
            || DB::table('destinatarios_documentos')->whereNotNull('deleted_at')->exists()) {
            throw new RuntimeException('No se puede revertir mientras existan documentos o destinatarios descartados lógicamente.');
        }

        Schema::table('destinatarios_documentos', function (Blueprint $table) {
            $table->dropIndex(['deleted_at']);
            $table->dropSoftDeletesTz();
        });

        Schema::table('documentos', function (Blueprint $table) {
            $table->dropIndex(['deleted_at']);
            $table->dropSoftDeletesTz();
        });
    }
};
