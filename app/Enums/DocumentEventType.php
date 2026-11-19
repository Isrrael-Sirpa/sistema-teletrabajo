<?php

namespace App\Enums;

enum DocumentEventType: string
{
    case DOCUMENT_CREATED = 'document_created';
    case DRAFT_UPDATED = 'draft_updated';
    case DRAFT_DISCARDED = 'draft_discarded';
    case RECIPIENT_ADDED = 'recipient_added';
    case RECIPIENT_UPDATED = 'recipient_updated';
    case RECIPIENT_REMOVED = 'recipient_removed';
    case FILE_ADDED = 'file_added';
    case FILE_REMOVED = 'file_removed';
    case AUTHORIZATION_REQUESTED = 'authorization_requested';
    case AUTHORIZATION_APPROVED = 'authorization_approved';
    case AUTHORIZATION_RETURNED = 'authorization_returned';
    case DOCUMENT_SENT = 'document_sent';
    case DOCUMENT_RECEIVED = 'document_received';
    case REVIEW_STARTED = 'review_started';
    case DOCUMENT_APPROVED = 'document_approved';
    case DOCUMENT_RETURNED = 'document_returned';
    case DOCUMENT_REJECTED = 'document_rejected';
    case DOCUMENT_ANNULLED = 'document_annulled';
    case DOCUMENT_VERSION_CREATED = 'document_version_created';
    case CORRECTION_SENT = 'correction_sent';

    public function label(): string
    {
        return match ($this) {
            self::DOCUMENT_CREATED => 'Documento creado',
            self::DRAFT_UPDATED => 'Borrador actualizado',
            self::DRAFT_DISCARDED => 'Borrador descartado',
            self::RECIPIENT_ADDED => 'Destinatario agregado',
            self::RECIPIENT_UPDATED => 'Destinatario modificado',
            self::RECIPIENT_REMOVED => 'Destinatario retirado',
            self::FILE_ADDED => 'Archivo adjunto agregado',
            self::FILE_REMOVED => 'Archivo adjunto retirado',
            self::AUTHORIZATION_REQUESTED => 'Autorización de salida solicitada',
            self::AUTHORIZATION_APPROVED => 'Salida autorizada',
            self::AUTHORIZATION_RETURNED => 'Autorización devuelta',
            self::DOCUMENT_SENT => 'Documento enviado',
            self::DOCUMENT_RECEIVED => 'Documento recibido',
            self::REVIEW_STARTED => 'Revisión iniciada',
            self::DOCUMENT_APPROVED => 'Documento aprobado',
            self::DOCUMENT_RETURNED => 'Documento devuelto',
            self::DOCUMENT_REJECTED => 'Documento rechazado',
            self::DOCUMENT_ANNULLED => 'Documento anulado',
            self::DOCUMENT_VERSION_CREATED => 'Nueva versión preparada',
            self::CORRECTION_SENT => 'Corrección enviada',
        };
    }

    public function isOfficial(): bool
    {
        return in_array($this, [
            self::DOCUMENT_SENT,
            self::DOCUMENT_RECEIVED,
            self::REVIEW_STARTED,
            self::DOCUMENT_APPROVED,
            self::DOCUMENT_RETURNED,
            self::DOCUMENT_REJECTED,
            self::DOCUMENT_ANNULLED,
            self::CORRECTION_SENT,
        ], true);
    }
}
