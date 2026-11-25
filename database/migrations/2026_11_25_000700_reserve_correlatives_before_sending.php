<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tipos_documentos', function (Blueprint $table) {
            $table->boolean('uses_correlative')->default(true)->after('correlative_label');
        });
        Schema::table('documentos', function (Blueprint $table) {
            $table->unsignedBigInteger('correlative_number')->nullable()->after('code');
            $table->unsignedSmallInteger('correlative_year')->nullable()->after('correlative_number');
            $table->foreignId('correlative_reserved_by')->nullable()->after('correlative_year')->constrained('usuarios')->restrictOnDelete();
            $table->timestampTz('correlative_reserved_at')->nullable()->after('correlative_reserved_by');
            $table->unique(['document_type_id', 'correlative_year', 'correlative_number'], 'documents_correlative_series_unique');
        });

        if (in_array(DB::getDriverName(), ['mysql', 'pgsql'], true)) {
            DB::statement('ALTER TABLE documentos DROP CONSTRAINT docs_sent_fields_check');
            DB::statement("ALTER TABLE documentos ADD CONSTRAINT docs_sent_fields_check CHECK ((general_status = 'draft' AND sent_at IS NULL) OR (general_status <> 'draft' AND sent_at IS NOT NULL))");
        }
    }

    public function down(): void
    {
        if (in_array(DB::getDriverName(), ['mysql', 'pgsql'], true)) {
            DB::statement('ALTER TABLE documentos DROP CONSTRAINT docs_sent_fields_check');
            DB::statement("ALTER TABLE documentos ADD CONSTRAINT docs_sent_fields_check CHECK ((general_status = 'draft' AND code IS NULL AND sent_at IS NULL) OR (general_status <> 'draft' AND code IS NOT NULL AND sent_at IS NOT NULL))");
        }
        Schema::table('documentos', function (Blueprint $table) {
            $table->dropUnique('documents_correlative_series_unique');
            $table->dropConstrainedForeignId('correlative_reserved_by');
            $table->dropColumn(['correlative_number', 'correlative_year', 'correlative_reserved_at']);
        });
        Schema::table('tipos_documentos', fn (Blueprint $table) => $table->dropColumn('uses_correlative'));
    }
};
