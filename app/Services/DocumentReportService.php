<?php

namespace App\Services;

use App\Enums\DocumentPriority;
use App\Enums\DocumentRecipientStatus;
use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\DocumentRecipient;
use App\Models\DocumentType;
use App\Models\OrganizationalUnit;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class DocumentReportService
{
    public function __construct(
        private readonly DocumentSearchService $search,
        private readonly DocumentDeadlineService $deadlines,
    ) {}

    /** @param array<string, mixed> $filters @return Builder<Document> */
    public function query(User $user, array $filters): Builder
    {
        $query = $this->search->query($user, Arr::except($filters, [
            'report', 'period_from', 'period_to', 'page',
        ]));

        $this->applyReport($query, (string) ($filters['report'] ?? 'summary'));
        $this->applyPeriod(
            $query,
            (string) ($filters['report'] ?? 'summary'),
            $filters['period_from'] ?? null,
            $filters['period_to'] ?? null,
        );

        return $query;
    }

    /** @param Builder<Document> $query @return array<string, Collection<int, array<string, mixed>>> */
    public function summaries(Builder $query): array
    {
        return [
            'statuses' => $this->grouped($query, 'general_status')->map(function (array $row): array {
                $status = DocumentStatus::tryFrom((string) $row['value']);

                return ['label' => $status?->label() ?? (string) $row['value'], 'count' => $row['count']];
            }),
            'priorities' => $this->grouped($query, 'priority')->map(function (array $row): array {
                $priority = DocumentPriority::tryFrom((string) $row['value']);

                return ['label' => $priority?->label() ?? (string) $row['value'], 'count' => $row['count']];
            }),
            'types' => $this->groupedRelation($query, 'document_types', 'document_type_id'),
            'units' => $this->unitSummary($query),
        ];
    }

    /** @return array{units: Collection, people: Collection, documentTypes: Collection} */
    public function options(User $user): array
    {
        $visible = fn () => Document::query()->visibleTo($user);
        $units = OrganizationalUnit::query()
            ->where(function (Builder $query) use ($visible): void {
                $query->whereIn('id', $visible()->select('origin_unit_id'))
                    ->orWhereIn('id', DocumentRecipient::query()
                        ->select('unit_id')
                        ->whereIn('document_id', $visible()->select('documents.id')));
            })
            ->orderBy('name')
            ->get();
        $people = User::query()
            ->where(function (Builder $query) use ($visible): void {
                $query->whereIn('id', $visible()->select('created_by'))
                    ->orWhereIn('id', $visible()->whereNotNull('sender_user_id')->select('sender_user_id'));
            })
            ->orderBy('name')
            ->get();
        $documentTypes = DocumentType::query()
            ->whereIn('id', $visible()->select('document_type_id'))
            ->orderBy('name')
            ->get();

        return compact('units', 'people', 'documentTypes');
    }

    /** @param Builder<Document> $query */
    public function csv(Builder $query): string
    {
        $documents = $this->clean($query)
            ->select([
                'documents.id', 'documents.code', 'documents.document_type_id',
                'documents.origin_unit_id', 'documents.sender_user_id', 'documents.subject',
                'documents.general_status', 'documents.priority', 'documents.confidentiality',
                'documents.sent_at', 'documents.due_at', 'documents.created_at',
            ])
            ->with(['documentType:id,name', 'originUnit:id,name', 'sender:id,name'])
            ->withCount('recipients')
            ->orderByDesc('documents.created_at')
            ->orderByDesc('documents.id')
            ->get();

        $stream = fopen('php://temp', 'w+b');
        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, [
            'Código', 'Tipo documental', 'Asunto', 'Origen', 'Estado', 'Prioridad',
            'Clasificación', 'Fecha de envío', 'Fecha límite', 'Vencimiento',
            'Remitente', 'Cantidad de destinatarios',
        ]);

        foreach ($documents as $document) {
            fputcsv($stream, array_map($this->safeCsvCell(...), [
                $document->code ?? 'Borrador sin código',
                $document->documentType->name,
                $document->subject,
                $document->originUnit->name,
                $document->general_status->label(),
                $document->priority->label(),
                $document->confidentiality->label(),
                $document->sent_at?->timezone($this->deadlines->timezone())->format('Y-m-d H:i') ?? '',
                $document->due_at?->timezone($this->deadlines->timezone())->format('Y-m-d H:i') ?? '',
                $this->deadlines->status($document)->label(),
                $document->sender?->name ?? '',
                (string) $document->recipients_count,
            ]));
        }

        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return (string) $csv;
    }

    /** @return array<string, string> */
    public function reportLabels(): array
    {
        return [
            'summary' => 'Resumen general',
            'sent' => 'Documentos enviados',
            'received' => 'Documentos recibidos',
            'approved' => 'Documentos aprobados',
            'returned' => 'Documentos devueltos',
            'rejected' => 'Documentos rechazados',
            'overdue' => 'Documentos vencidos',
            'due_soon' => 'Documentos próximos a vencer',
        ];
    }

    /** @param Builder<Document> $query */
    private function applyReport(Builder $query, string $report): void
    {
        match ($report) {
            'sent' => $query->whereNotNull('sent_at'),
            'received' => $query->whereHas('recipients', fn (Builder $recipients) => $recipients->whereNotNull('received_at')),
            'approved' => $query->where('general_status', DocumentStatus::APPROVED->value),
            'returned' => $query->where('general_status', DocumentStatus::RETURNED->value),
            'rejected' => $query->where('general_status', DocumentStatus::REJECTED->value),
            'overdue' => $query->overdue($this->deadlines->normalizeNow()),
            'due_soon' => $query->dueSoon($this->deadlines->normalizeNow(), $this->deadlines->dueSoonCutoff()),
            default => null,
        };
    }

    /** @param Builder<Document> $query */
    private function applyPeriod(Builder $query, string $report, mixed $from, mixed $to): void
    {
        if (! filled($from) && ! filled($to)) {
            return;
        }

        if ($report === 'received') {
            $query->whereHas('recipients', function (Builder $recipients) use ($from, $to): void {
                $recipients->whereNotNull('received_at');
                $this->dateRange($recipients, 'received_at', $from, $to);
            });

            return;
        }

        if (in_array($report, ['approved', 'returned', 'rejected'], true)) {
            $recipientStatus = DocumentRecipientStatus::from($report);
            $query->whereHas('recipients', function (Builder $recipients) use ($from, $recipientStatus, $to): void {
                $recipients->where('status', $recipientStatus->value)->whereNotNull('decided_at');
                $this->dateRange($recipients, 'decided_at', $from, $to);
            });

            return;
        }

        $column = match ($report) {
            'sent' => 'sent_at',
            'overdue', 'due_soon' => 'due_at',
            default => 'created_at',
        };
        $this->dateRange($query, $column, $from, $to);
    }

    private function dateRange(Builder $query, string $column, mixed $from, mixed $to): void
    {
        if (filled($from)) {
            $query->where($column, '>=', $this->startOfDay((string) $from));
        }
        if (filled($to)) {
            $query->where($column, '<', $this->startOfDay((string) $to)->addDay());
        }
    }

    /** @param Builder<Document> $query @return Collection<int, array{value: string, count: int}> */
    private function grouped(Builder $query, string $column): Collection
    {
        return $this->aggregateBase($query)
            ->select("documents.{$column}")
            ->selectRaw('COUNT(documents.id) AS aggregate_count')
            ->groupBy("documents.{$column}")
            ->orderBy("documents.{$column}")
            ->get()
            ->map(fn (Document $row): array => [
                'value' => $row->getRawOriginal($column),
                'count' => (int) $row->getAttribute('aggregate_count'),
            ]);
    }

    /** @param Builder<Document> $query @return Collection<int, array{label: string, count: int}> */
    private function groupedRelation(Builder $query, string $table, string $foreignKey): Collection
    {
        return $this->aggregateBase($query)
            ->join($table, "{$table}.id", '=', "documents.{$foreignKey}")
            ->select("{$table}.name AS aggregate_label")
            ->selectRaw('COUNT(documents.id) AS aggregate_count')
            ->groupBy("{$table}.id", "{$table}.name")
            ->orderBy("{$table}.name")
            ->get()
            ->map(fn (Document $row): array => [
                'label' => (string) $row->getAttribute('aggregate_label'),
                'count' => (int) $row->getAttribute('aggregate_count'),
            ]);
    }

    /** @param Builder<Document> $query @return Collection<int, array<string, mixed>> */
    private function unitSummary(Builder $query): Collection
    {
        $rows = $this->aggregateBase($query)
            ->join('organizational_units AS report_units', 'report_units.id', '=', 'documents.origin_unit_id')
            ->select(['report_units.id AS unit_id', 'report_units.name AS unit_name', 'documents.general_status'])
            ->selectRaw('COUNT(documents.id) AS aggregate_count')
            ->groupBy('report_units.id', 'report_units.name', 'documents.general_status')
            ->orderBy('report_units.name')
            ->get();

        $overdue = $this->aggregateBase($query)
            ->overdue($this->deadlines->normalizeNow())
            ->select('documents.origin_unit_id')
            ->selectRaw('COUNT(documents.id) AS aggregate_count')
            ->groupBy('documents.origin_unit_id')
            ->pluck('aggregate_count', 'origin_unit_id');

        return $rows->groupBy('unit_id')->map(function (Collection $unitRows, int|string $unitId) use ($overdue): array {
            $counts = $unitRows->mapWithKeys(fn (Document $row): array => [
                (string) $row->getRawOriginal('general_status') => (int) $row->getAttribute('aggregate_count'),
            ]);

            return [
                'label' => (string) $unitRows->first()->getAttribute('unit_name'),
                'total' => $counts->sum(),
                'sent' => (int) $counts->get(DocumentStatus::SENT->value, 0),
                'approved' => (int) $counts->get(DocumentStatus::APPROVED->value, 0),
                'returned' => (int) $counts->get(DocumentStatus::RETURNED->value, 0),
                'overdue' => (int) $overdue->get($unitId, 0),
            ];
        })->values();
    }

    /** @param Builder<Document> $query @return Builder<Document> */
    private function clean(Builder $query): Builder
    {
        return (clone $query)->setEagerLoads([])->reorder();
    }

    /**
     * Aggregate from an ID subquery so joins used by the summaries cannot make
     * visibility columns ambiguous or multiply the source document rows.
     *
     * @param  Builder<Document>  $query
     * @return Builder<Document>
     */
    private function aggregateBase(Builder $query): Builder
    {
        return Document::query()->whereIn(
            'documents.id',
            $this->clean($query)->select('documents.id'),
        );
    }

    private function startOfDay(string $date): CarbonImmutable
    {
        return CarbonImmutable::parse($date, $this->deadlines->timezone())->startOfDay();
    }

    private function safeCsvCell(mixed $value): string
    {
        $cell = (string) $value;
        if (preg_match('/^[\s]*[=+\-@]/u', $cell) === 1) {
            return "'".$cell;
        }

        return $cell;
    }
}
