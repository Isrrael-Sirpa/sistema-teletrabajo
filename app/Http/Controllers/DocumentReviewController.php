<?php

namespace App\Http\Controllers;

use App\Enums\ReviewRecommendation;
use App\Models\Document;
use App\Models\DocumentRecipient;
use App\Models\DocumentReviewTask;
use App\Services\DocumentReviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DocumentReviewController extends Controller
{
    public function index(Request $request): View
    {
        $actor = $request->user();
        $unitBoard = $request->routeIs('reviews.unit');
        $query = DocumentReviewTask::query()
            ->with(['recipient.document', 'recipient.unit', 'assignee', 'assigner']);

        if ($unitBoard) {
            abort_unless($actor->isResponsibleForUnit((int) $actor->organizational_unit_id), 403);
            $query->forUnit($actor->organizational_unit_id);
        } else {
            abort_unless(DocumentReviewTask::canBeAssignedTo($actor), 403);
            $query->whereHas('recipient.document')->assignedTo($actor);
        }

        $lanes = [
            'Pendientes' => (clone $query)->pending()
                ->oldest('id')->paginate(10, ['*'], 'pendientes')->withQueryString(),
            'Entregadas' => (clone $query)->whereNotNull('submitted_at')
                ->latest('submitted_at')->orderByDesc('id')->paginate(10, ['*'], 'entregadas')->withQueryString(),
            'Cerradas' => $query->whereNotNull('closed_at')
                ->latest('closed_at')->orderByDesc('id')->paginate(10, ['*'], 'cerradas')->withQueryString(),
        ];

        return view('documents.reviews.index', compact('unitBoard', 'lanes'));
    }

    public function store(Request $request, Document $document, DocumentRecipient $documentRecipient, DocumentReviewService $reviews): RedirectResponse
    {
        Gate::authorize('assignReview', [$document, $documentRecipient]);
        $data = $request->validate([
            'assigned_to' => ['required', 'integer', 'min:1'],
            'instructions' => ['required', 'string', 'max:2000'],
            'assignment_key' => ['required', 'uuid'],
            'recipient_lock_version' => ['required', 'integer', 'min:0'],
        ], [
            'assigned_to.required' => 'Seleccione a quién asignar la revisión.',
            'instructions.required' => 'Indique qué necesita que revise la persona asignada.',
            'instructions.max' => 'Las indicaciones pueden tener hasta 2000 caracteres.',
        ]);

        $reviews->assign($request->user(), $document, $documentRecipient, $data);

        return redirect()->route('documents.show', $document)->withFragment('revisiones')
            ->with('status', 'Revisión asignada. La persona podrá verla en «Mis tareas».');
    }

    public function update(Request $request, Document $document, DocumentReviewTask $reviewTask, DocumentReviewService $reviews): RedirectResponse
    {
        $recipient = $reviewTask->recipient;
        abort_unless($recipient?->document_id === $document->getKey(), 404);
        Gate::authorize('submitReview', [$document, $recipient, $reviewTask]);
        $data = $request->validate([
            'recommendation' => ['required', Rule::enum(ReviewRecommendation::class)],
            'message' => ['required', 'string', 'max:2000'],
        ], [
            'recommendation.required' => 'Seleccione una recomendación.',
            'recommendation.enum' => 'Seleccione una recomendación válida.',
            'message.required' => 'Explique su recomendación en un mensaje.',
            'message.max' => 'El mensaje puede tener hasta 2000 caracteres.',
        ]);

        $reviews->submit($request->user(), $document, $reviewTask, ReviewRecommendation::from($data['recommendation']), $data['message']);

        return redirect()->route('documents.show', $document)->withFragment('revisiones')
            ->with('status', 'Revisión entregada al responsable de la unidad.');
    }
}
