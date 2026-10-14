<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('temporary_delegations', function (Blueprint $table): void {
            $table->foreignId('delegator_user_id')
                ->after('public_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->index('delegator_user_id');
        });

        Schema::create('temporary_delegation_permissions', function (Blueprint $table): void {
            $table->foreignId('temporary_delegation_id')
                ->constrained('temporary_delegations')
                ->restrictOnDelete();
            $table->foreignId('permission_id')
                ->constrained('permissions')
                ->restrictOnDelete();
            $table->timestampTz('created_at')->useCurrent();

            $table->primary(
                ['temporary_delegation_id', 'permission_id'],
                'temporary_delegation_permissions_primary',
            );
            $table->index('permission_id');
        });
    }

    public function down(): void
    {
        if (DB::table('temporary_delegations')->exists()
            || DB::table('temporary_delegation_permissions')->exists()) {
            throw new RuntimeException('No se puede revertir mientras existan delegaciones temporales o capacidades históricas.');
        }

        Schema::dropIfExists('temporary_delegation_permissions');

        Schema::table('temporary_delegations', function (Blueprint $table): void {
            $table->dropForeign(['delegator_user_id']);
            $table->dropIndex(['delegator_user_id']);
            $table->dropColumn('delegator_user_id');
        });
    }
};
