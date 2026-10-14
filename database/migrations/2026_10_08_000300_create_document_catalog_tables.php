<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_types', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('name', 120);
            $table->string('code', 50)->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampsTz();

            $table->index(['is_active', 'name']);
        });

        Schema::create('document_counters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('origin_unit_id')->constrained('organizational_units')->restrictOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedBigInteger('last_number')->default(0);
            $table->timestampsTz();

            $table->unique(['origin_unit_id', 'year']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE UNIQUE INDEX document_types_name_ci_unique ON document_types (LOWER(name))');
            DB::statement('CREATE UNIQUE INDEX document_types_code_ci_unique ON document_types (LOWER(code))');
            DB::statement('ALTER TABLE document_counters ADD CONSTRAINT document_counters_year_check CHECK (year BETWEEN 2000 AND 9999)');
            DB::statement('ALTER TABLE document_counters ADD CONSTRAINT document_counters_number_check CHECK (last_number >= 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('document_counters');
        Schema::dropIfExists('document_types');
    }
};
