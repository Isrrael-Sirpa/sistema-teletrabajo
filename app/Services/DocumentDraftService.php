<?php

namespace App\Services;

use App\Enums\DocumentConfidentiality;
use App\Enums\DocumentEventType;
use App\Enums\DocumentPriority;
use App\Enums\DocumentStatus;
use App\Enums\OutgoingAuthorizationStatus;
use App\Enums\RoleName;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\OrganizationalUnit;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class DocumentDraftService
{
    public function __construct(private readonly DocumentEventService $events) {}

    /** @param array<string, mixed> $data */
    public function create(User $actor, array $data): Document
    {
        Gate::forUser($actor)->authorize('create', Document::class);

        return DB::transaction(function () use ($actor, $data): Document {
            $origin = $this->resolveOrigin($actor, $data['origin_unit_id'] ?? null);
            $type = $this->resolveActiveType((int) $data['document_type_id']);

            if ($type->uses_correlative) {
                throw ValidationException::withMessages([
                    'document_type_id' => 'Los documentos con correlativo se preparan desde Control de correlativos para reservar su cite.',
                ]);
            }

            $document = Document::query()->create([
                'document_type_id' => $type->getKey(),
                'origin_unit_id' => $origin->getKey(),
                'created_by' => $actor->getKey(),
                'sender_user_id' => null,
                'subject' => $data['subject'],
                'description' => $data['description'] ?? null,
                'general_status' => DocumentStatus::DRAFT,
                'authorization_status' => $actor->hasRole(RoleName::SECRETARY->value)
                    ? OutgoingAuthorizationStatus::NOT_REQUESTED
                    : OutgoingAuthorizationStatus::NOT_REQUIRED,
                'priority' => DocumentPriority::from($data['priority']),
                'confidentiality' => DocumentConfidentiality::from($data['confidentiality']),
                'due_at' => $this->dueAt($data['due_at'] ?? null),
                'code' => null,
                'lock_version' => 0,
            ]);

            $this->events->record(
                $document,
                DocumentEventType::DOCUMENT_CREATED,
                $actor,
                metadata: [
                    'subject' => $document->subject,
                    'document_type' => $type->name,
                    'origin_unit' => $origin->name,
                ],
            );

            return $document;
        });
    }

    /** @param array<string, mixed> $data */
    public function update(User $actor, Document $document, array $data): Document
    {
        Gate::forUser($actor)->authorize('update', $document);

        return DB::transaction(function () use ($actor, $document, $data): Document {
            $origin = $this->resolveOrigin($actor, $data['origin_unit_id'] ?? null);
            $type = $this->resolveActiveType((int) $data['document_type_id']);
            if ($document->code !== null && (
                $document->document_type_id !== $type->getKey()
                || $document->origin_unit_id !== $origin->getKey()
            )) {
                throw ValidationException::withMessages([
                    'document' => 'El tipo documental y la unidad de origen no pueden cambiarse porque el cite ya fue reservado.',
                ]);
            }
            $expectedVersion = (int) $data['lock_version'];
            $trackedFields = [
                'document_type_id' => 'tipo documental',
                'origin_unit_id' => 'unidad de origen',
                'subject' => 'asunto',
                'description' => 'descripción',
                'priority' => 'prioridad',
                'confidentiality' => 'confidencialidad',
                'due_at' => 'fecha límite',
            ];
            $before = collect(array_keys($trackedFields))
                ->mapWithKeys(fn (string $field): array => [$field => $document->getRawOriginal($field)])
                ->all();

            $updated = Document::query()
                ->whereKey($document->getKey())
                ->where('general_status', DocumentStatus::DRAFT->value)
                ->where('lock_version', $expectedVersion)
                ->update([
                    'document_type_id' => $type->getKey(),
                    'origin_unit_id' => $origin->getKey(),
                    'subject' => $data['subject'],
                    'description' => $data['description'] ?? null,
                    'priority' => $data['priority'],
                    'confidentiality' => $data['confidentiality'],
                    'due_at' => $this->dueAt($data['due_at'] ?? null),
                    'authorization_status' => $actor->hasRole(RoleName::SECRETARY->value)
                        ? OutgoingAuthorizationStatus::NOT_REQUESTED->value
                        : $document->authorization_status->value,
                    'authorization_requested_by' => null,
                    'authorization_requested_at' => null,
                    'authorized_by' => null,
                    'authorized_at' => null,
                    'authorization_note' => null,
                    'lock_version' => DB::raw('lock_version + 1'),
                    'updated_at' => now(),
                ]);

            if ($updated !== 1) {
                $current = Document::query()->find($document->getKey());

                if (! $current || $current->general_status !== DocumentStatus::DRAFT) {
                    throw ValidationException::withMessages([
                        'document' => 'El documento ya no es un borrador editable.',
                    ]);
                }

                throw ValidationException::withMessages([
                    'lock_version' => 'El borrador fue modificado en otra sesión. Recargue la página y revise los cambios antes de continuar.',
                ]);
            }

            $document->refresh();
            $changedFields = collect($trackedFields)
                ->filter(fn (string $label, string $field): bool => $before[$field] != $document->getRawOriginal($field))
                ->values()
                ->all();

            if ($changedFields !== []) {
                $this->events->record(
                    $document,
                    DocumentEventType::DRAFT_UPDATED,
                    $actor,
                    metadata: ['changed_fields' => $changedFields],
                );
            }

            return $document;
        });
    }

    public function discard(User $actor, Document $document): void
    {
        Gate::forUser($actor)->authorize('discardDraft', $document);

        DB::transaction(function () use ($actor, $document): void {
            $locked = Document::query()->lockForUpdate()->findOrFail($document->getKey());

            if ($locked->general_status !== DocumentStatus::DRAFT
                || $locked->code !== null
                || $locked->sent_at !== null
                || $locked->created_by !== $actor->getKey()) {
                throw ValidationException::withMessages([
                    'document' => 'Solo puede descartarse un borrador propio que nunca fue enviado.',
                ]);
            }

            foreach (['destinatarios_documentos', 'versiones_documentos', 'observaciones_documentos', 'concesiones_acceso_documentos'] as $table) {
                if (DB::table($table)->where('document_id', $locked->getKey())->exists()) {
                    throw ValidationException::withMessages([
                        'document' => 'El borrador tiene información relacionada y no puede descartarse físicamente.',
                    ]);
                }
            }

            $this->events->record($locked, DocumentEventType::DRAFT_DISCARDED, $actor);
            $locked->delete();
        });
    }

    private function resolveOrigin(User $actor, mixed $requestedOriginId): OrganizationalUnit
    {
        if ($actor->hasInstitutionalGlobalScope()) {
            $origin = OrganizationalUnit::query()->active()->find($requestedOriginId);
        } else {
            $origin = OrganizationalUnit::query()
                ->availableAsDocumentDestination()
                ->find($actor->organizational_unit_id);
        }

        if (! $origin) {
            throw ValidationException::withMessages([
                'origin_unit_id' => 'No existe una unidad de origen activa y válida para este usuario.',
            ]);
        }

        return $origin;
    }

    private function resolveActiveType(int $id): DocumentType
    {
        $type = DocumentType::query()->active()->find($id);

        if (! $type) {
            throw ValidationException::withMessages([
                'document_type_id' => 'Seleccione un tipo documental activo.',
            ]);
        }

        return $type;
    }

    private function dueAt(mixed $value): ?CarbonImmutable
    {
        if (! filled($value)) {
            return null;
        }

        return CarbonImmutable::parse((string) $value, config('app.timezone'))->endOfDay();
    }
}
