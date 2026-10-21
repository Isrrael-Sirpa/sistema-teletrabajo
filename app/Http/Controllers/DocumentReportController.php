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
use App\Services\DocumentReportPdfExporter;
use App\Services\DocumentReportService;
use App\Services\DocumentReportXlsxExporter;
use App\Support\DocumentReportPresentation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class DocumentReportController extends Controller
{
    public function __construct(
        private readonly DocumentReportService $reports,
        private readonly DocumentDeadlineService $deadlines,
        private readonly DelegatedAuthorizationService $delegations,
        private readonly AuditLogService $audit,
        private readonly DocumentReportPresentation $presentation,
        private readonly DocumentReportXlsxExporter $xlsx,
        private readonly DocumentReportPdfExporter $pdf,
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

        $options = $this->reports->options($request->user());

        return view('reports.documents', [
            ...$options,
            'documents' => $documents,
            'summaries' => $summaries,
            'deadlineStatuses' => $deadlineStatuses,
            'filters' => $filters,
            'reportLabels' => $this->reports->reportLabels(),
            'exportMaxRows' => $this->maxRows(),
            'appliedFilters' => $this->presentation->formatFilters($filters, $options, $this->reports->reportLabels()),
            'periodBasis' => $this->presentation->periodBasis($filters),
        ]);
    }

    public function csv(DocumentReportRequest $request): StreamedResponse|RedirectResponse
    {
        return $this->export($request, 'csv');
    }

    public function xlsx(DocumentReportRequest $request): StreamedResponse|RedirectResponse
    {
        return $this->export($request, 'xlsx');
    }

    public function pdf(DocumentReportRequest $request): StreamedResponse|RedirectResponse
    {
        return $this->export($request, 'pdf');
    }

    private function export(DocumentReportRequest $request, string $format): StreamedResponse|RedirectResponse
    {
        $this->authorizeView($request->user());
        $this->authorizeExport($request->user());
        $filters = $request->safe()->except('page');
        try {
            $query = $this->reports->query($request->user(), $filters);
            $rowCount = $this->count($query);
            $maximum = $this->maxRows();

            if ($rowCount > $maximum) {
                throw ValidationException::withMessages([
                    'export' => "La exportación contiene {$rowCount} filas. Reduzca el período o aplique filtros para no superar {$maximum}.",
                ]);
            }

            $documents = $this->reports->exportDocuments($query);
            $rowCount = $documents->count();
            if ($rowCount > $maximum) {
                throw ValidationException::withMessages([
                    'export' => "La exportación contiene {$rowCount} filas. Reduzca el período o aplique filtros para no superar {$maximum}.",
                ]);
            }

            if ($format === 'csv') {
                $content = $this->reports->csvDocuments($documents);
            } else {
                $renderer = match ($format) {
                    'xlsx' => $this->xlsx,
                    'pdf' => $this->pdf,
                };
                $content = $renderer->render(
                    $documents,
                    $this->exportMetadata($request->user(), $filters, $rowCount),
                );
            }
            $this->audit->record($request->user(), AuditAction::REPORT_EXPORTED, newValues: [
                'format' => $format,
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

            $filename = 'reporte-documentos-'.now()->format('Ymd-His').'.'.$format;

            return response()->streamDownload(
                static function () use ($content): void {
                    echo $content;
                },
                $filename,
                ['Content-Type' => match ($format) {
                    'csv' => 'text/csv; charset=UTF-8',
                    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'pdf' => 'application/pdf',
                }],
            );
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            return redirect()->route('reports.documents.index', $filters)->withErrors([
                'export' => 'No fue posible generar el reporte. Intente nuevamente.',
            ]);
        }
    }

    /** @param array<string, mixed> $filters @return array<string, mixed> */
    private function exportMetadata(User $user, array $filters, int $rowCount): array
    {
        $needsOptions = collect(Arr::only($filters, [
            'document_type', 'origin_unit', 'recipient_unit', 'recipient_user', 'creator', 'sender',
        ]))->contains(fn (mixed $value): bool => filled($value));
        $options = $needsOptions ? $this->reports->options($user) : [
            'units' => collect(), 'people' => collect(), 'documentTypes' => collect(),
        ];

        return [
            'generated_at' => $this->deadlines->normalizeNow(),
            'generated_by' => $user->name,
            'generated_role' => $user->roleName()?->label(),
            'filters' => $this->presentation->formatFilters($filters, $options, $this->reports->reportLabels()),
            'total' => $rowCount,
        ];
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
