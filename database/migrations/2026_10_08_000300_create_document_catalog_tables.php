<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipos_documentos', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('name', 120);
            $table->string('code', 50)->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('usuarios')->restrictOnDelete();
            $table->timestampsTz();

            $table->index(['is_active', 'name']);
        });

        Schema::create('contadores_documentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('origin_unit_id')->constrained('unidades_organizacionales')->restrictOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedBigInteger('last_number')->default(0);
            $table->timestampsTz();

            $table->unique(['origin_unit_id', 'year']);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE contadores_documentos ADD CONSTRAINT contadores_documentos_year_check CHECK (year BETWEEN 2000 AND 9999)');
            DB::statement('ALTER TABLE contadores_documentos ADD CONSTRAINT contadores_documentos_number_check CHECK (last_number >= 0)');
        } elseif (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE UNIQUE INDEX document_types_name_ci_unique ON tipos_documentos (LOWER(name))');
            DB::statement('CREATE UNIQUE INDEX document_types_code_ci_unique ON tipos_documentos (LOWER(code))');
            DB::statement('ALTER TABLE contadores_documentos ADD CONSTRAINT document_counters_year_check CHECK (year BETWEEN 2000 AND 9999)');
            DB::statement('ALTER TABLE contadores_documentos ADD CONSTRAINT document_counters_number_check CHECK (last_number >= 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('contadores_documentos');
        Schema::dropIfExists('tipos_documentos');
    }
};
