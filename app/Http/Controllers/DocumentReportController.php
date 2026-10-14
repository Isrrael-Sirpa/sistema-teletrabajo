<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Enums\PermissionName;
use App\Http\Requests\DocumentReportRequest;
use App\Models\Document;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\DelegatedAuthorizationService;
use App\Services\DocumentDeadlineService;
use App\Services\DocumentReportService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentReportController extends Controller
{
    public function __construct(
        private readonly DocumentReportService $reports,
        private readonly DocumentDeadlineService $deadlines,
        private readonly DelegatedAuthorizationService $delegations,
        private readonly AuditLogService $audit,
    ) {}

    public function index(DocumentReportRequest $request): View
    {
        $this->authorizeView($request->user());
        $filters = $request->safe()->except('page');
        $query = $this->reports->query($request->user(), $filters);
        $summaries = $this->reports->summaries($query);
        $documents = (clone $query)
            ->withCount('recipients')
            ->paginate(max(5, min(100, (int) config('reports.per_page', 25))))
            ->withQueryString();
        $now = $this->deadlines->normalizeNow();
        $deadlineStatuses = $documents->getCollection()->mapWithKeys(
            fn (Document $document): array => [
                $document->getKey() => $this->deadlines->status($document, $now),
            ],
        );

        return view('reports.documents', [
            ...$this->reports->options($request->user()),
            'documents' => $documents,
            'summaries' => $summaries,
            'deadlineStatuses' => $deadlineStatuses,
            'filters' => $filters,
            'reportLabels' => $this->reports->reportLabels(),
            'exportMaxRows' => $this->maxRows(),
        ]);
    }

    public function csv(DocumentReportRequest $request): StreamedResponse
    {
        $this->authorizeView($request->user());
        $this->authorizeExport($request->user());
        $filters = $request->safe()->except('page');
        $query = $this->reports->query($request->user(), $filters);
        $rowCount = $this->count($query);
        $maximum = $this->maxRows();

        if ($rowCount > $maximum) {
            throw ValidationException::withMessages([
                'export' => "La exportación contiene {$rowCount} filas. Reduzca el período o aplique filtros para no superar {$maximum}.",
            ]);
        }

        $content = $this->reports->csv($query);
        $this->audit->record($request->user(), AuditAction::REPORT_EXPORTED, newValues: [
            'format' => 'csv',
            'result' => 'success',
            'report' => (string) ($filters['report'] ?? 'summary'),
            'row_count' => $rowCount,
            'filters' => Arr::only($filters, [
                'report', 'status', 'recipient_status', 'priority', 'confidentiality',
                'deadline', 'document_type', 'origin_unit', 'recipient_unit',
                'period_from', 'period_to', 'sent_from', 'sent_to', 'due_from',
                'due_to', 'created_from', 'created_to', 'correction_pending', 'version_min',
            ]),
        ]);

        $filename = 'reporte-documentos-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(
            static function () use ($content): void {
                echo $content;
            },
            $filename,
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }

    private function authorizeView(User $user): void
    {
        abort_unless(
            $user->can(PermissionName::REPORTS_VIEW_GLOBAL->value)
            || $user->can(PermissionName::REPORTS_VIEW_UNIT->value)
            || $this->delegations->hasAny($user, PermissionName::REPORTS_VIEW_UNIT),
            403,
        );
    }

    private function authorizeExport(User $user): void
    {
        abort_unless(
            $user->can(PermissionName::REPORTS_EXPORT->value)
            || $this->delegations->hasAny($user, PermissionName::REPORTS_EXPORT),
            403,
        );
    }

    /** @param Builder<Document> $query */
    private function count(Builder $query): int
    {
        return (int) (clone $query)->setEagerLoads([])->reorder()->count('documents.id');
    }

    private function maxRows(): int
    {
        return max(1, min(100_000, (int) config('reports.export_max_rows', 5_000)));
    }
}
