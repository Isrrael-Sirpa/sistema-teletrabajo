<?php

namespace App\Models;

use App\Enums\DocumentEventType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable([
    'document_id', 'document_recipient_id', 'actor_id', 'event_type',
    'from_status', 'to_status', 'reason', 'metadata', 'occurred_at',
])]
class DocumentEvent extends Model
{
    protected $table = 'eventos_documentos';

    public $timestamps = false;

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class)->withTrashed();
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(DocumentRecipient::class, 'document_recipient_id')->withTrashed();
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function actorName(): string
    {
        return $this->actor?->name ?? 'Sistema';
    }

    public function description(): string
    {
        $actor = $this->actorName();
        $target = (string) ($this->metadata['recipient_label'] ?? 'el destinatario');

        return match ($this->event_type) {
            DocumentEventType::DOCUMENT_CREATED => "{$actor} creó el borrador.",
            DocumentEventType::DRAFT_UPDATED => "{$actor} actualizó el borrador.",
            DocumentEventType::DRAFT_DISCARDED => "{$actor} descartó el borrador.",
            DocumentEventType::RECIPIENT_ADDED => 'Se agregó a '.$target.' como '.($this->metadata['requires_response'] ?? false ? 'destinatario que requiere respuesta.' : 'copia informativa.'),
            DocumentEventType::RECIPIENT_UPDATED => 'Se cambió la finalidad de '.$target.' a '.($this->metadata['requires_response'] ?? false ? 'requiere respuesta.' : 'copia informativa.'),
            DocumentEventType::RECIPIENT_REMOVED => "Se retiró a {$target} del borrador.",
            DocumentEventType::FILE_ADDED => $actor.' adjuntó el archivo '.($this->metadata['original_name'] ?? 'indicado').'.',
            DocumentEventType::FILE_REMOVED => $actor.' retiró el archivo '.($this->metadata['original_name'] ?? 'indicado').'.',
            DocumentEventType::AUTHORIZATION_REQUESTED => $actor.' solicitó autorización para el envío.',
            DocumentEventType::AUTHORIZATION_APPROVED => $actor.' autorizó el envío institucional.',
            DocumentEventType::AUTHORIZATION_RETURNED => $actor.' devolvió el borrador para corrección.',
            DocumentEventType::DOCUMENT_SENT => "{$actor} envió oficialmente el documento.",
            DocumentEventType::DOCUMENT_RECEIVED => "{$actor} recibió el documento por {$target}.",
            DocumentEventType::REVIEW_STARTED => "{$actor} inició la revisión para {$target}.",
            DocumentEventType::DOCUMENT_APPROVED => "Documento aprobado por {$actor} para {$target}.",
            DocumentEventType::DOCUMENT_RETURNED => "Documento devuelto por {$actor} para {$target}.",
            DocumentEventType::DOCUMENT_REJECTED => "Documento rechazado por {$actor} para {$target}.",
            DocumentEventType::DOCUMENT_ANNULLED => "{$actor} anuló formalmente el documento.",
            DocumentEventType::DOCUMENT_VERSION_CREATED => $actor.' preparó la versión '.($this->metadata['version'] ?? 'indicada').' para corregir el documento.',
            DocumentEventType::CORRECTION_SENT => $actor.' envió la corrección correspondiente a la versión '.($this->metadata['version'] ?? 'indicada').'.',
        };
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Los eventos documentales son inmutables.');
        });

        static::deleting(function (): never {
            throw new LogicException('Los eventos documentales no pueden eliminarse.');
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'event_type' => DocumentEventType::class,
            'metadata' => 'array',
            'occurred_at' => 'immutable_datetime',
        ];
    }
}
