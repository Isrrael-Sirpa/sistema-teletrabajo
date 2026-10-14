<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 125);
            $table->string('display_name');
            $table->string('guard_name', 25);
            $table->timestampsTz();

            $table->unique(['name', 'guard_name']);
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 125);
            $table->string('display_name');
            $table->text('description')->nullable();
            $table->string('guard_name', 25);
            $table->timestampsTz();

            $table->unique(['name', 'guard_name']);
        });

        Schema::create('model_has_permissions', function (Blueprint $table) {
            $table->foreignId('permission_id')
                ->constrained('permissions')
                ->cascadeOnDelete();
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');

            $table->index(['model_id', 'model_type'], 'model_permissions_model_index');
            $table->primary(['permission_id', 'model_id', 'model_type'], 'model_permissions_primary');
        });

        Schema::create('model_has_roles', function (Blueprint $table) {
            $table->foreignId('role_id')
                ->constrained('roles')
                ->cascadeOnDelete();
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');

            $table->index(['model_id', 'model_type'], 'model_roles_model_index');
            $table->primary(['role_id', 'model_id', 'model_type'], 'model_roles_primary');
        });

        Schema::create('role_has_permissions', function (Blueprint $table) {
            $table->foreignId('permission_id')
                ->constrained('permissions')
                ->cascadeOnDelete();
            $table->foreignId('role_id')
                ->constrained('roles')
                ->cascadeOnDelete();

            $table->primary(['permission_id', 'role_id'], 'role_permissions_primary');
            $table->index('role_id');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE roles ADD CONSTRAINT roles_name_format_check CHECK (name ~ '^[a-z][a-z0-9_]*$')");
            DB::statement("ALTER TABLE permissions ADD CONSTRAINT permissions_name_format_check CHECK (name ~ '^[a-z][a-z0-9_]*\\.[a-z][a-z0-9_]*$')");
        }

        app('cache')->store(config('permission.cache.store') !== 'default'
            ? config('permission.cache.store')
            : null)->forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        Schema::dropIfExists('role_has_permissions');
        Schema::dropIfExists('model_has_roles');
        Schema::dropIfExists('model_has_permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('permissions');
    }
};
