<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDocumentTypeRequest;
use App\Http\Requests\UpdateDocumentTypeRequest;
use App\Models\DocumentType;
use App\Services\DocumentTypeService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DocumentTypeController extends Controller
{
    public function __construct(private readonly DocumentTypeService $documentTypes) {}

    public function index(): View
    {
        Gate::authorize('viewAny', DocumentType::class);

        $documentTypes = DocumentType::query()->orderBy('name')->paginate(15);

        return view('document-types.index', compact('documentTypes'));
    }

    public function show(DocumentType $documentType): View
    {
        Gate::authorize('view', $documentType);

        return view('document-types.show', compact('documentType'));
    }

    public function create(): View
    {
        Gate::authorize('create', DocumentType::class);

        return view('document-types.create');
    }

    public function store(StoreDocumentTypeRequest $request): RedirectResponse
    {
        try {
            $documentType = $this->documentTypes->create($request->user(), $request->validated());
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'name' => 'El nombre o código interno ya pertenece a otro tipo documental.',
            ]);
        }

        return redirect()->route('document-types.show', $documentType)
            ->with('status', 'El tipo documental fue creado correctamente.');
    }

    public function edit(DocumentType $documentType): View
    {
        Gate::authorize('update', $documentType);

        return view('document-types.edit', compact('documentType'));
    }

    public function update(UpdateDocumentTypeRequest $request, DocumentType $documentType): RedirectResponse
    {
        try {
            $this->documentTypes->update($request->user(), $documentType, $request->validated());
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'name' => 'Ya existe otro tipo documental con este nombre.',
            ]);
        }

        return redirect()->route('document-types.show', $documentType)
            ->with('status', 'El tipo documental fue actualizado correctamente.');
    }

    public function activate(Request $request, DocumentType $documentType): RedirectResponse
    {
        Gate::authorize('activate', $documentType);
        $this->documentTypes->setActive($request->user(), $documentType, true);

        return back()->with('status', 'El tipo documental fue activado correctamente.');
    }

    public function deactivate(Request $request, DocumentType $documentType): RedirectResponse
    {
        Gate::authorize('deactivate', $documentType);
        $this->documentTypes->setActive($request->user(), $documentType, false);

        return back()->with('status', 'El tipo documental fue desactivado sin eliminar información histórica.');
    }
}
