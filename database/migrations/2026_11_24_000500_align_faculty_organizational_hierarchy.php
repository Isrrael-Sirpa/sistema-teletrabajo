<?php

use App\Enums\OrganizationalUnitType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $facultyId = DB::table('unidades_organizacionales')
            ->where('type', OrganizationalUnitType::FACULTY->value)
            ->value('id');

        if (! $facultyId) {
            return;
        }

        $deanId = DB::table('unidades_organizacionales')
            ->where('type', OrganizationalUnitType::DEAN_OFFICE->value)
            ->value('id');

        if ($deanId) {
            DB::table('unidades_organizacionales')->where('id', $deanId)->update(['parent_id' => $facultyId]);
        }

        $viceDeanId = DB::table('unidades_organizacionales')
            ->where('type', OrganizationalUnitType::VICE_DEAN_OFFICE->value)
            ->value('id');

        if (! $viceDeanId) {
            return;
        }

        DB::table('unidades_organizacionales')->where('id', $viceDeanId)->update([
            'parent_id' => $deanId ?: $facultyId,
        ]);

        DB::table('unidades_organizacionales')
            ->where('type', OrganizationalUnitType::CAREER->value)
            ->update(['parent_id' => $viceDeanId]);

        DB::table('unidades_organizacionales')
            ->whereIn('acronym', ['KARDEX', 'DCOMP', 'DMB', 'BIB-FT', 'IIAT', 'POSTGRADO', 'PREUNIV'])
            ->update(['parent_id' => $viceDeanId]);

        DB::table('unidades_organizacionales')
            ->where('type', OrganizationalUnitType::ADMIN_DIRECTION->value)
            ->update(['parent_id' => $deanId ?: $facultyId]);
    }

    public function down(): void
    {
        // La jerarquía anterior era plana y no debe restaurarse automáticamente.
    }
};
