<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $permissionsTable = config('permission.table_names.permissions', 'permisos');

        Schema::table('delegaciones_temporales', function (Blueprint $table): void {
            $table->foreignId('delegator_user_id')
                ->after('public_id')
                ->constrained('usuarios')
                ->restrictOnDelete();
            $table->index('delegator_user_id');
        });

        Schema::create('permisos_delegaciones_temporales', function (Blueprint $table) use ($permissionsTable): void {
            $table->foreignId('temporary_delegation_id')
                ->constrained('delegaciones_temporales')
                ->restrictOnDelete();
            $table->foreignId('permission_id')
                ->constrained($permissionsTable)
                ->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->primary(
                ['temporary_delegation_id', 'permission_id'],
                'permisos_delegaciones_temporales_primary',
            );
            $table->index('permission_id');
        });
    }

    public function down(): void
    {
        if (DB::table('delegaciones_temporales')->exists()
            || DB::table('permisos_delegaciones_temporales')->exists()) {
            throw new RuntimeException('No se puede revertir mientras existan delegaciones temporales o capacidades históricas.');
        }

        Schema::dropIfExists('permisos_delegaciones_temporales');

        Schema::table('delegaciones_temporales', function (Blueprint $table): void {
            $table->dropForeign(['delegator_user_id']);
            $table->dropIndex(['delegator_user_id']);
            $table->dropColumn('delegator_user_id');
        });
    }
};
