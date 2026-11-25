<?php

namespace App\Enums;

enum ActivityType: string
{
    case CORRESPONDENCE_REVIEW = 'correspondence_review';
    case DRAFT_DOCUMENT = 'draft_document';
    case PREPARE_REPORT = 'prepare_report';
    case COLLECT_BACKGROUND = 'collect_background';
    case VERIFY_INFORMATION = 'verify_information';
    case INTERNAL_WORK = 'internal_work';
    case REQUEST_ATTENTION = 'request_attention';
    case INSTITUTIONAL_COORDINATION = 'institutional_coordination';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::CORRESPONDENCE_REVIEW => 'Revisión de correspondencia',
            self::DRAFT_DOCUMENT => 'Redacción de nota, carta u oficio',
            self::PREPARE_REPORT => 'Elaboración de informe',
            self::COLLECT_BACKGROUND => 'Recopilación de antecedentes',
            self::VERIFY_INFORMATION => 'Verificación de información',
            self::INTERNAL_WORK => 'Actividad interna de la unidad',
            self::REQUEST_ATTENTION => 'Atención de solicitud o caso',
            self::INSTITUTIONAL_COORDINATION => 'Coordinación institucional',
            self::OTHER => 'Otra actividad',
        };
    }
}
