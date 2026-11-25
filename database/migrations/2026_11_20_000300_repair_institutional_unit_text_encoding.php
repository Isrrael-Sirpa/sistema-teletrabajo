<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            'DECANATO' => 'Decanato de la Facultad de Tecnología',
            'VICEDEC' => 'Vicedecanato de la Facultad de Tecnología',
            'DIR-ADM' => 'Dirección Administrativa',
            'DCOMP' => 'Departamento de Computación',
            'DMB' => 'Departamento de Materias Básicas',
            'IIAT' => 'Instituto de Investigaciones y Aplicaciones Tecnológicas',
        ] as $acronym => $name) {
            DB::table('unidades_organizacionales')->where('acronym', $acronym)->update([
                'name' => $name,
                'description' => 'Unidad institucional inicial de la Facultad de Tecnología.',
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void {}
};
