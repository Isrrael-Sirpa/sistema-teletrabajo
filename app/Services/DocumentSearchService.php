<?php

namespace App\Services;

use App\Enums\DocumentEventType;
use App\Enums\DocumentPriority;
use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class DocumentSearchService
{
    public function __construct(
        private readonly DocumentDeadlineService $deadlines,
        private readonly DocumentWorkQueryService $workQueries,
    ) {}

    /**
     * Authorization is deliberately the first scope applied to every search.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<Document>
     */
    public function query(User $user, array $filters): Builder
    {
        $query = Document::query()
            ->visibleTo($user)
            ->with(['documentType', 'originUnit', 'creator', 'sender']);

        $this->applyText($query, (string) ($filters['q'] ?? ''));
        $this->applyDirectFilters($query, $filters);
        $this->applyRelations($query, $filters);
        $this->applyDates($query, $filters);
        $this->applyDeadline($query, (string) ($filters['deadline'] ?? ''));
        $this->applyCorrection($query, filter_var($filters['correction_pending'] ?? false, FILTER_VALIDATE_BOOL));

        if (filled($filters['version_min'] ?? null)) {
            $query->whereHas('versions', fn (Builder $versions) => $versions
                ->where('version_number', '>=', (int) $filters['version_min']));
        }

        if (filled($filters['work'] ?? null)) {
            $this->workQueries->apply($query, $user, (string) $filters['work']);
        }

        return $this->applyOrder(
            $query,
            (string) ($filters['sort'] ?? 'created_at'),
            (string) ($filters['direction'] ?? 'desc'),
        );
    }

    private function applyText(Builder $query, string $search): void
    {
        $search = Str::squish($search);
        if ($search === '') {
            return;
        }

        $normalized = Str::lower($search);
        if (preg_match('/^[A-Z0-9]+(?:-[A-Z0-9]+)*-\d{4}-\d{1,12}$/i', $search) === 1) {
            $query->where('code', Str::upper($search));

            return;
        }

        $literal = addcslashes($normalized, '\\%_');
        $prefix = $literal.'%';
        $partial = '%'.$literal.'%';
        $escape = $query->getConnection()->getDriverName() === 'pgsql'
            ? ' ESCAPE CHR(92)'
            : ' ESCAPE CHAR(92)';
        $query->where(function (Builder $text) use ($escape, $partial, $prefix): void {
            $text->whereRaw('LOWER(code) LIKE ?'.$escape, [$prefix])
                ->orWhereRaw('LOWER(subject) LIKE ?'.$escape, [$partial])
                ->orWhereRaw('LOWER(description) LIKE ?'.$escape, [$partial]);
        });
    }

    /** @param array<string, mixed> $filters */
    private function applyDirectFilters(Builder $query, array $filters): void
    {
        foreach ([
            'status' => 'general_status',
            'priority' => 'priority',
            'confidentiality' => 'confidentiality',
        ] as $filter => $column) {
            if (filled($filters[$filter] ?? null)) {
                $query->where($column, $filters[$filter]);
            }
        }

        foreach ([
            'document_type' => ['documentType', 'public_id'],
            'origin_unit' => ['originUnit', 'public_id'],
            'creator' => ['creator', 'public_id'],
            'sender' => ['sender', 'public_id'],
        ] as $filter => [$relation, $column]) {
            if (filled($filters[$filter] ?? null)) {
                $query->whereRelation($relation, $column, $filters[$filter]);
            }
        }
    }

    /** @param array<string, mixed> $filters */
    private function applyRelations(Builder $query, array $filters): void
    {
        if (filled($filters['recipient_unit'] ?? null)) {
            $query->whereHas('recipients', fn (Builder $recipients) => $recipients
                ->whereRelation('unit', 'public_id', $filters['recipient_unit']));
        }

        if (filled($filters['recipient_user'] ?? null)) {
            $query->whereHas('recipients', fn (Builder $recipients) => $recipients
                ->whereRelation('user', 'public_id', $filters['recipient_user']));
        }

        if (filled($filters['recipient_status'] ?? null)) {
            $query->whereHas('recipients', fn (Builder $recipients) => $recipients
                ->where('status', $filters['recipient_status']));
        }
    }

    /** @param array<string, mixed> $filters */
    private function applyDates(Builder $query, array $filters): void
    {
        foreach ([
            ['sent_from', 'sent_to', 'sent_at'],
            ['due_from', 'due_to', 'due_at'],
            ['created_from', 'created_to', 'created_at'],
        ] as [$from, $to, $column]) {
            if (filled($filters[$from] ?? null)) {
                $query->where($column, '>=', $this->startOfDay((string) $filters[$from]));
            }
            if (filled($filters[$to] ?? null)) {
                $query->where($column, '<', $this->startOfDay((string) $filters[$to])->addDay());
            }
        }
    }

    private function applyDeadline(Builder $query, string $deadline): void
    {
        $now = $this->deadlines->normalizeNow();

        match ($deadline) {
            'due_soon' => $query->dueSoon($now, $this->deadlines->dueSoonCutoff($now)),
            'overdue' => $query->overdue($now),
            'has_due' => $query->whereNotNull('due_at'),
            'no_due' => $query->whereNull('due_at'),
            default => null,
        };
    }

    private function applyCorrection(Builder $query, bool $pending): void
    {
        if (! $pending) {
            return;
        }

        $versionCreated = DocumentEventType::DOCUMENT_VERSION_CREATED->value;
        $correctionSent = DocumentEventType::CORRECTION_SENT->value;
        $query->where('general_status', DocumentStatus::RETURNED->value)
            ->whereRaw(
                '(SELECT COALESCE(MAX(version_events.id), 0) FROM document_events AS version_events WHERE version_events.document_id = documents.id AND version_events.event_type = ?) > '
                .'(SELECT COALESCE(MAX(sent_events.id), 0) FROM document_events AS sent_events WHERE sent_events.document_id = documents.id AND sent_events.event_type = ?)',
                [$versionCreated, $correctionSent],
            );
    }

    /** @return Builder<Document> */
    private function applyOrder(Builder $query, string $sort, string $direction): Builder
    {
        $direction = $direction === 'asc' ? 'asc' : 'desc';

        if ($sort === 'priority') {
            $query->orderByRaw(
                'CASE priority WHEN ? THEN 1 WHEN ? THEN 2 WHEN ? THEN 3 ELSE 0 END '.$direction,
                [DocumentPriority::NORMAL->value, DocumentPriority::IMPORTANT->value, DocumentPriority::URGENT->value],
            );
        } elseif (in_array($sort, ['sent_at', 'due_at', 'code'], true)) {
            $query->orderByRaw("CASE WHEN {$sort} IS NULL THEN 1 ELSE 0 END")
                ->orderBy($sort, $direction);
        } else {
            $query->orderBy('created_at', $direction);
        }

        return $query->orderByDesc('documents.id');
    }

    private function startOfDay(string $date): CarbonImmutable
    {
        return CarbonImmutable::parse($date, config('app.timezone'))->startOfDay();
    }
}
