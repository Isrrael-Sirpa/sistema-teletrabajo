<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $types = "'faculty', 'career', 'kardex', 'dean_office', 'vice_dean_office', 'admin_direction', 'department', 'library', 'institute', 'postgraduate_unit', 'program', 'other'";

        if (DB::getDriverName() === 'mysql') {
            DB::unprepared(<<<'SQL'
                DROP TRIGGER IF EXISTS unidades_parent_type_trigger_ins;
                DROP TRIGGER IF EXISTS unidades_parent_type_trigger_upd;
                SQL);
            DB::statement('ALTER TABLE unidades_organizacionales DROP CONSTRAINT unidades_tipo_check');
            DB::statement('ALTER TABLE unidades_organizacionales DROP CONSTRAINT units_parent_presence_check');
            DB::statement("ALTER TABLE unidades_organizacionales ADD CONSTRAINT unidades_tipo_check CHECK (type IN ({$types}))");
            DB::statement("ALTER TABLE unidades_organizacionales ADD CONSTRAINT units_parent_presence_check CHECK ((type = 'faculty' AND parent_id IS NULL) OR (type <> 'faculty' AND parent_id IS NOT NULL))");
            DB::unprepared(<<<'SQL'
                CREATE TRIGGER unidades_parent_type_trigger_ins
                BEFORE INSERT ON unidades_organizacionales
                FOR EACH ROW
                BEGIN
                    DECLARE parent_type VARCHAR(20);
                    IF NEW.type <> 'faculty' THEN
                        SELECT type INTO parent_type FROM unidades_organizacionales WHERE id = NEW.parent_id;
                        IF parent_type IS NULL THEN
                            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Toda unidad debe depender de una unidad institucional existente.';
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
                        IF parent_type IS NULL THEN
                            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Toda unidad debe depender de una unidad institucional existente.';
                        END IF;
                    END IF;
                END;
                SQL);
        } elseif (DB::getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
                DROP TRIGGER IF EXISTS organizational_units_parent_type_trigger ON unidades_organizacionales;
                DROP FUNCTION IF EXISTS validate_organizational_unit_parent();
                SQL);
            DB::statement('ALTER TABLE unidades_organizacionales DROP CONSTRAINT IF EXISTS units_type_check');
            DB::statement('ALTER TABLE unidades_organizacionales DROP CONSTRAINT IF EXISTS units_parent_presence_check');
            DB::statement("ALTER TABLE unidades_organizacionales ADD CONSTRAINT units_type_check CHECK (type IN ({$types}))");
            DB::statement("ALTER TABLE unidades_organizacionales ADD CONSTRAINT units_parent_presence_check CHECK ((type = 'faculty' AND parent_id IS NULL) OR (type <> 'faculty' AND parent_id IS NOT NULL))");
            DB::unprepared(<<<'SQL'
                CREATE OR REPLACE FUNCTION validate_organizational_unit_parent()
                RETURNS trigger AS $$
                BEGIN
                    IF NEW.type = 'faculty' THEN
                        RETURN NEW;
                    END IF;

                    IF NOT EXISTS (SELECT 1 FROM unidades_organizacionales WHERE id = NEW.parent_id) THEN
                        RAISE EXCEPTION 'Toda unidad debe depender de una unidad institucional existente.' USING ERRCODE = '23514';
                    END IF;

                    RETURN NEW;
                END;
                $$ LANGUAGE plpgsql;

                CREATE TRIGGER organizational_units_parent_type_trigger
                BEFORE INSERT OR UPDATE OF type, parent_id ON unidades_organizacionales
                FOR EACH ROW EXECUTE FUNCTION validate_organizational_unit_parent();
                SQL);
        }
    }

    public function down(): void
    {
        // La estructura ampliada contiene unidades que no pueden reducirse de forma segura.
    }
};
