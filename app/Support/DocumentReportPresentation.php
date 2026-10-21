<?php

namespace App\Support;

use App\Enums\DocumentConfidentiality;
use App\Enums\DocumentPriority;
use App\Enums\DocumentRecipientStatus;
use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Services\DocumentDeadlineService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class DocumentReportPresentation
{
    private const FILTER_LABELS = [
        'report' => 'Reporte', 'q' => 'Búsqueda', 'status' => 'Estado',
        'recipient_status' => 'Estado del destinatario', 'priority' => 'Prioridad',
        'confidentiality' => 'Clasificación', 'deadline' => 'Situación del plazo',
        'document_type' => 'Tipo de documento', 'origin_unit' => 'Unidad de origen',
        'recipient_unit' => 'Unidad destinataria', 'recipient_user' => 'Persona destinataria',
        'creator' => 'Creador', 'sender' => 'Remitente', 'period_from' => 'Desde', 'period_to' => 'Hasta',
        'sent_from' => 'Enviado desde', 'sent_to' => 'Enviado hasta',
        'due_from' => 'Fecha límite desde', 'due_to' => 'Fecha límite hasta',
        'created_from' => 'Creado desde', 'created_to' => 'Creado hasta',
        'correction_pending' => 'Correcciones', 'version_min' => 'Versión mínima',
        'sort' => 'Ordenar por', 'direction' => 'Orden', 'work' => 'Vista de trabajo',
    ];

    private const DEADLINE_LABELS = [
        'due_soon' => 'Próximos a vencer', 'overdue' => 'Vencidos',
        'has_due' => 'Con fecha límite', 'no_due' => 'Sin fecha límite',
    ];

    private const SORT_LABELS = [
        'created_at' => 'Fecha de creación', 'sent_at' => 'Fecha de envío',
        'due_at' => 'Fecha límite', 'priority' => 'Prioridad', 'code' => 'Código',
    ];

    private const WORK_LABELS = [
        'pending_receive' => 'Pendientes de recibir', 'in_review' => 'En revisión',
        'decision' => 'Requieren decisión', 'collaboration' => 'Copias informativas',
        'assigned' => 'Asignados', 'returned_correction' => 'Devueltos para corrección',
        'my_drafts' => 'Mis borradores', 'authorized' => 'Documentos autorizados',
    ];

    public function __construct(
        private readonly DocumentDeadlineService $deadlines,
    ) {}

    /** @return list<string> */
    public function headers(): array
    {
        return [
            'Código', 'Tipo de documento', 'Asunto', 'Unidad de origen', 'Remitente',
            'Estado', 'Prioridad', 'Clasificación', 'Fecha de envío', 'Fecha límite',
            'Situación del plazo', 'Destinatarios',
        ];
    }

    /** @return list<string|int|CarbonImmutable> */
    public function values(Document $document): array
    {
        return [
            $document->code ?? '—',
            $document->documentType->name,
            $document->subject,
            $document->originUnit->name,
            $document->sender?->name ?? '—',
            $document->general_status->label(),
            $document->priority->label(),
            $document->confidentiality->label(),
            $document->sent_at === null ? '—' : CarbonImmutable::instance($document->sent_at)->setTimezone($this->deadlines->timezone()),
            $document->due_at === null ? '—' : CarbonImmutable::instance($document->due_at)->setTimezone($this->deadlines->timezone()),
            $this->deadlines->status($document)->label(),
            (int) $document->recipients_count,
        ];
    }

    /**
     * Resolve labels only from the already-authorized, loaded filter options.
     *
     * @param  array<string, mixed>  $filters
     * @param  array{units: Collection, people: Collection, documentTypes: Collection}  $options
     * @param  array<string, string>  $reportLabels
     * @return array<string, string>
     */
    public function formatFilters(array $filters, array $options, array $reportLabels): array
    {
        $unitNames = $options['units']->pluck('name', 'public_id');
        $typeNames = $options['documentTypes']->pluck('name', 'public_id');
        $personNames = $options['people']->pluck('name', 'public_id');
        $formatted = [];

        foreach ($filters as $key => $value) {
            if (! filled($value) || ! isset(self::FILTER_LABELS[$key])) {
                continue;
            }

            if (str_ends_with($key, '_from') || str_ends_with($key, '_to')) {
                $formatted[self::FILTER_LABELS[$key]] = CarbonImmutable::createFromFormat(
                    '!Y-m-d', (string) $value, $this->deadlines->timezone(),
                )->format('d/m/Y');

                continue;
            }

            $formatted[self::FILTER_LABELS[$key]] = (string) match ($key) {
                'report' => $reportLabels[$value],
                'status' => DocumentStatus::from($value)->label(),
                'recipient_status' => DocumentRecipientStatus::from($value)->label(),
                'priority' => DocumentPriority::from($value)->label(),
                'confidentiality' => DocumentConfidentiality::from($value)->label(),
                'deadline' => self::DEADLINE_LABELS[$value],
                'origin_unit', 'recipient_unit' => $unitNames->get($value, 'Selección aplicada'),
                'document_type' => $typeNames->get($value, 'Selección aplicada'),
                'creator', 'sender', 'recipient_user' => $personNames->get($value, 'Selección aplicada'),
                'correction_pending' => $value ? 'Pendientes de corregir' : 'Sin restricción por correcciones',
                'sort' => self::SORT_LABELS[$value],
                'direction' => $value === 'asc' ? 'Ascendente' : 'Descendente',
                'work' => self::WORK_LABELS[$value],
                default => $value,
            };
        }

        return $formatted;
    }

    /** @param array<string, mixed> $filters */
    public function periodBasis(array $filters): string
    {
        return match ($filters['report'] ?? 'summary') {
            'sent' => 'envío', 'received' => 'recepción',
            'approved', 'returned', 'rejected' => 'decisión',
            'overdue', 'due_soon' => 'vencimiento', default => 'creación',
        };
    }
}
