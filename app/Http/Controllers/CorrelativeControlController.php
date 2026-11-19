<?php

namespace App\Http\Controllers;

use App\Enums\RoleName;
use App\Models\Document;
use App\Models\DocumentCounter;
use App\Models\DocumentType;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
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
        $documentTypes = DocumentType::query()->availableForNewDocuments()->get();
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
                ->with(['documentType', 'originUnit'])
                ->where('document_type_id', $selectedType->id)
                ->whereNotNull('code')
                ->whereYear('sent_at', $year)
                ->get()
                ->mapWithKeys(function (Document $document) use ($year): array {
                    preg_match('/N°\s*(\d+)\/'.$year.'$/u', (string) $document->code, $matches);

                    return isset($matches[1]) ? [(int) $matches[1] => $document] : [];
                });

        $numbers = collect(range($firstNumber, $lastVisibleNumber))->map(function (int $number) use (
            $documentsByNumber,
            $lastNumber,
            $user,
        ): array {
            /** @var Document|null $document */
            $document = $documentsByNumber->get($number);
            $used = $number <= $lastNumber;
            $canView = $document !== null && Gate::forUser($user)->allows('view', $document);

            return [
                'number' => $number,
                'used' => $used,
                'document' => $canView ? $document : null,
            ];
        });

        $paginator = new LengthAwarePaginator(
            $numbers,
            $totalNumbers,
            $perPage,
            $page,
            ['path' => route('correlatives.index'), 'query' => $request->except('page')],
        );

        return view('correlatives.index', [
            'filters' => $filters,
            'year' => $year,
            'documentTypes' => $documentTypes,
            'selectedType' => $selectedType,
            'lastNumber' => $lastNumber,
            'nextNumber' => $lastNumber + 1,
            'numbers' => $paginator,
            'unit' => $user->organizationalUnit,
        ]);
    }
}
