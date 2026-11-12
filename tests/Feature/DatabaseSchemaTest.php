<?php

namespace Tests\Feature;

use App\Models\OrganizationalUnit;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class DatabaseSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_approved_phase_two_tables_exist(): void
    {
        $tables = [
            'unidades_organizacionales',
            'usuarios',
            'tipos_documentos',
            'contadores_documentos',
            'documentos',
            'destinatarios_documentos',
            'versiones_documentos',
            'archivos_documentos',
            'observaciones_documentos',
            'concesiones_acceso_documentos',
            'eventos_documentos',
            'registros_auditoria',
            'delegaciones_temporales',
            'notificaciones',
        ];

        foreach ($tables as $table) {
            $this->assertTrue(Schema::hasTable($table), "Missing table: {$table}");
        }
    }

    public function test_document_recipients_and_files_have_the_required_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('destinatarios_documentos', [
            'document_id',
            'unit_id',
            'user_id',
            'requires_response',
            'status',
            'received_by',
            'received_at',
            'lock_version',
            'deleted_at',
        ]));

        $this->assertTrue(Schema::hasColumn('documentos', 'deleted_at'));

        $this->assertTrue(Schema::hasColumns('archivos_documentos', [
            'document_version_id',
            'disk',
            'path',
            'original_name',
            'detected_mime_type',
            'size_bytes',
            'sha256',
        ]));

        $this->assertTrue(Schema::hasColumns('eventos_documentos', [
            'document_id',
            'document_recipient_id',
            'actor_id',
            'event_type',
            'from_status',
            'to_status',
            'reason',
            'metadata',
            'occurred_at',
        ]));
    }

    public function test_users_receive_a_public_uuid_and_belong_to_an_organizational_unit(): void
    {
        $unit = OrganizationalUnit::query()->create([
            'type' => 'career',
            'name' => 'Carrera de prueba',
            'acronym' => 'CPR',
            'is_active' => true,
        ]);

        $user = User::factory()->for($unit, 'organizationalUnit')->create();

        $this->assertTrue(Str::isUuid($unit->public_id));
        $this->assertTrue(Str::isUuid($user->public_id));
        $this->assertTrue($user->organizationalUnit->is($unit));
        $this->assertSame('public_id', $user->getRouteKeyName());
    }

    public function test_an_organizational_unit_with_users_cannot_be_deleted(): void
    {
        $unit = OrganizationalUnit::query()->create([
            'type' => 'career',
            'name' => 'Carrera protegida',
            'acronym' => 'CPT',
            'is_active' => true,
        ]);

        User::factory()->for($unit, 'organizationalUnit')->create();

        $this->expectException(QueryException::class);

        DB::table('unidades_organizacionales')->where('id', $unit->id)->delete();
    }
}
