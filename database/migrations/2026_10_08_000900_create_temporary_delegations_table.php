<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delegaciones_temporales', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('delegate_user_id')->constrained('usuarios')->restrictOnDelete();
            $table->foreignId('organizational_unit_id')->constrained('unidades_organizacionales')->restrictOnDelete();
            $table->foreignId('authorized_by')->constrained('usuarios')->restrictOnDelete();
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at');
            $table->text('reason');
            $table->string('status', 20)->default('scheduled');
            $table->timestampTz('revoked_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('usuarios')->restrictOnDelete();
            $table->text('revocation_reason')->nullable();
            $table->timestampsTz();

            $table->index(['delegate_user_id', 'starts_at', 'ends_at'], 'deleg_temp_delegate_dates_idx');
            $table->index(['organizational_unit_id', 'starts_at', 'ends_at'], 'deleg_temp_unit_dates_idx');
            $table->index(['status', 'ends_at']);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE delegaciones_temporales ADD CONSTRAINT delegations_status_check CHECK (status IN ('scheduled', 'active', 'revoked', 'expired'))");
            DB::statement('ALTER TABLE delegaciones_temporales ADD CONSTRAINT delegations_dates_check CHECK (ends_at > starts_at)');
            DB::statement('ALTER TABLE delegaciones_temporales ADD CONSTRAINT delegations_reason_check CHECK (LENGTH(TRIM(reason)) > 0)');
            DB::statement("ALTER TABLE delegaciones_temporales ADD CONSTRAINT delegation_revocation_check CHECK ((status = 'revoked' AND revoked_at IS NOT NULL AND revoked_by IS NOT NULL AND LENGTH(TRIM(revocation_reason)) > 0) OR (status <> 'revoked' AND revoked_at IS NULL AND revoked_by IS NULL AND revocation_reason IS NULL))");
        } elseif (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE delegaciones_temporales ADD CONSTRAINT delegations_status_check CHECK (status IN ('scheduled', 'active', 'revoked', 'expired'))");
            DB::statement('ALTER TABLE delegaciones_temporales ADD CONSTRAINT delegations_dates_check CHECK (ends_at > starts_at)');
            DB::statement('ALTER TABLE delegaciones_temporales ADD CONSTRAINT delegations_reason_check CHECK (LENGTH(BTRIM(reason)) > 0)');
            DB::statement("ALTER TABLE delegaciones_temporales ADD CONSTRAINT delegation_revocation_check CHECK ((status = 'revoked' AND revoked_at IS NOT NULL AND revoked_by IS NOT NULL AND LENGTH(BTRIM(revocation_reason)) > 0) OR (status <> 'revoked' AND revoked_at IS NULL AND revoked_by IS NULL AND revocation_reason IS NULL))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('delegaciones_temporales');
    }
};
