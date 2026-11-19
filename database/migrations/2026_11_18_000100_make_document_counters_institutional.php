<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasIndex('contadores_documentos', 'series_correlativas_unicas')) {
            Schema::table('contadores_documentos', fn (Blueprint $table) => $table->dropUnique('series_correlativas_unicas'));
        }
        $series = DB::table('contadores_documentos')->whereNotNull('document_type_id')
            ->selectRaw('document_type_id, year, SUM(last_number) AS last_number')
            ->groupBy('document_type_id', 'year')->get();
        DB::table('contadores_documentos')->delete();
        Schema::table('contadores_documentos', function (Blueprint $table): void {
            $table->dropForeign(['origin_unit_id']);
            $table->unsignedBigInteger('origin_unit_id')->nullable()->change();
            $table->foreign('origin_unit_id')->references('id')->on('unidades_organizacionales')->restrictOnDelete();
            $table->unique(['document_type_id', 'year'], 'series_correlativas_institucionales');
        });
        foreach ($series as $item) {
            DB::table('contadores_documentos')->insert([
                'origin_unit_id' => null, 'document_type_id' => $item->document_type_id,
                'year' => $item->year, 'last_number' => $item->last_number,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('contadores_documentos', function (Blueprint $table): void {
            $table->dropUnique('series_correlativas_institucionales');
            $table->dropForeign(['origin_unit_id']);
        });
        DB::table('contadores_documentos')->whereNull('origin_unit_id')->delete();
        Schema::table('contadores_documentos', function (Blueprint $table): void {
            $table->unsignedBigInteger('origin_unit_id')->nullable(false)->change();
            $table->foreign('origin_unit_id')->references('id')->on('unidades_organizacionales')->restrictOnDelete();
            $table->unique(['origin_unit_id', 'document_type_id', 'year'], 'series_correlativas_unicas');
        });
    }
};
