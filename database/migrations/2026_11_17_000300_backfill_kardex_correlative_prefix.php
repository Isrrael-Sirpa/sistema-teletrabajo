<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('unidades_organizacionales')
            ->where('type', 'kardex')
            ->whereNull('correlative_prefix')
            ->update(['correlative_prefix' => 'FT KARDEX.']);
    }

    public function down(): void
    {
        DB::table('unidades_organizacionales')
            ->where('type', 'kardex')
            ->where('correlative_prefix', 'FT KARDEX.')
            ->update(['correlative_prefix' => null]);
    }
};
