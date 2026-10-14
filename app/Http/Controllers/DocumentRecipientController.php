<?php

namespace App\Http\Controllers;

use App\Http\Requests\DestroyDocumentRecipientRequest;
use App\Http\Requests\StoreDocumentRecipientRequest;
use App\Http\Requests\UpdateDocumentRecipientRequest;
use App\Models\Document;
use App\Services\DocumentRecipientService;
use Illuminate\Http\RedirectResponse;

class DocumentRecipientController extends Controller
{
    public function __construct(private readonly DocumentRecipientService $recipients) {}

    public function store(StoreDocumentRecipientRequest $request, Document $document): RedirectResponse
    {
        $this->recipients->add($request->user(), $document, $request->validated());

        return back()->with('status', 'Destinatario agregado correctamente.');
    }

    public function update(UpdateDocumentRecipientRequest $request, Document $document): RedirectResponse
    {
        $this->recipients->update($request->user(), $document, $request->validated());

        return back()->with('status', 'La clasificación del destinatario fue actualizada.');
    }

    public function destroy(DestroyDocumentRecipientRequest $request, Document $document): RedirectResponse
    {
        $this->recipients->remove($request->user(), $document, $request->validated());

        return back()->with('status', 'Destinatario quitado correctamente.');
    }
}
