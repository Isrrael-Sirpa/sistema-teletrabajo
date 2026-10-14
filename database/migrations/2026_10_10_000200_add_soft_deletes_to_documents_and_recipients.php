<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->softDeletesTz();
            $table->index('deleted_at');
        });

        Schema::table('document_recipients', function (Blueprint $table) {
            $table->softDeletesTz();
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        if (DB::table('documents')->whereNotNull('deleted_at')->exists()
            || DB::table('document_recipients')->whereNotNull('deleted_at')->exists()) {
            throw new RuntimeException('No se puede revertir mientras existan documentos o destinatarios descartados lógicamente.');
        }

        Schema::table('document_recipients', function (Blueprint $table) {
            $table->dropIndex(['deleted_at']);
            $table->dropSoftDeletesTz();
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->dropIndex(['deleted_at']);
            $table->dropSoftDeletesTz();
        });
    }
};
