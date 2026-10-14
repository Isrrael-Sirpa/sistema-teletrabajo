<?php

namespace App\Http\Controllers;

use App\Enums\DocumentAccessPermission;
use App\Http\Requests\RevokeDocumentAccessGrantRequest;
use App\Http\Requests\StoreDocumentAccessGrantRequest;
use App\Http\Requests\UpdateDocumentAccessGrantRequest;
use App\Models\Document;
use App\Models\DocumentAccessGrant;
use App\Services\DocumentAccessGrantService;
use Illuminate\Http\RedirectResponse;

class DocumentAccessGrantController extends Controller
{
    public function __construct(private readonly DocumentAccessGrantService $grants) {}

    public function store(StoreDocumentAccessGrantRequest $request, Document $document): RedirectResponse
    {
        $this->grants->grant($request->user(), $document, $request->validated());

        return redirect()->route('documents.show', $document)
            ->with('status', 'El acceso explícito fue concedido correctamente.');
    }

    public function update(
        UpdateDocumentAccessGrantRequest $request,
        Document $document,
        DocumentAccessGrant $documentAccessGrant,
    ): RedirectResponse {
        $this->grants->changeLevel(
            $request->user(),
            $document,
            $documentAccessGrant,
            DocumentAccessPermission::from($request->validated('permission')),
        );

        return redirect()->route('documents.show', $document)
            ->with('status', 'El nivel de acceso fue actualizado correctamente.');
    }

    public function destroy(
        RevokeDocumentAccessGrantRequest $request,
        Document $document,
        DocumentAccessGrant $documentAccessGrant,
    ): RedirectResponse {
        $this->grants->revoke(
            $request->user(),
            $document,
            $documentAccessGrant,
            $request->validated('revocation_reason'),
        );

        return redirect()->route('documents.show', $document)
            ->with('status', 'El acceso explícito fue revocado correctamente.');
    }
}
