<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_observations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('documents')->restrictOnDelete();
            $table->foreignId('document_recipient_id')->nullable()->constrained('document_recipients')->restrictOnDelete();
            $table->foreignId('author_id')->constrained('users')->restrictOnDelete();
            $table->string('visibility', 20);
            $table->text('body');
            $table->timestampsTz();

            $table->index(['document_id', 'created_at']);
            $table->index(['document_recipient_id', 'created_at']);
        });

        Schema::create('document_access_grants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('documents')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('granted_by')->constrained('users')->restrictOnDelete();
            $table->string('permission', 20);
            $table->timestampTz('starts_at')->nullable();
            $table->timestampTz('ends_at')->nullable();
            $table->text('reason');
            $table->timestampTz('revoked_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('revocation_reason')->nullable();
            $table->timestampsTz();

            $table->index(['user_id', 'starts_at', 'ends_at']);
            $table->index(['document_id', 'user_id']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE document_observations ADD CONSTRAINT observations_visibility_check CHECK (visibility IN ('internal', 'participants'))");
            DB::statement('ALTER TABLE document_observations ADD CONSTRAINT observations_body_check CHECK (LENGTH(BTRIM(body)) > 0)');
            DB::statement("ALTER TABLE document_access_grants ADD CONSTRAINT access_permission_check CHECK (permission IN ('view', 'download'))");
            DB::statement('ALTER TABLE document_access_grants ADD CONSTRAINT access_dates_check CHECK (starts_at IS NULL OR ends_at IS NULL OR ends_at > starts_at)');
            DB::statement('ALTER TABLE document_access_grants ADD CONSTRAINT access_revocation_check CHECK ((revoked_at IS NULL AND revoked_by IS NULL AND revocation_reason IS NULL) OR (revoked_at IS NOT NULL AND revoked_by IS NOT NULL AND LENGTH(BTRIM(revocation_reason)) > 0))');
            DB::statement('CREATE UNIQUE INDEX active_access_grants_unique ON document_access_grants (document_id, user_id, permission) WHERE revoked_at IS NULL');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('document_access_grants');
        Schema::dropIfExists('document_observations');
    }
};
