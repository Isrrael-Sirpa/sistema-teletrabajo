<?php

namespace App\Http\Controllers;

use App\Enums\DocumentAccessPermission;
use App\Enums\DocumentConfidentiality;
use App\Enums\DocumentPriority;
use App\Enums\DocumentRecipientStatus;
use App\Enums\DocumentStatus;
use App\Http\Requests\SearchDocumentsRequest;
use App\Http\Requests\StoreDocumentRequest;
use App\Http\Requests\UpdateDocumentRequest;
use App\Models\Document;
use App\Models\DocumentRecipient;
use App\Models\DocumentReviewTask;
use App\Models\DocumentType;
use App\Models\OrganizationalUnit;
use App\Models\User;
use App\Services\DocumentAccessGrantService;
use App\Services\DocumentDeadlineService;
use App\Services\DocumentDraftService;
use App\Services\DocumentFileService;
use App\Services\DocumentRecipientEligibility;
use App\Services\DocumentReviewService;
use App\Services\DocumentSearchService;
use App\Services\DocumentWorkflowService;
use App\Services\DocumentWorkQueryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class DocumentController extends Controller
{
    public function __construct(
        private readonly DocumentDraftService $drafts,
        private readonly DocumentDeadlineService $deadlines,
        private readonly DocumentRecipientEligibility $recipientEligibility,
        private readonly DocumentFileService $files,
        private readonly DocumentWorkQueryService $workQueries,
        private readonly DocumentAccessGrantService $accessGrants,
        private readonly DocumentSearchService $search,
    ) {}

    public function index(SearchDocumentsRequest $request): View
    {
        Gate::authorize('viewAny', Document::class);

        $now = $this->deadlines->normalizeNow();
        $filters = $request->safe()->except('page');
        $workFilter = (string) ($filters['work'] ?? '');
        $workFilterLabel = $this->workQueries->label($workFilter);
        $documents = $this->search->query($request->user(), $filters)
            ->paginate(max(5, min(100, (int) config('documents.search.per_page', 15))))
            ->withQueryString();
        $deadlineStatuses = $documents->getCollection()->mapWithKeys(
            fn (Document $document): array => [
                $document->getKey() => $this->deadlines->status($document, $now),
            ],
        );

        $actor = $request->user();
        $visibleDocuments = fn () => Document::query()->visibleTo($actor);
        $units = OrganizationalUnit::query()
            ->where(function ($query) use ($visibleDocuments): void {
                $query->whereIn('id', $visibleDocuments()->select('origin_unit_id'))
                    ->orWhereIn('id', DocumentRecipient::query()
                        ->select('unit_id')
                        ->whereIn('document_id', $visibleDocuments()->select((new Document)->qualifyColumn('id'))));
            })
            ->orderBy('name')->get();
        $people = User::query()->with('organizationalUnit')
            ->where(function ($query) use ($visibleDocuments): void {
                $query->whereIn('id', $visibleDocuments()->select('created_by'))
                    ->orWhereIn('id', $visibleDocuments()->whereNotNull('sender_user_id')->select('sender_user_id'))
                    ->orWhereIn('id', DocumentRecipient::query()
                        ->whereNotNull('user_id')
                        ->select('user_id')
                        ->whereIn('document_id', $visibleDocuments()->select((new Document)->qualifyColumn('id'))));
            })
            ->orderBy('name')->get();
        $documentTypes = DocumentType::query()
            ->whereIn('id', $visibleDocuments()->select('document_type_id'))
            ->orderBy('name')->get();

        return view('documents.index', compact(
            'documents',
            'filters',
            'workFilter',
            'workFilterLabel',
            'deadlineStatuses',
            'units',
            'people',
            'documentTypes',
        ));
    }

    public function show(Request $request, Document $document, DocumentReviewService $reviews, DocumentWorkflowService $workflow): View
    {
        Gate::authorize('view', $document);
        $actor = $request->user();
        $taskOnly = DocumentReviewTask::canBeAssignedTo($actor)
            && ! Document::query()->whereKey($document->getKey())->visibleTo($actor, includeTasks: false)->exists();
        $document->load([
            'documentType',
            'originUnit',
            'creator',
            'authorizationRequester',
            'authorizer',
            'annuller',
            'recipients' => fn ($recipients) => $recipients->when(
                $taskOnly,
                fn ($destinations) => $destinations->where('unit_id', $actor->organizational_unit_id),
            ),
            'recipients.unit',
            'recipients.user.roles',
            'recipients.receiver',
            'recipients.currentHandler',
            'recipients.decisionMaker',
            'versions' => fn ($versions) => $versions->when(
                $taskOnly,
                fn ($query) => $query->whereIn('id', $document->reviewVersionIdsFor($actor)),
            ),
            'versions.uploader',
            'versions.files.uploader',
        ]);

        $canManageRecipients = Gate::allows('manageRecipients', $document);
        $canManageFiles = Gate::allows('manageFiles', $document);
        $canManageAccessGrants = Gate::allows('manageAccessGrants', $document);
        $deadlineStatus = $this->deadlines->status($document);
        $pendingCorrectionVersion = $taskOnly ? null : $this->files->pendingCorrectionVersion($document);
        $currentVersionNumber = (int) ($document->versions->max('version_number') ?? 0);
        $returnedRecipients = $document->recipients
            ->where('requires_response', true)
            ->where('status', DocumentRecipientStatus::RETURNED);
        $recipientUnits = collect();
        $recipientUsers = collect();
        $activeAccessGrants = collect();
        $eligibleAccessUsers = collect();
        $accessPermissions = DocumentAccessPermission::cases();
        $isUnitResponsible = $actor->isResponsibleForUnit((int) $actor->organizational_unit_id);
        $assignableDestinations = $document->recipients->filter(
            fn (DocumentRecipient $recipient): bool => Gate::allows('assignReview', [$document, $recipient]),
        );
        $reviewTeam = $assignableDestinations->isEmpty() ? collect() : $reviews->team($actor->organizational_unit_id);
        $reviewTasks = DocumentReviewTask::query()
            ->whereRelation('recipient', 'document_id', $document->getKey())
            ->when(
                $isUnitResponsible,
                fn ($tasks) => $tasks->whereRelation('recipient', 'unit_id', $actor->organizational_unit_id),
                fn ($tasks) => $tasks->assignedTo($actor),
            )
            ->with(['recipient.unit', 'assignee', 'assigner', 'version'])
            ->latest('id')
            ->get();

        if ($canManageRecipients) {
            $recipientUnits = OrganizationalUnit::query()
                ->availableAsDocumentDestination()
                ->orderBy('type')
                ->orderBy('name')
                ->get();

            $recipientUsers = $this->recipientEligibility->personalCandidates()
                ->with(['organizationalUnit', 'roles'])
                ->orderBy('name')
                ->get();
        }

        if ($canManageAccessGrants) {
            $activeAccessGrants = $document->accessGrants()
                ->effective()
                ->with(['user.organizationalUnit', 'user.roles', 'grantor'])
                ->orderBy('ends_at')
                ->orderBy('created_at')
                ->get();
            $eligibleAccessUsers = $this->accessGrants
                ->eligibleExtras($request->user())
                ->whereNotIn('id', $activeAccessGrants->pluck('user_id'))
                ->values();
        }

        $history = $document->events()
            ->when($taskOnly, fn ($events) => $events->whereIn('document_recipient_id', $reviewTasks->pluck('document_recipient_id')))
            ->with(['actor', 'recipient.unit', 'recipient.user'])
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->paginate(
                max(5, min(100, (int) config('documents.history.per_page', 15))),
                ['*'],
                'historial',
            )
            ->withQueryString();
        $correlativePreview = $document->general_status === DocumentStatus::DRAFT
            ? $workflow->previewCode($document)
            : null;

        return view('documents.show', compact(
            'document',
            'deadlineStatus',
            'canManageRecipients',
            'canManageFiles',
            'canManageAccessGrants',
            'pendingCorrectionVersion',
            'currentVersionNumber',
            'returnedRecipients',
            'recipientUnits',
            'recipientUsers',
            'activeAccessGrants',
            'eligibleAccessUsers',
            'accessPermissions',
            'assignableDestinations',
            'reviewTeam',
            'reviewTasks',
            'isUnitResponsible',
            'taskOnly',
            'history',
            'correlativePreview',
        ));
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', Document::class);

        return view('documents.create', $this->formData($request));
    }

    public function store(StoreDocumentRequest $request): RedirectResponse
    {
        $document = $this->drafts->create($request->user(), $request->validated());

        return redirect()->route('documents.show', $document)
            ->with('status', 'Borrador guardado correctamente.');
    }

    public function edit(Request $request, Document $document): View
    {
        Gate::authorize('update', $document);

        return view('documents.edit', [
            ...$this->formData($request),
            'document' => $document,
        ]);
    }

    public function update(UpdateDocumentRequest $request, Document $document): RedirectResponse
    {
        $document = $this->drafts->update($request->user(), $document, $request->validated());

        return redirect()->route('documents.show', $document)
            ->with('status', 'El borrador fue actualizado correctamente.');
    }

    public function discard(Request $request, Document $document): RedirectResponse
    {
        Gate::authorize('discardDraft', $document);
        $this->drafts->discard($request->user(), $document);

        return redirect()->route('documents.index')
            ->with('status', 'El borrador fue descartado correctamente.');
    }

    /** @return array<string, mixed> */
    private function formData(Request $request): array
    {
        $user = $request->user();
        $canSelectOrigin = $user->hasInstitutionalGlobalScope();

        return [
            'documentTypes' => DocumentType::query()->availableForNewDocuments()->get(),
            'selectedDocumentTypeId' => DocumentType::query()->availableForNewDocuments()
                ->where('public_id', $request->query('document_type'))
                ->value('id'),
            'originUnits' => $canSelectOrigin
                ? OrganizationalUnit::query()->active()->orderBy('type')->orderBy('name')->get()
                : collect(),
            'fixedOrigin' => $canSelectOrigin ? null : $user->organizationalUnit,
            'canSelectOrigin' => $canSelectOrigin,
            'priorities' => DocumentPriority::cases(),
            'confidentialities' => DocumentConfidentiality::cases(),
        ];
    }
}
