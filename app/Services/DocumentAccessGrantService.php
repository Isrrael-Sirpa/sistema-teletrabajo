<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\DocumentAccessPermission;
use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\Document;
use App\Models\DocumentAccessGrant;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class DocumentAccessGrantService
{
    public function __construct(private readonly AuditLogService $audit) {}

    /** @return Collection<int, User> */
    public function eligibleExtras(User $actor): Collection
    {
        return User::query()
            ->where('status', UserStatus::ACTIVE->value)
            ->role(RoleName::EXTRA->value)
            ->whereHas('organizationalUnit', fn (Builder $unit) => $unit->where('is_active', true))
            ->when(! $actor->hasInstitutionalGlobalScope(), fn (Builder $query) => $query
                ->where('organizational_unit_id', $actor->organizational_unit_id))
            ->with(['organizationalUnit', 'roles'])
            ->orderBy('name')
            ->get();
    }

    /** @param array{user_id: int, permission: string, ends_at?: string|null} $data */
    public function grant(User $actor, Document $document, array $data): DocumentAccessGrant
    {
        Gate::forUser($actor)->authorize('grantAccess', $document);

        try {
            return DB::transaction(function () use ($actor, $document, $data): DocumentAccessGrant {
                $lockedDocument = $this->lockDocument($document);
                Gate::forUser($actor)->authorize('grantAccess', $lockedDocument);
                $beneficiary = $this->eligibleBeneficiary($actor, (int) $data['user_id']);

                $this->closeExpiredCycles($actor, $lockedDocument, $beneficiary);

                if (DocumentAccessGrant::query()
                    ->where('document_id', $lockedDocument->getKey())
                    ->where('user_id', $beneficiary->getKey())
                    ->whereNull('revoked_at')
                    ->exists()) {
                    throw ValidationException::withMessages([
                        'user_id' => 'Este usuario ya tiene una concesión activa o programada para el documento.',
                    ]);
                }

                $grant = DocumentAccessGrant::query()->create([
                    'document_id' => $lockedDocument->getKey(),
                    'user_id' => $beneficiary->getKey(),
                    'granted_by' => $actor->getKey(),
                    'permission' => DocumentAccessPermission::from($data['permission']),
                    'starts_at' => now(),
                    'ends_at' => $data['ends_at'] ?? null,
                    'reason' => 'Acceso explícito concedido desde el detalle documental.',
                ]);

                $this->audit->record($actor, AuditAction::ACCESS_GRANT_CREATED, $grant, newValues: [
                    'document_id' => $lockedDocument->getKey(),
                    'document_public_id' => $lockedDocument->public_id,
                    'beneficiary_user_id' => $beneficiary->getKey(),
                    'permission' => $grant->permission,
                    'starts_at' => $grant->starts_at,
                    'ends_at' => $grant->ends_at,
                    'confidentiality' => $lockedDocument->confidentiality,
                ]);

                return $grant;
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'user_id' => 'Este usuario ya tiene una concesión activa para el documento.',
            ]);
        }
    }

    public function changeLevel(
        User $actor,
        Document $document,
        DocumentAccessGrant $grant,
        DocumentAccessPermission $permission,
    ): DocumentAccessGrant {
        Gate::forUser($actor)->authorize('updateAccessGrant', [$document, $grant]);

        try {
            return DB::transaction(function () use ($actor, $document, $grant, $permission): DocumentAccessGrant {
                $lockedDocument = $this->lockDocument($document);
                $lockedGrant = DocumentAccessGrant::query()->lockForUpdate()->findOrFail($grant->getKey());
                Gate::forUser($actor)->authorize('updateAccessGrant', [$lockedDocument, $lockedGrant]);
                $this->assertEffective($lockedGrant);

                if ($lockedGrant->permission === $permission) {
                    throw ValidationException::withMessages([
                        'permission' => 'La concesión ya tiene el nivel seleccionado.',
                    ]);
                }

                $lockedGrant->forceFill([
                    'revoked_at' => now(),
                    'revoked_by' => $actor->getKey(),
                    'revocation_reason' => 'Cambio de nivel de acceso de '.$lockedGrant->permission->label().' a '.$permission->label().'.',
                ])->save();

                $replacement = DocumentAccessGrant::query()->create([
                    'document_id' => $lockedDocument->getKey(),
                    'user_id' => $lockedGrant->user_id,
                    'granted_by' => $actor->getKey(),
                    'permission' => $permission,
                    'starts_at' => now(),
                    'ends_at' => $lockedGrant->ends_at,
                    'reason' => 'Nuevo ciclo por cambio explícito de nivel de acceso.',
                ]);

                $this->audit->record(
                    $actor,
                    AuditAction::ACCESS_GRANT_LEVEL_CHANGED,
                    $lockedGrant,
                    ['permission' => $lockedGrant->permission, 'revoked_at' => null],
                    [
                        'permission' => $replacement->permission,
                        'replacement_grant_id' => $replacement->getKey(),
                        'document_id' => $lockedDocument->getKey(),
                        'document_public_id' => $lockedDocument->public_id,
                        'beneficiary_user_id' => $lockedGrant->user_id,
                        'confidentiality' => $lockedDocument->confidentiality,
                    ],
                );

                return $replacement;
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'permission' => 'Ya existe una concesión activa con el nivel solicitado.',
            ]);
        }
    }

    public function revoke(User $actor, Document $document, DocumentAccessGrant $grant, string $reason): void
    {
        Gate::forUser($actor)->authorize('revokeAccessGrant', [$document, $grant]);

        DB::transaction(function () use ($actor, $document, $grant, $reason): void {
            $lockedDocument = $this->lockDocument($document);
            $lockedGrant = DocumentAccessGrant::query()->lockForUpdate()->findOrFail($grant->getKey());
            Gate::forUser($actor)->authorize('revokeAccessGrant', [$lockedDocument, $lockedGrant]);
            $this->assertEffective($lockedGrant);

            $lockedGrant->forceFill([
                'revoked_at' => now(),
                'revoked_by' => $actor->getKey(),
                'revocation_reason' => trim($reason),
            ])->save();

            $this->audit->record(
                $actor,
                AuditAction::ACCESS_GRANT_REVOKED,
                $lockedGrant,
                ['revoked_at' => null],
                [
                    'revoked_at' => $lockedGrant->revoked_at,
                    'document_id' => $lockedDocument->getKey(),
                    'document_public_id' => $lockedDocument->public_id,
                    'beneficiary_user_id' => $lockedGrant->user_id,
                    'permission' => $lockedGrant->permission,
                    'confidentiality' => $lockedDocument->confidentiality,
                ],
            );
        }, 3);
    }

    private function lockDocument(Document $document): Document
    {
        return Document::query()->lockForUpdate()->findOrFail($document->getKey());
    }

    private function eligibleBeneficiary(User $actor, int $userId): User
    {
        $beneficiary = User::query()->with(['roles', 'organizationalUnit'])->find($userId);

        if (! $beneficiary
            || $beneficiary->status !== UserStatus::ACTIVE
            || ! $beneficiary->hasRole(RoleName::EXTRA->value)
            || ! $beneficiary->organizationalUnit?->is_active
            || (! $actor->hasInstitutionalGlobalScope()
                && $beneficiary->organizational_unit_id !== $actor->organizational_unit_id)) {
            throw ValidationException::withMessages([
                'user_id' => 'Seleccione un usuario Extra activo dentro de su ámbito autorizado.',
            ]);
        }

        return $beneficiary;
    }

    private function closeExpiredCycles(User $actor, Document $document, User $beneficiary): void
    {
        DocumentAccessGrant::query()
            ->where('document_id', $document->getKey())
            ->where('user_id', $beneficiary->getKey())
            ->whereNull('revoked_at')
            ->whereNotNull('ends_at')
            ->where('ends_at', '<=', now())
            ->update([
                'revoked_at' => now(),
                'revoked_by' => $actor->getKey(),
                'revocation_reason' => 'Ciclo expirado cerrado al emitir una nueva concesión.',
                'updated_at' => now(),
            ]);
    }

    private function assertEffective(DocumentAccessGrant $grant): void
    {
        if ($grant->revoked_at !== null
            || ($grant->starts_at !== null && $grant->starts_at->isFuture())
            || ($grant->ends_at !== null && $grant->ends_at->isPast())) {
            throw ValidationException::withMessages([
                'grant' => 'La concesión ya no está activa.',
            ]);
        }
    }
}
