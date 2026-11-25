<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usuarios', function (Blueprint $table): void {
            $table->string('academic_title', 12)->nullable()->after('name');
        });

        $departmentId = DB::table('unidades_organizacionales')->where('acronym', 'DCOMP')->value('id');
        $administratorRoleId = DB::table('roles')->where('name', 'administrator')->value('id');

        if ($departmentId && $administratorRoleId) {
            $administratorIds = DB::table('modelo_tiene_roles')
                ->where('role_id', $administratorRoleId)
                ->where('model_type', 'App\\Models\\User')
                ->pluck('model_id');
            DB::table('usuarios')->whereIn('id', $administratorIds)
                ->whereNull('organizational_unit_id')
                ->update(['organizational_unit_id' => $departmentId]);
        }
    }

    public function down(): void
    {
        Schema::table('usuarios', fn (Blueprint $table) => $table->dropColumn('academic_title'));
    }
};
