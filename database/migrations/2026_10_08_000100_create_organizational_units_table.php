<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizational_units', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('type', 20);
            $table->string('name', 160);
            $table->string('acronym', 20)->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->index(['type', 'is_active']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE organizational_units ADD CONSTRAINT units_type_check CHECK (type IN ('faculty', 'career', 'kardex'))");
            DB::statement("CREATE UNIQUE INDEX units_singleton_type_unique ON organizational_units (type) WHERE type IN ('faculty', 'kardex')");
            DB::statement('CREATE UNIQUE INDEX units_name_ci_unique ON organizational_units (LOWER(name))');
            DB::statement('CREATE UNIQUE INDEX units_acronym_ci_unique ON organizational_units (LOWER(acronym))');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('organizational_units');
    }
};
