<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('unidades_organizacionales', 'correlative_prefix')) {
            Schema::table('unidades_organizacionales', function (Blueprint $table): void {
                $table->string('correlative_prefix', 60)->nullable()->after('acronym');
            });
        }

        if (! Schema::hasColumn('tipos_documentos', 'correlative_label')) {
            Schema::table('tipos_documentos', function (Blueprint $table): void {
                $table->string('correlative_label', 30)->nullable()->after('code');
            });
        }

        if (! Schema::hasColumn('contadores_documentos', 'document_type_id')) {
            Schema::table('contadores_documentos', function (Blueprint $table): void {
                $table->foreignId('document_type_id')->nullable()->after('origin_unit_id')
                    ->constrained('tipos_documentos')->restrictOnDelete();
            });
        }

        if (! Schema::hasIndex('contadores_documentos', 'contadores_documentos_origen_idx')) {
            Schema::table('contadores_documentos', function (Blueprint $table): void {
                $table->index('origin_unit_id', 'contadores_documentos_origen_idx');
            });
        }

        if (Schema::hasIndex('contadores_documentos', 'contadores_documentos_origin_unit_id_year_unique')) {
            Schema::table('contadores_documentos', function (Blueprint $table): void {
                $table->dropUnique(['origin_unit_id', 'year']);
            });
        }

        if (! Schema::hasIndex('contadores_documentos', 'series_correlativas_unicas')) {
            Schema::table('contadores_documentos', function (Blueprint $table): void {
                $table->unique(['origin_unit_id', 'document_type_id', 'year'], 'series_correlativas_unicas');
            });
        }

        foreach ([
            'aeronautica' => 'AER',
            'construcciones-civiles' => 'CIV',
            'electricidad-industrial' => 'ELT',
            'electronica-telecomunicaciones' => 'ETN',
            'electromecanica' => 'ELM',
            'mecanica-automotriz' => 'MAZ',
            'mecanica-industrial' => 'MIN',
            'quimica-industrial' => 'QMC',
            'geodesia-topografia-geomatica' => 'GTG',
        ] as $key => $acronym) {
            $career = config("institutional_careers.{$key}.name");
            DB::table('unidades_organizacionales')
                ->where('name', $career)
                ->whereNull('correlative_prefix')
                ->update(['correlative_prefix' => "FT C.{$acronym}."]);
        }

        foreach ([
            'carta' => 'CARTA',
            'informe' => 'INF.',
            'memorandum' => 'MEM.',
            'circular' => 'CIRC.',
            'solicitud' => 'SOL.',
            'resolucion' => 'RES.',
            'nota' => 'NOTA',
            'formulario' => 'FORM.',
            'certificacion' => 'CERT.',
            'correspondencia' => 'CORR.',
            'documento_kardex' => 'KARDEX',
            'otros' => 'OTROS',
        ] as $code => $label) {
            DB::table('tipos_documentos')->where('code', $code)->whereNull('correlative_label')
                ->update(['correlative_label' => $label]);
        }
    }

    public function down(): void
    {
        Schema::table('contadores_documentos', function (Blueprint $table): void {
            $table->dropUnique('series_correlativas_unicas');
            $table->dropConstrainedForeignId('document_type_id');
            $table->unique(['origin_unit_id', 'year']);
        });

        Schema::table('tipos_documentos', function (Blueprint $table): void {
            $table->dropColumn('correlative_label');
        });

        Schema::table('unidades_organizacionales', function (Blueprint $table): void {
            $table->dropColumn('correlative_prefix');
        });
    }
};
