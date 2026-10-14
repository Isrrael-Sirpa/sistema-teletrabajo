<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->uuid('public_id')->unique()->after('id');
            $table->foreignId('organizational_unit_id')
                ->nullable()
                ->after('public_id')
                ->constrained('organizational_units')
                ->restrictOnDelete();
            $table->string('status', 20)->default('active')->after('password');
            $table->timestampTz('last_login_at')->nullable()->after('status');

            $table->index(['organizational_unit_id', 'status']);
        });

        Schema::table('sessions', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE users ADD CONSTRAINT users_status_check CHECK (status IN ('active', 'inactive'))");
            DB::statement('CREATE UNIQUE INDEX users_email_ci_unique ON users (LOWER(email))');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS users_email_ci_unique');
            DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_status_check');
        }

        Schema::table('sessions', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['organizational_unit_id']);
            $table->dropIndex(['organizational_unit_id', 'status']);
            $table->dropUnique(['public_id']);
            $table->dropColumn(['public_id', 'organizational_unit_id', 'status', 'last_login_at']);
        });
    }
};
