<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('unidades_organizacionales')->where('type', 'dean_office')
            ->whereNull('correlative_prefix')->update(['correlative_prefix' => 'FT. DEC.', 'updated_at' => now()]);
    }

    public function down(): void {}
};
