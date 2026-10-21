<?php

namespace Tests\PostgreSQL;

use App\Enums\DocumentConfidentiality;
use App\Enums\DocumentPriority;
use App\Enums\DocumentStatus;
use App\Enums\RoleName;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\OrganizationalUnit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PostgreSqlDashboardTest extends TestCase
{
    /** @var array<int, int> */
    private array $documentIds = [];

    /** @var array<int, int> */
    private array $userIds = [];

    /** @var array<int, int> */
    private array $unitIds = [];

    protected function tearDown(): void
    {
        DB::table('notifications')->where('notifiable_type', User::class)->whereIn('notifiable_id', $this->userIds)->delete();
        DB::table('document_access_grants')->whereIn('document_id', $this->documentIds)->delete();
        DB::table('document_events')->whereIn('document_id', $this->documentIds)->delete();
        DB::table('document_recipients')->whereIn('document_id', $this->documentIds)->delete();
        DB::table('documents')->whereIn('id', $this->documentIds)->delete();
        DB::table('model_has_roles')->where('model_type', User::class)->whereIn('model_id', $this->userIds)->delete();
        DB::table('model_has_permissions')->where('model_type', User::class)->whereIn('model_id', $this->userIds)->delete();
        DB::table('users')->whereIn('id', $this->userIds)->delete();
        DB::table('organizational_units')->whereIn('id', $this->unitIds)->delete();

        parent::tearDown();
    }

    public function test_postgresql_dashboard_aggregates_statuses_and_timestamptz_deadlines(): void
    {
        $this->assertSame('pgsql', DB::connection()->getDriverName());
        $admin = $this->user(RoleName::ADMINISTRATOR);
        $baseline = collect($this->actingAs($admin)->get(route('dashboard'))->viewData('cards'));
        $career = $this->career();
        $creator = $this->user(RoleName::SECRETARY, $career);
        $this->document($creator, $career, DocumentStatus::SENT, now()->subHour());
        $this->document($creator, $career, DocumentStatus::IN_REVIEW, now()->addDay());
        $this->document($creator, $career, DocumentStatus::APPROVED, now()->subDay());

        $response = $this->actingAs($admin)->get(route('dashboard'));
        $cards = collect($response->viewData('cards'));

        $response->assertOk();
        foreach (['Documentos enviados pendientes', 'Documentos en revisión', 'Vencidos', 'Próximos a vencer'] as $label) {
            $this->assertSame(
                (int) $baseline->firstWhere('label', $label)['count'] + 1,
                (int) $cards->firstWhere('label', $label)['count'],
            );
        }
    }

    private function career(): OrganizationalUnit
    {
        $faculty = OrganizationalUnit::query()->where('type', 'faculty')->firstOrFail();
        $unit = OrganizationalUnit::query()->create([
            'parent_id' => $faculty->id,
            'type' => 'career',
            'name' => 'Carrera Dashboard '.Str::random(8),
            'acronym' => Str::upper(Str::random(8)),
            'is_active' => true,
        ]);
        $this->unitIds[] = $unit->id;

        return $unit;
    }

    private function user(RoleName $role, ?OrganizationalUnit $unit = null): User
    {
        $user = User::factory()->create(['organizational_unit_id' => $unit?->id]);
        $user->assignRole($role->value);
        $this->userIds[] = $user->id;

        return $user;
    }

    private function document(User $creator, OrganizationalUnit $origin, DocumentStatus $status, $dueAt): Document
    {
        $document = Document::query()->create([
            'document_type_id' => DocumentType::query()->where('is_active', true)->firstOrFail()->id,
            'origin_unit_id' => $origin->id,
            'created_by' => $creator->id,
            'subject' => 'Dashboard PostgreSQL '.Str::random(8),
            'general_status' => DocumentStatus::DRAFT,
            'priority' => DocumentPriority::NORMAL,
            'confidentiality' => DocumentConfidentiality::NORMAL,
            'due_at' => $dueAt,
            'lock_version' => 0,
        ]);
        $this->documentIds[] = $document->id;
        $document->forceFill([
            'sender_user_id' => $creator->id,
            'general_status' => $status,
            'code' => 'FTEC-'.$origin->acronym.'-2026-'.Str::upper(Str::random(8)),
            'sent_at' => now()->subDay(),
            'lock_version' => 1,
        ])->save();

        return $document;
    }
}
