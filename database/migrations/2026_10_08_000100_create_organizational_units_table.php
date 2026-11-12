<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unidades_organizacionales', function (Blueprint $table) {
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

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE unidades_organizacionales ADD CONSTRAINT unidades_tipo_check CHECK (type IN ('faculty', 'career', 'kardex'))");
            DB::statement("ALTER TABLE unidades_organizacionales ADD COLUMN singleton_type VARCHAR(20) GENERATED ALWAYS AS (CASE WHEN type IN ('faculty', 'kardex') THEN type ELSE NULL END) STORED");
            DB::statement("ALTER TABLE unidades_organizacionales ADD CONSTRAINT unidades_singleton_tipo_unique UNIQUE (singleton_type)");
        } elseif (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE unidades_organizacionales ADD CONSTRAINT units_type_check CHECK (type IN ('faculty', 'career', 'kardex'))");
            DB::statement("CREATE UNIQUE INDEX units_singleton_type_unique ON unidades_organizacionales (type) WHERE type IN ('faculty', 'kardex')");
            DB::statement('CREATE UNIQUE INDEX units_name_ci_unique ON unidades_organizacionales (LOWER(name))');
            DB::statement('CREATE UNIQUE INDEX units_acronym_ci_unique ON unidades_organizacionales (LOWER(acronym))');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('unidades_organizacionales');
    }
};
