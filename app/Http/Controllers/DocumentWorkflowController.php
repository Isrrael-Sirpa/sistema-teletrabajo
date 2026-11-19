<?php

namespace App\Http\Controllers;

use App\Enums\DocumentTransition;
use App\Http\Requests\AnnulDocumentRequest;
use App\Http\Requests\SendCorrectionRequest;
use App\Http\Requests\SendDocumentRequest;
use App\Http\Requests\TransitionDocumentRecipientRequest;
use App\Models\Document;
use App\Models\DocumentRecipient;
use App\Services\DocumentWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DocumentWorkflowController extends Controller
{
    public function __construct(private readonly DocumentWorkflowService $workflow) {}

    public function send(SendDocumentRequest $request, Document $document): RedirectResponse
    {
        $document = $this->workflow->send(
            $request->user(),
            $document,
            $request->integer('document_lock_version'),
        );

        return redirect()->route('documents.show', $document)
            ->with('status', "Documento enviado oficialmente con el código {$document->code}.");
    }

    public function requestAuthorization(Request $request, Document $document): RedirectResponse
    {
        $data = $request->validate(['document_lock_version' => ['required', 'integer', 'min:0']]);
        $this->workflow->requestAuthorization($request->user(), $document, (int) $data['document_lock_version']);

        return back()->with('status', 'La solicitud fue enviada a la autoridad de la unidad.');
    }

    public function decideAuthorization(Request $request, Document $document, string $decision): RedirectResponse
    {
        $data = $request->validate([
            'document_lock_version' => ['required', 'integer', 'min:0'],
            'authorization_note' => ['nullable', 'string', 'max:2000'],
        ]);
        $this->workflow->decideAuthorization(
            $request->user(),
            $document,
            (int) $data['document_lock_version'],
            $decision === 'approve',
            $data['authorization_note'] ?? null,
        );

        return back()->with('status', $decision === 'approve'
            ? 'El documento quedó autorizado para su envío.'
            : 'El borrador fue devuelto a Secretaría para corrección.');
    }

    public function transition(
        TransitionDocumentRecipientRequest $request,
        Document $document,
        DocumentRecipient $documentRecipient,
        string $transition,
    ): RedirectResponse {
        $action = DocumentTransition::from($transition);
        $this->workflow->transition(
            $request->user(),
            $document,
            $documentRecipient,
            $action,
            $request->integer('document_lock_version'),
            $request->integer('recipient_lock_version'),
            $request->string('reason')->toString() ?: null,
        );

        return back()->with('status', $action->label().' registrado correctamente.');
    }

    public function sendCorrection(SendCorrectionRequest $request, Document $document): RedirectResponse
    {
        $document = $this->workflow->sendCorrection(
            $request->user(),
            $document,
            $request->integer('document_lock_version'),
        );

        return redirect()->route('documents.show', $document)
            ->with('status', 'La corrección fue enviada a los destinatarios que habían devuelto el documento.');
    }

    public function annul(AnnulDocumentRequest $request, Document $document): RedirectResponse
    {
        $document = $this->workflow->annul(
            $request->user(),
            $document,
            $request->integer('document_lock_version'),
            $request->string('reason')->toString(),
        );

        return redirect()->route('documents.show', $document)
            ->with('status', 'El documento fue anulado y permanece conservado como expediente histórico.');
    }
}
