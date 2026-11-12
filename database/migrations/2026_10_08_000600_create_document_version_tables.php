<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('versiones_documentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('documentos')->restrictOnDelete();
            $table->unsignedInteger('version_number');
            $table->foreignId('uploaded_by')->constrained('usuarios')->restrictOnDelete();
            $table->text('note')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->unique(['document_id', 'version_number']);
        });

        Schema::create('archivos_documentos', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('document_version_id')->constrained('versiones_documentos')->restrictOnDelete();
            $table->string('disk', 60);
            $table->string('path', 500);
            $table->string('original_name', 255);
            $table->string('stored_name', 255);
            $table->string('extension', 20);
            $table->string('detected_mime_type', 255);
            $table->unsignedBigInteger('size_bytes');
            $table->char('sha256', 64);
            $table->foreignId('uploaded_by')->constrained('usuarios')->restrictOnDelete();
            $table->timestampTz('created_at')->useCurrent();

            $table->unique(['disk', 'path']);
            $table->index('sha256');
            $table->index(['document_version_id', 'created_at']);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE versiones_documentos ADD CONSTRAINT versions_number_check CHECK (version_number >= 1)');
            DB::statement('ALTER TABLE archivos_documentos ADD CONSTRAINT files_size_check CHECK (size_bytes > 0)');
            DB::statement("ALTER TABLE archivos_documentos ADD CONSTRAINT files_sha256_check CHECK (sha256 REGEXP '^[0-9a-f]{64}$')");
        } elseif (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE versiones_documentos ADD CONSTRAINT versions_number_check CHECK (version_number >= 1)');
            DB::statement('ALTER TABLE archivos_documentos ADD CONSTRAINT files_size_check CHECK (size_bytes > 0)');
            DB::statement("ALTER TABLE archivos_documentos ADD CONSTRAINT files_sha256_check CHECK (sha256 ~ '^[0-9a-f]{64}$')");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('archivos_documentos');
        Schema::dropIfExists('versiones_documentos');
    }
};
