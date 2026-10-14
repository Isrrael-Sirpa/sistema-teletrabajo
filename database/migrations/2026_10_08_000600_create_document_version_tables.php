<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('documents')->restrictOnDelete();
            $table->unsignedInteger('version_number');
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->text('note')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->unique(['document_id', 'version_number']);
        });

        Schema::create('document_files', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('document_version_id')->constrained('document_versions')->restrictOnDelete();
            $table->string('disk', 60);
            $table->string('path', 1024);
            $table->string('original_name', 255);
            $table->string('stored_name', 255);
            $table->string('extension', 20);
            $table->string('detected_mime_type', 255);
            $table->unsignedBigInteger('size_bytes');
            $table->char('sha256', 64);
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->timestampTz('created_at')->useCurrent();

            $table->unique(['disk', 'path']);
            $table->index('sha256');
            $table->index(['document_version_id', 'created_at']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE document_versions ADD CONSTRAINT versions_number_check CHECK (version_number >= 1)');
            DB::statement('ALTER TABLE document_files ADD CONSTRAINT files_size_check CHECK (size_bytes > 0)');
            DB::statement("ALTER TABLE document_files ADD CONSTRAINT files_sha256_check CHECK (sha256 ~ '^[0-9a-f]{64}$')");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('document_files');
        Schema::dropIfExists('document_versions');
    }
};
