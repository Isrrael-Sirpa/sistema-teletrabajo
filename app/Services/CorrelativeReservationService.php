<?php

namespace App\Services;

use App\Enums\DocumentConfidentiality;
use App\Enums\DocumentEventType;
use App\Enums\DocumentPriority;
use App\Enums\DocumentStatus;
use App\Enums\OutgoingAuthorizationStatus;
use App\Enums\RoleName;
use App\Models\Document;
use App\Models\DocumentCounter;
use App\Models\DocumentType;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CorrelativeReservationService
{
    public function __construct(private readonly DocumentEventService $events) {}

    public function reserve(User $actor, DocumentType $type): Document
    {
        if (! $actor->hasRole(RoleName::SECRETARY->value) || ! $actor->organizationalUnit?->is_active) {
            throw ValidationException::withMessages(['document_type' => 'Solo Secretaría puede reservar correlativos.']);
        }
        if (! $type->is_active || ! $type->uses_correlative) {
            throw ValidationException::withMessages(['document_type' => 'Este tipo documental no utiliza correlativo.']);
        }

        return DB::transaction(function () use ($actor, $type): Document {
            User::query()->lockForUpdate()->findOrFail($actor->getKey());
            $pendingReservation = Document::query()
                ->where('created_by', $actor->getKey())
                ->where('document_type_id', $type->getKey())
                ->whereNotNull('correlative_number')
                ->whereNull('sent_at')
                ->lockForUpdate()
                ->oldest('correlative_reserved_at')
                ->first();

            if ($pendingReservation) {
                throw ValidationException::withMessages([
                    'document_type' => 'Ya tiene una reserva pendiente para este tipo documental. Complete o envíe el documento con cite '.$pendingReservation->code.' antes de reservar otro.',
                ]);
            }

            $reservedAt = now();
            $year = $reservedAt->year;
            DocumentCounter::query()->insertOrIgnore([
                'origin_unit_id' => null,
                'document_type_id' => $type->getKey(),
                'year' => $year,
                'last_number' => 0,
                'created_at' => $reservedAt,
                'updated_at' => $reservedAt,
            ]);
            $counter = DocumentCounter::query()
                ->where('document_type_id', $type->getKey())
                ->where('year', $year)
                ->lockForUpdate()
                ->sole();
            $number = ++$counter->last_number;
            $counter->save();

            $prefix = trim((string) $actor->organizationalUnit->correlative_prefix);
            $label = trim((string) $type->correlative_label);
            if ($prefix === '' || $label === '') {
                throw ValidationException::withMessages([
                    'document_type' => 'La unidad o el tipo documental no tiene prefijo configurado.',
                ]);
            }

            $digits = max(4, min(12, (int) config('documents.workflow.code_digits', 4)));
            $code = $prefix.' '.$label.' N° '.str_pad((string) $number, $digits, '0', STR_PAD_LEFT).'/'.$year;
            $document = Document::query()->create([
                'document_type_id' => $type->getKey(),
                'origin_unit_id' => $actor->organizational_unit_id,
                'created_by' => $actor->getKey(),
                'subject' => 'Pendiente de redacción',
                'general_status' => DocumentStatus::DRAFT,
                'authorization_status' => OutgoingAuthorizationStatus::NOT_REQUESTED,
                'priority' => DocumentPriority::NORMAL,
                'confidentiality' => DocumentConfidentiality::NORMAL,
                'code' => $code,
                'correlative_number' => $number,
                'correlative_year' => $year,
                'correlative_reserved_by' => $actor->getKey(),
                'correlative_reserved_at' => $reservedAt,
                'lock_version' => 0,
            ]);
            $this->events->record($document, DocumentEventType::DOCUMENT_CREATED, $actor, metadata: ['subject' => $document->subject]);
            $this->events->record($document, DocumentEventType::CORRELATIVE_RESERVED, $actor, metadata: [
                'code' => $code,
                'number' => $number,
                'year' => $year,
            ]);

            return $document;
        });
    }
}
