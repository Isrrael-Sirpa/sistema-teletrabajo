<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\DelegationState;
use App\Enums\DocumentConfidentiality;
use App\Enums\DocumentPriority;
use App\Enums\DocumentRecipientStatus;
use App\Enums\DocumentStatus;
use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\Document;
use App\Models\DocumentRecipient;
use App\Models\DocumentType;
use App\Models\OrganizationalUnit;
use App\Models\TemporaryDelegation;
use App\Models\User;
use App\Services\DelegatedAuthorizationService;
use App\Services\TemporaryDelegationService;
use Carbon\CarbonImmutable;
use Database\Seeders\AuthorizationSeeder;
use Database\Seeders\DocumentTypeSeeder;
use Database\Seeders\InstitutionalStructureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class TemporaryDelegationTest extends TestCase
{
    use RefreshDatabase;

    private OrganizationalUnit $career;

    private User $head;

    private User $secretary;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            AuthorizationSeeder::class,
            InstitutionalStructureSeeder::class,
            DocumentTypeSeeder::class,
        ]);

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-12 10:00:00', config('app.timezone')));
        $this->career = $this->career('Sistemas', 'SIS');
        $this->head = $this->user(RoleName::CAREER_HEAD, $this->career);
        $this->secretary = $this->user(RoleName::SECRETARY, $this->career);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_administrator_creates_audited_delegation_without_document_events(): void
    {
        $administrator = $this->user(RoleName::ADMINISTRATOR);
        $delegation = $this->createDelegation($administrator, permissions: [
            PermissionName::USERS_VIEW,
            PermissionName::DOCUMENTS_VIEW,
        ]);

        $this->assertSame($this->head->id, $delegation->delegator_user_id);
        $this->assertSame($this->secretary->id, $delegation->delegate_user_id);
        $this->assertSame($administrator->id, $delegation->authorized_by);
        $this->assertEqualsCanonicalizing(
            [PermissionName::USERS_VIEW->value, PermissionName::DOCUMENTS_VIEW->value],
            $delegation->permissions->pluck('name')->all(),
        );
        $this->assertDatabaseHas('registros_auditoria', [
            'action' => AuditAction::DELEGATION_CREATED->value,
            'actor_id' => $administrator->id,
            'auditable_id' => $delegation->id,
        ]);
        $this->assertDatabaseCount('eventos_documentos', 0);
    }

    public function test_co_administrator_and_titular_head_can_create_with_their_allowed_scope(): void
    {
        $coAdministrator = $this->user(RoleName::CO_ADMINISTRATOR);
        $coDelegation = $this->createDelegation($coAdministrator);
        $this->assertSame($coAdministrator->id, $coDelegation->authorized_by);

        $otherCareer = $this->career('Electrónica', 'ELT');
        $otherHead = $this->user(RoleName::CAREER_HEAD, $otherCareer);
        $otherSecretary = $this->user(RoleName::SECRETARY, $otherCareer);
        $headDelegation = $this->createDelegation(
            $otherHead,
            $otherCareer,
            $otherHead,
            $otherSecretary,
        );

        $this->assertSame($otherHead->id, $headDelegation->delegator_user_id);
        $this->assertSame($otherHead->id, $headDelegation->authorized_by);
    }

    public function test_secretary_cannot_self_assign_or_access_the_management_module(): void
    {
        $this->actingAs($this->secretary)->get(route('delegations.index'))->assertForbidden();
        $this->actingAs($this->secretary)->post(route('delegations.store'), $this->payload())->assertForbidden();

        $this->expectException(HttpException::class);
        $this->createDelegation($this->secretary);
    }

    public function test_delegate_must_be_an_active_secretary_from_the_same_career(): void
    {
        $administrator = $this->user(RoleName::ADMINISTRATOR);
        $otherCareer = $this->career('Mecánica', 'MEC');
        $invalidUsers = [
            $this->user(RoleName::SECRETARY, $this->career, UserStatus::INACTIVE),
            $this->user(RoleName::SECRETARY, $otherCareer),
            $this->user(RoleName::SECRETARY_ASSISTANT, $this->career),
            $this->user(RoleName::EXTRA, $this->career),
            $this->user(RoleName::CO_ADMINISTRATOR),
        ];

        foreach ($invalidUsers as $invalidUser) {
            $this->assertValidationError(
                fn () => $this->createDelegation($administrator, delegate: $invalidUser),
                'delegate_user_id',
            );
        }

        $this->assertDatabaseCount('delegaciones_temporales', 0);
    }

    public function test_delegator_must_be_the_active_titular_head_of_the_same_career(): void
    {
        $administrator = $this->user(RoleName::ADMINISTRATOR);
        $otherCareer = $this->career('Industrial', 'IND');
        $otherHead = $this->user(RoleName::CAREER_HEAD, $otherCareer);

        $this->assertValidationError(
            fn () => $this->createDelegation($administrator, head: $otherHead),
            'delegator_user_id',
        );

        $this->head->update(['status' => UserStatus::INACTIVE]);
        $this->assertValidationError(
            fn () => $this->createDelegation($administrator),
            'delegator_user_id',
        );
    }

    public function test_invalid_dates_and_excessive_periods_are_rejected(): void
    {
        $administrator = $this->user(RoleName::ADMINISTRATOR);
        $now = CarbonImmutable::now();

        $this->assertValidationError(
            fn () => $this->createDelegation($administrator, startsAt: $now, endsAt: $now),
            'ends_at',
        );
        $this->assertValidationError(
            fn () => $this->createDelegation($administrator, startsAt: $now->subDays(2), endsAt: $now->subDay()),
            'ends_at',
        );
        $this->assertValidationError(
            fn () => $this->createDelegation($administrator, startsAt: $now, endsAt: $now->addDays(367)),
            'ends_at',
        );
    }

    public function test_overlapping_intervals_are_rejected_but_adjacent_intervals_are_allowed(): void
    {
        $administrator = $this->user(RoleName::ADMINISTRATOR);
        $start = CarbonImmutable::now()->addDay();
        $boundary = $start->addDays(5);
        $this->createDelegation($administrator, startsAt: $start, endsAt: $boundary);

        $this->assertValidationError(
            fn () => $this->createDelegation(
                $administrator,
                startsAt: $start->addHour(),
                endsAt: $boundary->addDay(),
            ),
            'starts_at',
        );

        $adjacent = $this->createDelegation(
            $administrator,
            startsAt: $boundary,
            endsAt: $boundary->addDays(2),
        );
        $this->assertSame($boundary->timestamp, $adjacent->starts_at->timestamp);
        $this->assertDatabaseCount('delegaciones_temporales', 2);
    }

    public function test_effectiveness_uses_half_open_dates_and_ignores_stored_status(): void
    {
        $administrator = $this->user(RoleName::ADMINISTRATOR);
        $start = CarbonImmutable::now()->addHour();
        $end = $start->addHours(2);
        $delegation = $this->createDelegation(
            $administrator,
            startsAt: $start,
            endsAt: $end,
            permissions: [PermissionName::USERS_VIEW],
        );
        $authorization = app(DelegatedAuthorizationService::class);

        $this->assertSame(DelegationState::UPCOMING, $delegation->state());
        $this->assertFalse($authorization->can($this->secretary, PermissionName::USERS_VIEW, $this->career->id));

        CarbonImmutable::setTestNow($start);
        $delegation->update(['status' => 'expired']);
        $this->assertSame(DelegationState::ACTIVE, $delegation->fresh()->state());
        $this->assertTrue($authorization->can($this->secretary, PermissionName::USERS_VIEW, $this->career->id));

        CarbonImmutable::setTestNow($end);
        $this->assertSame(DelegationState::EXPIRED, $delegation->fresh()->state());
        $this->assertFalse($authorization->can($this->secretary, PermissionName::USERS_VIEW, $this->career->id));
    }

    public function test_revocation_is_immediate_audited_and_preserves_historical_capabilities(): void
    {
        $administrator = $this->user(RoleName::ADMINISTRATOR);
        $delegation = $this->createDelegation($administrator, permissions: [PermissionName::USERS_VIEW]);
        $permissionId = $delegation->permissions->sole()->id;

        $this->assertTrue(app(DelegatedAuthorizationService::class)
            ->can($this->secretary, PermissionName::USERS_VIEW, $this->career->id));

        app(TemporaryDelegationService::class)->revoke($administrator, $delegation, 'Retorno anticipado del titular.');

        $delegation->refresh();
        $this->assertSame(DelegationState::REVOKED, $delegation->state());
        $this->assertSame($administrator->id, $delegation->revoked_by);
        $this->assertFalse(app(DelegatedAuthorizationService::class)
            ->can($this->secretary, PermissionName::USERS_VIEW, $this->career->id));
        $this->assertDatabaseHas('permisos_delegaciones_temporales', [
            'temporary_delegation_id' => $delegation->id,
            'permission_id' => $permissionId,
        ]);
        $this->assertDatabaseHas('registros_auditoria', [
            'action' => AuditAction::DELEGATION_REVOKED->value,
            'actor_id' => $administrator->id,
            'auditable_id' => $delegation->id,
        ]);
    }

    public function test_delegation_never_changes_spatie_roles_or_permanent_permissions(): void
    {
        $administrator = $this->user(RoleName::ADMINISTRATOR);
        $rolesBefore = $this->secretary->getRoleNames()->sort()->values()->all();
        $permissionsBefore = $this->secretary->getAllPermissions()->pluck('name')->sort()->values()->all();
        $directBefore = DB::table('modelo_tiene_permisos')->where('model_type', User::class)
            ->where('model_id', $this->secretary->id)->count();
        $delegation = $this->createDelegation($administrator, permissions: [PermissionName::USERS_VIEW]);

        foreach (['active', 'revoked'] as $moment) {
            if ($moment === 'revoked') {
                app(TemporaryDelegationService::class)->revoke($administrator, $delegation, 'Fin de la suplencia.');
            }
            $this->secretary->refresh();
            $this->assertSame($rolesBefore, $this->secretary->getRoleNames()->sort()->values()->all());
            $this->assertSame($permissionsBefore, $this->secretary->getAllPermissions()->pluck('name')->sort()->values()->all());
            $this->assertSame($directBefore, DB::table('modelo_tiene_permisos')->where('model_type', User::class)
                ->where('model_id', $this->secretary->id)->count());
        }
    }

    public function test_delegated_user_management_is_limited_by_capability_and_career(): void
    {
        $administrator = $this->user(RoleName::ADMINISTRATOR);
        $ownExtra = $this->user(RoleName::EXTRA, $this->career);
        $otherCareer = $this->career('Civil', 'CIV');
        $otherExtra = $this->user(RoleName::EXTRA, $otherCareer);
        $this->createDelegation($administrator, permissions: [PermissionName::USERS_VIEW]);

        $this->actingAs($this->secretary)->get(route('users.index'))
            ->assertOk()->assertSee($ownExtra->email)->assertDontSee($otherExtra->email);
        $this->actingAs($this->secretary)->get(route('users.show', $ownExtra))->assertOk();
        $this->actingAs($this->secretary)->get(route('users.show', $otherExtra))->assertForbidden();
        $this->actingAs($this->secretary)->get(route('users.create'))->assertForbidden();
    }

    public function test_global_or_unlisted_permissions_are_rejected_in_the_transaction(): void
    {
        $administrator = $this->user(RoleName::ADMINISTRATOR);

        foreach ([PermissionName::AUDIT_VIEW->value, PermissionName::ROLES_MANAGE->value, 'unknown.permission'] as $permission) {
            $this->assertValidationError(
                fn () => $this->createDelegation($administrator, permissions: [$permission]),
                'permissions',
            );
        }

        $this->assertDatabaseCount('delegaciones_temporales', 0);
    }

    public function test_restricted_visibility_is_equivalent_only_inside_the_delegated_career(): void
    {
        $administrator = $this->user(RoleName::ADMINISTRATOR);
        $otherSecretary = $this->user(RoleName::SECRETARY, $this->career);
        $ownRestricted = $this->document($otherSecretary, $this->career, 'Documento restringido propio');
        $otherCareer = $this->career('Ambiental', 'AMB');
        $foreignCreator = $this->user(RoleName::SECRETARY, $otherCareer);
        $foreignRestricted = $this->document($foreignCreator, $otherCareer, 'Documento restringido ajeno');
        $this->createDelegation($administrator, permissions: [PermissionName::DOCUMENTS_VIEW]);

        $this->actingAs($this->secretary)->get(route('documents.show', $ownRestricted))
            ->assertOk()->assertSee($ownRestricted->subject);
        $this->actingAs($this->secretary)->get(route('documents.show', $foreignRestricted))->assertForbidden();
    }

    public function test_dashboard_shows_active_headship_and_removes_it_at_the_exclusive_end(): void
    {
        $administrator = $this->user(RoleName::ADMINISTRATOR);
        $end = CarbonImmutable::now()->addHours(2);
        $this->createDelegation(
            $administrator,
            endsAt: $end,
            permissions: [PermissionName::USERS_VIEW],
        );

        $this->actingAs($this->secretary)->get(route('dashboard'))
            ->assertOk()->assertSee('Jefatura temporal activa')->assertSee($this->career->name);

        CarbonImmutable::setTestNow($end);
        $this->actingAs($this->secretary)->get(route('dashboard'))
            ->assertOk()->assertDontSee('Jefatura temporal activa');
    }

    public function test_even_a_historical_delegation_cannot_receive_or_decide_for_a_unit(): void
    {
        $administrator = $this->user(RoleName::ADMINISTRATOR);
        $origin = $this->career('Arquitectura', 'ARQ');
        $creator = $this->user(RoleName::SECRETARY, $origin);
        $document = $this->document($creator, $origin, 'Documento para decisión', DocumentStatus::SENT);
        $recipient = DocumentRecipient::query()->create([
            'document_id' => $document->id,
            'unit_id' => $this->career->id,
            'requires_response' => true,
            'status' => DocumentRecipientStatus::SENT,
            'lock_version' => 1,
        ]);
        $delegation = $this->createDelegation($administrator, permissions: [PermissionName::DOCUMENTS_VIEW]);
        $delegation->permissions()->attach(Permission::query()->whereIn('name', [
            PermissionName::DOCUMENTS_RECEIVE->value,
            PermissionName::DOCUMENTS_REVIEW->value,
            PermissionName::DOCUMENTS_APPROVE->value,
        ])->pluck('id'));

        foreach ([RoleName::SECRETARY_ASSISTANT, RoleName::EXTRA] as $role) {
            $this->secretary->syncRoles([$role->value]);
            $this->assertFalse(app(DelegatedAuthorizationService::class)->can(
                $this->secretary, PermissionName::DOCUMENTS_RECEIVE, $this->career->id,
            ));
            $this->assertFalse(Gate::forUser($this->secretary)->allows('receiveRecipient', [$document, $recipient]));
        }
        $this->secretary->syncRoles([RoleName::SECRETARY->value]);

        $this->actingAs($this->secretary)->get(route('documents.show', $document))->assertOk();
        $this->transition($document, $recipient, 'receive')->assertForbidden();
        $recipient->update(['status' => DocumentRecipientStatus::IN_REVIEW]);
        $this->transition($document, $recipient, 'approve')->assertForbidden();
        $this->assertSame(DocumentRecipientStatus::IN_REVIEW, $recipient->fresh()->status);
    }

    public function test_revocation_blocks_the_next_delegated_request_without_new_login(): void
    {
        $administrator = $this->user(RoleName::ADMINISTRATOR);
        $delegation = $this->createDelegation($administrator, permissions: [PermissionName::USERS_VIEW]);

        $this->actingAs($this->secretary)->get(route('users.index'))->assertOk();
        app(TemporaryDelegationService::class)->revoke($administrator, $delegation, 'Revocación inmediata.');
        $this->actingAs($this->secretary)->get(route('users.index'))->assertForbidden();
    }

    public function test_delegation_stops_applying_if_delegate_changes_role_or_career(): void
    {
        $administrator = $this->user(RoleName::ADMINISTRATOR);
        $this->createDelegation($administrator, permissions: [PermissionName::USERS_VIEW]);
        $authorization = app(DelegatedAuthorizationService::class);

        $this->assertTrue($authorization->hasAny($this->secretary, PermissionName::USERS_VIEW));

        $otherCareer = $this->career('Geología', 'GEO');
        $this->secretary->update(['organizational_unit_id' => $otherCareer->id]);
        $this->assertFalse($authorization->hasAny($this->secretary->refresh(), PermissionName::USERS_VIEW));
        $this->assertCount(0, $authorization->activeFor($this->secretary));

        $this->secretary->update(['organizational_unit_id' => $this->career->id]);
        $this->secretary->syncRoles([RoleName::EXTRA->value]);
        $this->assertFalse($authorization->hasAny($this->secretary->refresh(), PermissionName::USERS_VIEW));
        $this->assertCount(0, $authorization->activeFor($this->secretary));
    }

    private function payload(
        ?OrganizationalUnit $career = null,
        ?User $head = null,
        ?User $delegate = null,
        ?CarbonImmutable $startsAt = null,
        ?CarbonImmutable $endsAt = null,
        array $permissions = [PermissionName::USERS_VIEW],
    ): array {
        $career ??= $this->career;
        $head ??= $this->head;
        $delegate ??= $this->secretary;
        $startsAt ??= CarbonImmutable::now()->subHour();
        $endsAt ??= CarbonImmutable::now()->addDay();

        return [
            'organizational_unit_id' => $career->id,
            'delegator_user_id' => $head->id,
            'delegate_user_id' => $delegate->id,
            'starts_at' => $startsAt->format('Y-m-d\TH:i'),
            'ends_at' => $endsAt->format('Y-m-d\TH:i'),
            'reason' => 'Ausencia temporal institucional.',
            'permissions' => array_map(
                fn (PermissionName|string $permission): string => $permission instanceof PermissionName ? $permission->value : $permission,
                $permissions,
            ),
        ];
    }

    private function createDelegation(
        User $actor,
        ?OrganizationalUnit $career = null,
        ?User $head = null,
        ?User $delegate = null,
        ?CarbonImmutable $startsAt = null,
        ?CarbonImmutable $endsAt = null,
        array $permissions = [PermissionName::USERS_VIEW],
    ): TemporaryDelegation {
        return app(TemporaryDelegationService::class)->create(
            $actor,
            $this->payload($career, $head, $delegate, $startsAt, $endsAt, $permissions),
        );
    }

    private function assertValidationError(callable $callback, string $field): void
    {
        try {
            $callback();
            $this->fail("Se esperaba un error de validación para {$field}.");
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
        }
    }

    private function career(string $name, string $acronym): OrganizationalUnit
    {
        $faculty = OrganizationalUnit::query()->where('type', 'faculty')->sole();

        return OrganizationalUnit::query()->create([
            'parent_id' => $faculty->id,
            'type' => 'career',
            'name' => $name.' '.Str::random(6),
            'acronym' => $acronym.Str::upper(Str::random(4)),
            'is_active' => true,
        ]);
    }

    private function user(
        RoleName $role,
        ?OrganizationalUnit $career = null,
        UserStatus $status = UserStatus::ACTIVE,
    ): User {
        $user = User::factory()->create([
            'organizational_unit_id' => $career?->id,
            'status' => $status,
        ]);
        $user->assignRole($role->value);

        return $user;
    }

    private function document(
        User $creator,
        OrganizationalUnit $origin,
        string $subject,
        DocumentStatus $status = DocumentStatus::DRAFT,
    ): Document {
        $official = $status !== DocumentStatus::DRAFT;

        return Document::query()->create([
            'document_type_id' => DocumentType::query()->where('is_active', true)->firstOrFail()->id,
            'origin_unit_id' => $origin->id,
            'created_by' => $creator->id,
            'sender_user_id' => $official ? $creator->id : null,
            'subject' => $subject,
            'general_status' => $status,
            'priority' => DocumentPriority::NORMAL,
            'confidentiality' => DocumentConfidentiality::RESTRICTED,
            'code' => $official ? 'FTEC-'.$origin->acronym.'-2026-'.Str::upper(Str::random(7)) : null,
            'sent_at' => $official ? now()->subHour() : null,
            'lock_version' => $official ? 1 : 0,
        ]);
    }

    private function transition(Document $document, DocumentRecipient $recipient, string $transition)
    {
        return $this->actingAs($this->secretary)->patch(
            route('documents.recipients.transition', [$document, $recipient, $transition]),
            [
                'document_lock_version' => $document->fresh()->lock_version,
                'recipient_lock_version' => $recipient->fresh()->lock_version,
            ],
        );
    }
}
