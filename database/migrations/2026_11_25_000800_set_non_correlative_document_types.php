<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('tipos_documentos')
            ->whereIn('code', ['correspondencia', 'documento_kardex', 'otros'])
            ->update(['uses_correlative' => false, 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('tipos_documentos')
            ->whereIn('code', ['correspondencia', 'documento_kardex', 'otros'])
            ->update(['uses_correlative' => true, 'updated_at' => now()]);
    }
};
