<?php

namespace App\Http\Controllers;

use App\Enums\DocumentRecipientStatus;
use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\Document;
use App\Models\DocumentRecipient;
use App\Models\DocumentType;
use App\Models\OrganizationalUnit;
use App\Models\User;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class CorrespondenceBookController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $this->authorizeAccess($user);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'year' => ['nullable', 'integer', 'min:2000', 'max:'.(now()->year + 1)],
            'month' => ['nullable', 'integer', 'between:1,12'],
            'document_type' => ['nullable', 'uuid', 'exists:tipos_documentos,public_id'],
            'unit' => ['nullable', 'uuid', 'exists:unidades_organizacionales,public_id'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ]);

        $isGlobal = $user->hasInstitutionalGlobalScope();
        $selectedUnit = $isGlobal && filled($filters['unit'] ?? null)
            ? OrganizationalUnit::query()->where('public_id', $filters['unit'])->firstOrFail()
            : ($isGlobal ? null : $user->organizationalUnit);
        $year = (int) ($filters['year'] ?? now()->year);
        $month = filled($filters['month'] ?? null) ? (int) $filters['month'] : null;

        $entries = DocumentRecipient::query()
            ->with([
                'document.documentType',
                'document.originUnit',
                'document.sender',
                'unit',
                'user',
                'receiver',
            ])
            ->where('status', '!=', DocumentRecipientStatus::PREPARED->value)
            ->whereHas('document', function (Builder $documents) use ($filters, $year): void {
                $documents->whereNotNull('sent_at')->whereYear('sent_at', $year);

                if (filled($filters['month'] ?? null)) {
                    $documents->whereMonth('sent_at', (int) $filters['month']);
                }

                if (filled($filters['date_from'] ?? null)) {
                    $documents->whereDate('sent_at', '>=', $filters['date_from']);
                }
                if (filled($filters['date_to'] ?? null)) {
                    $documents->whereDate('sent_at', '<=', $filters['date_to']);
                }

                if (filled($filters['document_type'] ?? null)) {
                    $documents->whereRelation('documentType', 'public_id', $filters['document_type']);
                }

                if (filled($filters['q'] ?? null)) {
                    $term = '%'.addcslashes(trim($filters['q']), '\\%_').'%';
                    $documents->where(fn (Builder $search) => $search
                        ->where('code', 'like', $term)
                        ->orWhere('subject', 'like', $term));
                }
            });

        if ($selectedUnit !== null) {
            $this->applyUnitScope($entries, $selectedUnit->id);
        }

        $entries = $entries
            ->orderByDesc(
                Document::query()
                    ->select('sent_at')
                    ->whereColumn('documentos.id', 'destinatarios_documentos.document_id')
                    ->limit(1),
            )
            ->orderByDesc('id')
            ->paginate($request->routeIs('correspondence-book.pdf') ? 10000 : 25)
            ->withQueryString();

        return view('correspondence-book.index', [
            'entries' => $entries,
            'filters' => $filters,
            'year' => $year,
            'month' => $month,
            'selectedUnit' => $selectedUnit,
            'isGlobal' => $isGlobal,
            'units' => $isGlobal
                ? OrganizationalUnit::query()->orderBy('name')->get(['id', 'public_id', 'name', 'is_active'])
                : collect(),
            'documentTypes' => DocumentType::query()->orderBy('name')->get(['id', 'public_id', 'name', 'is_active']),
        ]);
    }

    public function pdf(Request $request): Response
    {
        $htmlResponse = $this->index($request);
        $entries = $htmlResponse->getData()['entries'];
        $entries = $entries->getCollection();
        $data = $htmlResponse->getData();

        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(view('correspondence-book.pdf', [
            ...$data,
            'entries' => $entries,
        ])->render());
        $dompdf->setPaper('letter', 'landscape');
        $dompdf->render();

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="libro-correspondencia-'.$data['year'].'.pdf"',
        ]);
    }

    /** @param Builder<DocumentRecipient> $query */
    private function applyUnitScope(Builder $query, int $unitId): void
    {
        $query->where(fn (Builder $scope) => $scope
            ->where('unit_id', $unitId)
            ->orWhereRelation('document', 'origin_unit_id', $unitId));
    }

    private function authorizeAccess(User $user): void
    {
        $allowedRole = $user->hasInstitutionalGlobalScope()
            || $user->hasRole(RoleName::SECRETARY->value)
            || $user->isResponsibleForUnit((int) $user->organizational_unit_id);

        abort_unless(
            $allowedRole
            && ($user->can(PermissionName::REPORTS_VIEW_GLOBAL->value)
                || $user->can(PermissionName::REPORTS_VIEW_UNIT->value)),
            403,
        );
    }
}
