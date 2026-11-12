<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('observaciones_documentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('documentos')->restrictOnDelete();
            $table->foreignId('document_recipient_id')->nullable()->constrained('destinatarios_documentos')->restrictOnDelete();
            $table->foreignId('author_id')->constrained('usuarios')->restrictOnDelete();
            $table->string('visibility', 20);
            $table->text('body');
            $table->timestampsTz();

            $table->index(['document_id', 'created_at']);
            $table->index(['document_recipient_id', 'created_at']);
        });

        Schema::create('concesiones_acceso_documentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('documentos')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('usuarios')->restrictOnDelete();
            $table->foreignId('granted_by')->constrained('usuarios')->restrictOnDelete();
            $table->string('permission', 20);
            $table->timestampTz('starts_at')->nullable();
            $table->timestampTz('ends_at')->nullable();
            $table->text('reason');
            $table->timestampTz('revoked_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('usuarios')->restrictOnDelete();
            $table->text('revocation_reason')->nullable();
            $table->timestampsTz();

            $table->index(['user_id', 'starts_at', 'ends_at']);
            $table->index(['document_id', 'user_id']);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE observaciones_documentos ADD CONSTRAINT observations_visibility_check CHECK (visibility IN ('internal', 'participants'))");
            DB::statement('ALTER TABLE observaciones_documentos ADD CONSTRAINT observations_body_check CHECK (LENGTH(TRIM(body)) > 0)');
            DB::statement("ALTER TABLE concesiones_acceso_documentos ADD CONSTRAINT access_permission_check CHECK (permission IN ('view', 'download'))");
            DB::statement('ALTER TABLE concesiones_acceso_documentos ADD CONSTRAINT access_dates_check CHECK (starts_at IS NULL OR ends_at IS NULL OR ends_at > starts_at)');
            DB::statement('ALTER TABLE concesiones_acceso_documentos ADD CONSTRAINT access_revocation_check CHECK ((revoked_at IS NULL AND revoked_by IS NULL AND revocation_reason IS NULL) OR (revoked_at IS NOT NULL AND revoked_by IS NOT NULL AND LENGTH(TRIM(revocation_reason)) > 0))');
            DB::statement('ALTER TABLE concesiones_acceso_documentos ADD COLUMN is_active_grant TINYINT GENERATED ALWAYS AS (CASE WHEN revoked_at IS NULL THEN 1 ELSE NULL END) STORED');
            DB::statement('ALTER TABLE concesiones_acceso_documentos ADD CONSTRAINT active_access_grants_unique UNIQUE (document_id, user_id, permission, is_active_grant)');
        } elseif (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE observaciones_documentos ADD CONSTRAINT observations_visibility_check CHECK (visibility IN ('internal', 'participants'))");
            DB::statement('ALTER TABLE observaciones_documentos ADD CONSTRAINT observations_body_check CHECK (LENGTH(BTRIM(body)) > 0)');
            DB::statement("ALTER TABLE concesiones_acceso_documentos ADD CONSTRAINT access_permission_check CHECK (permission IN ('view', 'download'))");
            DB::statement('ALTER TABLE concesiones_acceso_documentos ADD CONSTRAINT access_dates_check CHECK (starts_at IS NULL OR ends_at IS NULL OR ends_at > starts_at)');
            DB::statement('ALTER TABLE concesiones_acceso_documentos ADD CONSTRAINT access_revocation_check CHECK ((revoked_at IS NULL AND revoked_by IS NULL AND revocation_reason IS NULL) OR (revoked_at IS NOT NULL AND revoked_by IS NOT NULL AND LENGTH(BTRIM(revocation_reason)) > 0))');
            DB::statement('CREATE UNIQUE INDEX active_access_grants_unique ON concesiones_acceso_documentos (document_id, user_id, permission) WHERE revoked_at IS NULL');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('concesiones_acceso_documentos');
        Schema::dropIfExists('observaciones_documentos');
    }
};
