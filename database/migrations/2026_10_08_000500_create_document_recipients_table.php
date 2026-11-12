<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('destinatarios_documentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('documentos')->restrictOnDelete();
            $table->foreignId('unit_id')->constrained('unidades_organizacionales')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('usuarios')->restrictOnDelete();
            $table->boolean('requires_response')->default(true);
            $table->string('status', 20)->default('sent');
            $table->foreignId('received_by')->nullable()->constrained('usuarios')->restrictOnDelete();
            $table->timestampTz('received_at')->nullable();
            $table->foreignId('current_handler_id')->nullable()->constrained('usuarios')->restrictOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('usuarios')->restrictOnDelete();
            $table->timestampTz('decided_at')->nullable();
            $table->text('decision_reason')->nullable();
            $table->unsignedInteger('lock_version')->default(0);
            $table->timestampsTz();

            $table->index(['document_id', 'status']);
            $table->index(['unit_id', 'status']);
            $table->index(['user_id', 'status']);
            $table->index(['current_handler_id', 'status']);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE destinatarios_documentos ADD COLUMN target_user_id BIGINT UNSIGNED GENERATED ALWAYS AS (COALESCE(user_id, 0)) STORED');
            DB::statement('ALTER TABLE destinatarios_documentos ADD CONSTRAINT destinatarios_target_unique UNIQUE (document_id, unit_id, target_user_id)');
            DB::statement("ALTER TABLE destinatarios_documentos ADD CONSTRAINT recipients_status_check CHECK (status IN ('prepared', 'sent', 'received', 'in_review', 'approved', 'returned', 'rejected'))");
            DB::statement("ALTER TABLE destinatarios_documentos ADD CONSTRAINT recipient_copy_status_check CHECK (status = 'prepared' OR requires_response OR status IN ('sent', 'received'))");
            DB::statement('ALTER TABLE destinatarios_documentos ADD CONSTRAINT recipient_receive_check CHECK ((received_by IS NULL AND received_at IS NULL) OR (received_by IS NOT NULL AND received_at IS NOT NULL))');
            DB::statement("ALTER TABLE destinatarios_documentos ADD CONSTRAINT recipient_decision_check CHECK ((status IN ('approved', 'returned', 'rejected') AND decided_by IS NOT NULL AND decided_at IS NOT NULL) OR (status NOT IN ('approved', 'returned', 'rejected') AND decided_by IS NULL AND decided_at IS NULL AND decision_reason IS NULL))");
            DB::statement("ALTER TABLE destinatarios_documentos ADD CONSTRAINT recipient_reason_check CHECK (status NOT IN ('returned', 'rejected') OR LENGTH(TRIM(decision_reason)) > 0)");
            DB::statement('ALTER TABLE destinatarios_documentos ADD CONSTRAINT recipient_lock_check CHECK (lock_version >= 0)');
        } elseif (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE destinatarios_documentos ADD CONSTRAINT recipients_target_unique UNIQUE NULLS NOT DISTINCT (document_id, unit_id, user_id)');
            DB::statement("ALTER TABLE destinatarios_documentos ADD CONSTRAINT recipients_status_check CHECK (status IN ('sent', 'received', 'in_review', 'approved', 'returned', 'rejected'))");
            DB::statement("ALTER TABLE destinatarios_documentos ADD CONSTRAINT recipient_copy_status_check CHECK (requires_response OR status IN ('sent', 'received'))");
            DB::statement('ALTER TABLE destinatarios_documentos ADD CONSTRAINT recipient_receive_check CHECK ((received_by IS NULL AND received_at IS NULL) OR (received_by IS NOT NULL AND received_at IS NOT NULL))');
            DB::statement("ALTER TABLE destinatarios_documentos ADD CONSTRAINT recipient_decision_check CHECK ((status IN ('approved', 'returned', 'rejected') AND decided_by IS NOT NULL AND decided_at IS NOT NULL) OR (status NOT IN ('approved', 'returned', 'rejected') AND decided_by IS NULL AND decided_at IS NULL AND decision_reason IS NULL))");
            DB::statement("ALTER TABLE destinatarios_documentos ADD CONSTRAINT recipient_reason_check CHECK (status NOT IN ('returned', 'rejected') OR LENGTH(BTRIM(decision_reason)) > 0)");
            DB::statement('ALTER TABLE destinatarios_documentos ADD CONSTRAINT recipient_lock_check CHECK (lock_version >= 0)');
        } else {
            Schema::table('destinatarios_documentos', function (Blueprint $table) {
                $table->unique(['document_id', 'unit_id', 'user_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('destinatarios_documentos');
    }
};
