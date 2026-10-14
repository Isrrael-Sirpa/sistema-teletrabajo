<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE document_recipients DROP CONSTRAINT recipients_status_check');
            DB::statement('ALTER TABLE document_recipients DROP CONSTRAINT recipient_copy_status_check');
            DB::statement("ALTER TABLE document_recipients ALTER COLUMN status SET DEFAULT 'prepared'");
            DB::statement("ALTER TABLE document_recipients ADD CONSTRAINT recipients_status_check CHECK (status IN ('prepared', 'sent', 'received', 'in_review', 'approved', 'returned', 'rejected'))");
            DB::statement("ALTER TABLE document_recipients ADD CONSTRAINT recipient_copy_status_check CHECK (status = 'prepared' OR requires_response OR status IN ('sent', 'received'))");

            return;
        }

        Schema::table('document_recipients', function (Blueprint $table): void {
            $table->string('status', 20)->default('prepared')->change();
        });
    }

    public function down(): void
    {
        if (DB::table('document_recipients')->where('status', 'prepared')->exists()) {
            throw new RuntimeException('No se puede revertir mientras existan destinatarios preparados.');
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE document_recipients DROP CONSTRAINT recipients_status_check');
            DB::statement('ALTER TABLE document_recipients DROP CONSTRAINT recipient_copy_status_check');
            DB::statement("ALTER TABLE document_recipients ALTER COLUMN status SET DEFAULT 'sent'");
            DB::statement("ALTER TABLE document_recipients ADD CONSTRAINT recipients_status_check CHECK (status IN ('sent', 'received', 'in_review', 'approved', 'returned', 'rejected'))");
            DB::statement("ALTER TABLE document_recipients ADD CONSTRAINT recipient_copy_status_check CHECK (requires_response OR status IN ('sent', 'received'))");

            return;
        }

        Schema::table('document_recipients', function (Blueprint $table): void {
            $table->string('status', 20)->default('sent')->change();
        });
    }
};
