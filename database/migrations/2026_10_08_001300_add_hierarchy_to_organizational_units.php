<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('unidades_organizacionales', function (Blueprint $table) {
            $table->foreignId('parent_id')
                ->nullable()
                ->after('public_id')
                ->constrained('unidades_organizacionales')
                ->restrictOnDelete();

            $table->index('parent_id', 'units_parent_id_index');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement(<<<'SQL'
                ALTER TABLE unidades_organizacionales
                ADD CONSTRAINT units_parent_presence_check CHECK (
                    (type = 'faculty' AND parent_id IS NULL)
                    OR (type IN ('career', 'kardex') AND parent_id IS NOT NULL)
                )
                SQL);

            DB::unprepared(<<<'SQL'
                CREATE TRIGGER unidades_parent_type_trigger_ins
                BEFORE INSERT ON unidades_organizacionales
                FOR EACH ROW
                BEGIN
                    DECLARE parent_type VARCHAR(20);
                    IF NEW.type <> 'faculty' THEN
                        SELECT type INTO parent_type FROM unidades_organizacionales WHERE id = NEW.parent_id;
                        IF parent_type IS NULL OR parent_type <> 'faculty' THEN
                            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Las carreras y Kardex deben depender de una Facultad.';
                        END IF;
                    END IF;
                END;
                SQL);

            DB::unprepared(<<<'SQL'
                CREATE TRIGGER unidades_parent_type_trigger_upd
                BEFORE UPDATE ON unidades_organizacionales
                FOR EACH ROW
                BEGIN
                    DECLARE parent_type VARCHAR(20);
                    IF NEW.type <> 'faculty' THEN
                        SELECT type INTO parent_type FROM unidades_organizacionales WHERE id = NEW.parent_id;
                        IF parent_type IS NULL OR parent_type <> 'faculty' THEN
                            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Las carreras y Kardex deben depender de una Facultad.';
                        END IF;
                    END IF;
                END;
                SQL);
        } elseif (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<'SQL'
                ALTER TABLE unidades_organizacionales
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
                    FROM unidades_organizacionales
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
                ON unidades_organizacionales
                FOR EACH ROW
                EXECUTE FUNCTION validate_organizational_unit_parent();
                SQL);
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::unprepared(<<<'SQL'
                DROP TRIGGER IF EXISTS unidades_parent_type_trigger_ins;
                DROP TRIGGER IF EXISTS unidades_parent_type_trigger_upd;
                SQL);
        } elseif (DB::getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
                DROP TRIGGER IF EXISTS organizational_units_parent_type_trigger ON unidades_organizacionales;
                DROP FUNCTION IF EXISTS validate_organizational_unit_parent();
                SQL);
        }

        Schema::table('unidades_organizacionales', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropIndex('units_parent_id_index');
            $table->dropColumn('parent_id');
        });
    }
};
