<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eventos_documentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('documentos')->restrictOnDelete();
            $table->foreignId('document_recipient_id')->nullable()->constrained('destinatarios_documentos')->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('usuarios')->restrictOnDelete();
            $table->string('event_type', 80);
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20)->nullable();
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestampTz('occurred_at')->useCurrent();

            $table->index(['document_id', 'occurred_at', 'id']);
            $table->index(['actor_id', 'occurred_at']);
            $table->index(['event_type', 'occurred_at']);
        });

        Schema::create('registros_auditoria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('usuarios')->restrictOnDelete();
            $table->string('action', 120);
            $table->string('auditable_type', 160)->nullable();
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->uuid('request_id');
            $table->timestampTz('occurred_at')->useCurrent();

            $table->index(['auditable_type', 'auditable_id']);
            $table->index(['actor_id', 'occurred_at']);
            $table->index('request_id');
            $table->index(['action', 'occurred_at']);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE eventos_documentos ADD CONSTRAINT event_status_pair_check CHECK ((from_status IS NULL AND to_status IS NULL) OR to_status IS NOT NULL)');
        } elseif (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE registros_auditoria ALTER COLUMN ip_address TYPE inet USING NULLIF(ip_address, \'\')::inet');
            DB::statement('ALTER TABLE eventos_documentos ADD CONSTRAINT event_status_pair_check CHECK ((from_status IS NULL AND to_status IS NULL) OR to_status IS NOT NULL)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('registros_auditoria');
        Schema::dropIfExists('eventos_documentos');
    }
};
