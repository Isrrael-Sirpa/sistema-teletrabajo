<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizational_units', function (Blueprint $table) {
            $table->foreignId('parent_id')
                ->nullable()
                ->after('public_id')
                ->constrained('organizational_units')
                ->restrictOnDelete();

            $table->index('parent_id', 'units_parent_id_index');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<'SQL'
                ALTER TABLE organizational_units
                ADD CONSTRAINT units_parent_presence_check CHECK (
                    (type = 'faculty' AND parent_id IS NULL)
                    OR (type IN ('career', 'kardex') AND parent_id IS NOT NULL)
                )
                SQL);

            DB::unprepared(<<<'SQL'
                CREATE OR REPLACE FUNCTION validate_organizational_unit_parent()
                RETURNS trigger AS $$
                DECLARE
                    parent_type varchar(20);
                BEGIN
                    IF NEW.type = 'faculty' THEN
                        RETURN NEW;
                    END IF;

                    SELECT type INTO parent_type
                    FROM organizational_units
                    WHERE id = NEW.parent_id;

                    IF parent_type IS DISTINCT FROM 'faculty' THEN
                        RAISE EXCEPTION 'Las carreras y Kardex deben depender de una Facultad.'
                            USING ERRCODE = '23514';
                    END IF;

                    RETURN NEW;
                END;
                $$ LANGUAGE plpgsql;

                CREATE TRIGGER organizational_units_parent_type_trigger
                BEFORE INSERT OR UPDATE OF type, parent_id
                ON organizational_units
                FOR EACH ROW
                EXECUTE FUNCTION validate_organizational_unit_parent();
                SQL);
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
                DROP TRIGGER IF EXISTS organizational_units_parent_type_trigger ON organizational_units;
                DROP FUNCTION IF EXISTS validate_organizational_unit_parent();
                SQL);
        }

        Schema::table('organizational_units', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropIndex('units_parent_id_index');
            $table->dropColumn('parent_id');
        });
    }
};
