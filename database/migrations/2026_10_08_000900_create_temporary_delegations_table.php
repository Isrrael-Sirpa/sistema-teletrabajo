<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('temporary_delegations', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('delegate_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('organizational_unit_id')->constrained('organizational_units')->restrictOnDelete();
            $table->foreignId('authorized_by')->constrained('users')->restrictOnDelete();
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at');
            $table->text('reason');
            $table->string('status', 20)->default('scheduled');
            $table->timestampTz('revoked_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('revocation_reason')->nullable();
            $table->timestampsTz();

            $table->index(['delegate_user_id', 'starts_at', 'ends_at']);
            $table->index(['organizational_unit_id', 'starts_at', 'ends_at']);
            $table->index(['status', 'ends_at']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE temporary_delegations ADD CONSTRAINT delegations_status_check CHECK (status IN ('scheduled', 'active', 'revoked', 'expired'))");
            DB::statement('ALTER TABLE temporary_delegations ADD CONSTRAINT delegations_dates_check CHECK (ends_at > starts_at)');
            DB::statement('ALTER TABLE temporary_delegations ADD CONSTRAINT delegations_reason_check CHECK (LENGTH(BTRIM(reason)) > 0)');
            DB::statement("ALTER TABLE temporary_delegations ADD CONSTRAINT delegation_revocation_check CHECK ((status = 'revoked' AND revoked_at IS NOT NULL AND revoked_by IS NOT NULL AND LENGTH(BTRIM(revocation_reason)) > 0) OR (status <> 'revoked' AND revoked_at IS NULL AND revoked_by IS NULL AND revocation_reason IS NULL))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('temporary_delegations');
    }
};
