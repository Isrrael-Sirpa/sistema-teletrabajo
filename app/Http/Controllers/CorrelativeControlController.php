<?php

namespace App\Http\Controllers;

use App\Enums\RoleName;
use App\Models\Document;
use App\Models\DocumentCounter;
use App\Models\DocumentType;
use App\Services\CorrelativeReservationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CorrelativeControlController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user->hasRole(RoleName::SECRETARY->value) && $user->organizationalUnit?->is_active, 403);

        $filters = $request->validate([
            'year' => ['nullable', 'integer', 'min:2000', 'max:'.(now()->year + 1)],
            'document_type' => ['nullable', 'uuid', Rule::exists('tipos_documentos', 'public_id')],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $year = (int) ($filters['year'] ?? now()->year);
        $documentTypes = DocumentType::query()->availableForNewDocuments()
            ->where('uses_correlative', true)
            ->get();
        $selectedType = filled($filters['document_type'] ?? null)
            ? $documentTypes->firstWhere('public_id', $filters['document_type'])
            : $documentTypes->first();

        abort_if(filled($filters['document_type'] ?? null) && $selectedType === null, 422);

        $lastNumber = $selectedType === null ? 0 : (int) (DocumentCounter::query()
            ->where('document_type_id', $selectedType->id)
            ->where('year', $year)
            ->value('last_number') ?? 0);
        $totalNumbers = max(1, $lastNumber + 1);
        $perPage = 120;
        $page = min((int) ($filters['page'] ?? 1), max(1, (int) ceil($totalNumbers / $perPage)));
        $firstNumber = (($page - 1) * $perPage) + 1;
        $lastVisibleNumber = min($totalNumbers, $firstNumber + $perPage - 1);

        $documentsByNumber = $selectedType === null
            ? collect()
            : Document::query()
                ->with(['originUnit', 'correlativeReserver.organizationalUnit'])
                ->where('document_type_id', $selectedType->id)
                ->where('correlative_year', $year)
                ->whereNotNull('correlative_number')
                ->get()
                ->keyBy('correlative_number');

        $numbers = collect(range($firstNumber, $lastVisibleNumber))->map(function (int $number) use ($documentsByNumber, $lastNumber): array {
            /** @var Document|null $document */
            $document = $documentsByNumber->get($number);

            return [
                'number' => $number,
                'used' => $number <= $lastNumber,
                'reservedBy' => $document?->correlativeReserver,
                'reservedAt' => $document?->correlative_reserved_at,
                'sent' => $document === null || $document->sent_at !== null,
            ];
        });

        $pendingReservation = $selectedType === null ? null : Document::query()
            ->where('created_by', $user->getKey())
            ->where('document_type_id', $selectedType->getKey())
            ->whereNotNull('correlative_number')
            ->whereNull('sent_at')
            ->oldest('correlative_reserved_at')
            ->first();

        $paginator = new LengthAwarePaginator($numbers, $totalNumbers, $perPage, $page, [
            'path' => route('correlatives.index'),
            'query' => $request->except('page'),
        ]);

        return view('correlatives.index', [
            'filters' => $filters,
            'year' => $year,
            'documentTypes' => $documentTypes,
            'selectedType' => $selectedType,
            'lastNumber' => $lastNumber,
            'nextNumber' => $lastNumber + 1,
            'numbers' => $paginator,
            'unit' => $user->organizationalUnit,
            'pendingReservation' => $pendingReservation,
        ]);
    }

    public function reserve(Request $request, CorrelativeReservationService $reservations): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasRole(RoleName::SECRETARY->value) && $user->organizationalUnit?->is_active, 403);

        $data = $request->validate([
            'document_type' => ['required', 'uuid', Rule::exists('tipos_documentos', 'public_id')],
        ]);
        $type = DocumentType::query()->where('public_id', $data['document_type'])->firstOrFail();
        $document = $reservations->reserve($user, $type);

        return redirect()->route('documents.show', $document)
            ->with('status', 'Se reservó el cite '.$document->code.'. Complete el borrador antes de solicitar su autorización o enviarlo.');
    }
}
