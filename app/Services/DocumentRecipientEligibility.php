<?php

namespace App\Services;

use App\Enums\OrganizationalUnitType;
use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\DocumentRecipient;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class DocumentRecipientEligibility
{
    public function assertAllowedUnitFor(User $actor, \App\Models\OrganizationalUnit $unit): void
    {
        if ($actor->hasRole(RoleName::STUDENT->value)
            && ! in_array($unit->type, [OrganizationalUnitType::CAREER, OrganizationalUnitType::KARDEX], true)) {
            throw ValidationException::withMessages([
                'unit_id' => 'Como estudiante, solo puede enviar documentos a su carrera o a Kardex.',
            ]);
        }

        if ($actor->hasRole(RoleName::STUDENT->value)
            && $unit->type === OrganizationalUnitType::CAREER
            && $unit->getKey() !== $actor->organizational_unit_id) {
            throw ValidationException::withMessages([
                'unit_id' => 'Como estudiante, solo puede elegir su propia carrera o Kardex.',
            ]);
        }
    }

    /** @return Builder<User> */
    public function personalCandidates(): Builder
    {
        return User::query()
            ->where('status', UserStatus::ACTIVE->value)
            ->whereNotNull('organizational_unit_id')
            ->whereHas('organizationalUnit', function (Builder $query): void {
                $query->where('is_active', true)->whereIn('type', [
                    OrganizationalUnitType::CAREER->value,
                    OrganizationalUnitType::KARDEX->value,
                ]);
            })
            ->whereHas('roles', function (Builder $query): void {
                $query->whereIn('name', [
                    RoleName::CAREER_HEAD->value,
                    RoleName::KARDEX_HEAD->value,
                    RoleName::SECRETARY->value,
                    RoleName::SECRETARY_ASSISTANT->value,
                ]);
            });
    }

    public function assertPersonalCandidate(User $user, bool $requiresResponse): void
    {
        $user->loadMissing(['organizationalUnit', 'roles']);
        $role = $user->roleName();

        if ($user->status !== UserStatus::ACTIVE
            || ! $user->organizationalUnit
            || ! $user->organizationalUnit->is_active
            || ! in_array($user->organizationalUnit->type, [
                OrganizationalUnitType::CAREER,
                OrganizationalUnitType::KARDEX,
            ], true)
            || ! $role?->canBePersonalDocumentRecipient()) {
            throw ValidationException::withMessages([
                'user_id' => 'Seleccione una persona activa del equipo de una carrera o Kardex.',
            ]);
        }

        if ($requiresResponse) {
            throw ValidationException::withMessages([
                'requires_response' => 'Agregue una unidad como destinatario principal. Las personas pueden recibir copias informativas.',
            ]);
        }
    }

    public function assertPreparedRecipient(DocumentRecipient $recipient): void
    {
        $unit = $recipient->unit()->first();

        if (! $unit
            || ! $unit->is_active
            || $unit->type === OrganizationalUnitType::FACULTY) {
            throw ValidationException::withMessages([
                'recipients' => 'Uno de los destinatarios pertenece a una unidad inactiva o no autorizada.',
            ]);
        }

        if ($recipient->user_id === null) {
            return;
        }

        $user = User::query()->with(['organizationalUnit', 'roles'])->find($recipient->user_id);

        if (! $user || $user->organizational_unit_id !== $recipient->unit_id) {
            throw ValidationException::withMessages([
                'recipients' => 'Uno de los destinatarios personales ya no pertenece a la unidad registrada.',
            ]);
        }

        try {
            $this->assertPersonalCandidate($user, $recipient->requires_response);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages([
                'recipients' => $exception->validator->errors()->first(),
            ]);
        }
    }
}
