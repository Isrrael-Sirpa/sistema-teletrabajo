<?php

namespace App\Services;

use App\Enums\DocumentConfidentiality;
use App\Enums\DocumentEventType;
use App\Enums\DocumentPriority;
use App\Enums\DocumentRecipientStatus;
use App\Enums\DocumentStatus;
use App\Enums\OrganizationalUnitType;
use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\Document;
use App\Models\DocumentFile;
use App\Models\DocumentReviewTask;
use App\Models\OrganizationalUnit;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

class DashboardService
{
    public function __construct(
        private readonly DocumentWorkQueryService $workQueries,
        private readonly DocumentDeadlineService $deadlines,
        private readonly DelegatedAuthorizationService $delegations,
        private readonly DocumentReviewService $reviews,
    ) {}

    /** @return array<string, mixed> */
    public function for(User $user): array
    {
        $user->loadMissing(['roles', 'organizationalUnit']);
        $role = $user->roleName();
        $unreadCount = $user->unreadNotifications()->count();
        $base = [
            'role' => $role,
            'roleLabel' => $role?->label() ?? 'Usuario',
            'scopeLabel' => $role?->isGlobal() === true
                ? 'Ámbito institucional'
                : ($user->organizationalUnit?->name ?? 'Sin unidad asignada'),
            'unreadNotificationCount' => $unreadCount,
            'temporaryHeadships' => $this->delegations->activeFor($user),
        ];

        if ($user->isResponsibleForUnit((int) $user->organizational_unit_id)) {
            $corrections = $this->workQueries->visibleTo($user);
            $this->workQueries->apply($corrections, $user, 'returned_correction');

            return $base + [
                'unitDashboard' => true,
                'unitPending' => $this->unitPending($user),
                'teamTasks' => $this->teamTasks($user->organizational_unit_id),
                'hasReturnedCorrections' => $corrections->exists(),
            ];
        }

        return $base + [
            'unitDashboard' => false,
            'cards' => match ($role) {
                RoleName::ADMINISTRATOR, RoleName::CO_ADMINISTRATOR => $this->globalCards($unreadCount),
                RoleName::SECRETARY => $this->secretaryCards($user, $unreadCount),
                RoleName::SECRETARY_ASSISTANT => $this->assistantCards($user, $unreadCount),
                RoleName::EXTRA => $this->extraCards($user, $unreadCount),
                default => [$this->notificationCard($unreadCount)],
            },
            'quickLinks' => $this->quickLinks($user, $role),
            'attentionTitle' => $role === RoleName::EXTRA
                ? 'Documentos autorizados recientes'
                : 'Necesitan su atención',
            'attention' => $this->attention($user, $role),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function globalCards(int $unreadCount): array
    {
        $now = $this->deadlines->normalizeNow();
        $cutoff = $this->deadlines->dueSoonCutoff($now);
        $counts = Document::query()->selectRaw(
            'SUM(CASE WHEN general_status = ? THEN 1 ELSE 0 END) AS sent_count,
             SUM(CASE WHEN general_status = ? THEN 1 ELSE 0 END) AS review_count,
             SUM(CASE WHEN general_status = ? THEN 1 ELSE 0 END) AS returned_count,
             SUM(CASE WHEN due_at IS NOT NULL AND due_at <= ? AND general_status IN (?, ?, ?, ?) THEN 1 ELSE 0 END) AS overdue_count,
             SUM(CASE WHEN due_at > ? AND due_at <= ? AND general_status IN (?, ?, ?, ?) THEN 1 ELSE 0 END) AS due_soon_count',
            [
                DocumentStatus::SENT->value,
                DocumentStatus::IN_REVIEW->value,
                DocumentStatus::RETURNED->value,
                $now,
                ...DocumentStatus::deadlineActionableValues(),
                $now,
                $cutoff,
                ...DocumentStatus::deadlineActionableValues(),
            ],
        )->first();

        return [
            $this->card('Documentos enviados pendientes', (int) $counts->sent_count, route('documents.index', ['status' => 'sent']), 'Esperan recepción o trámite.'),
            $this->card('Documentos en revisión', (int) $counts->review_count, route('documents.index', ['status' => 'in_review']), 'Revisión institucional activa.'),
            $this->card('Documentos devueltos', (int) $counts->returned_count, route('documents.index', ['status' => 'returned']), 'Requieren corrección del origen.'),
            $this->card('Vencidos', (int) $counts->overdue_count, route('documents.index', ['deadline' => 'overdue']), 'Atención inmediata.', 'danger'),
            $this->card('Próximos a vencer', (int) $counts->due_soon_count, route('documents.index', ['deadline' => 'due_soon']), 'Dentro de los próximos '.$this->deadlines->dueSoonDays().' días.', 'warning'),
            $this->notificationCard($unreadCount),
            $this->card('Usuarios activos', User::query()->where('status', UserStatus::ACTIVE->value)->count(), route('users.index', ['status' => 'active']), 'Cuentas institucionales habilitadas.'),
            $this->card('Carreras activas', OrganizationalUnit::query()->careers()->active()->count(), route('units.index', ['type' => OrganizationalUnitType::CAREER->value, 'status' => 'active']), 'Carreras disponibles.'),
        ];
    }

    /** @return Collection<int, array<string, mixed>> */
    private function unitPending(User $user): Collection
    {
        $now = $this->deadlines->normalizeNow();
        $query = $this->workQueries->visibleTo($user)->with([
            'originUnit:id,name',
            'recipients' => function ($recipients) use ($user): void {
                $this->workQueries->pendingUnitRecipients($recipients->getQuery(), $user);
                $recipients->orderByDesc('requires_response')
                    ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [DocumentRecipientStatus::SENT->value])
                    ->orderBy('id');
            },
        ]);
        $this->workQueries->apply($query, $user, 'unit_pending');
        $this->orderByAttention($query, $now, pendingByDestination: true);
        $hasTeam = $this->reviews->team($user->organizational_unit_id)->isNotEmpty();
        $gate = Gate::forUser($user);

        return $query->limit(5)->get()->toBase()->map(function (Document $document) use ($hasTeam, $now, $gate): ?array {
            $recipient = $document->recipients->first();
            if (! $recipient) {
                return null;
            }

            $versionId = $this->reviews->deliveredVersionId($document, $recipient);
            $tasks = $recipient->reviewTasks()->where('document_version_id', $versionId)->whereNull('closed_at')
                ->selectRaw('COUNT(CASE WHEN submitted_at IS NULL THEN 1 END) AS pending, COUNT(submitted_at) AS delivered')
                ->first();
            $destinationAnchor = 'destino-'.$recipient->id;
            [$step, $label, $anchor] = match (true) {
                $gate->allows('receiveRecipient', [$document, $recipient]) => [
                    'Por recibir', $recipient->requires_response ? 'Recibir documento' : 'Recibir copia', $destinationAnchor,
                ],
                (int) $tasks->pending > 0 => ['En revisión del equipo', 'Ver revisiones', 'revisiones'],
                (int) $tasks->delivered > 0 => ['Por decidir', 'Ver revisiones', 'revisiones'],
                $recipient->status === DocumentRecipientStatus::IN_REVIEW => ['Por decidir', 'Revisar decisión', $destinationAnchor],
                $hasTeam && $gate->allows('assignReview', [$document, $recipient]) => [
                    'Por asignar', 'Asignar revisión', 'asignar-revision-'.$recipient->id,
                ],
                default => ['Por revisar', 'Revisar documento', $destinationAnchor],
            };

            return [
                'document' => $document,
                'step' => $step,
                'actionLabel' => $label,
                'url' => route('documents.show', $document).'#'.$anchor,
                'deadlineStatus' => $this->deadlines->statusForPendingWork($document, $now),
                'attachmentCount' => $versionId && $gate->allows('downloadFile', $document)
                    ? DocumentFile::query()->where('document_version_id', $versionId)->count()
                    : null,
            ];
        })->filter()->values();
    }

    /** @return array{pending: int, delivered: int, people: Collection, recent: Collection} */
    private function teamTasks(int $unitId): array
    {
        $tasks = DocumentReviewTask::query()->forUnit($unitId)->whereNull('closed_at');
        $counts = 'COUNT(CASE WHEN submitted_at IS NULL THEN 1 END) AS pending, COUNT(submitted_at) AS delivered';
        $totals = (clone $tasks)->selectRaw($counts)->first();
        $people = (clone $tasks)->select('assigned_to')->selectRaw($counts)
            ->with('assignee:id,name')->groupBy('assigned_to')
            ->orderByDesc('pending')->orderByDesc('delivered')->orderBy('assigned_to')->limit(4)->get();

        $recent = $tasks->with(['assignee:id,name', 'recipient.document:id,public_id,subject']);
        $pending = (clone $recent)->pending()->latest('id')->limit(2)->get();
        $delivered = $recent->whereNotNull('submitted_at')->latest('submitted_at')->orderByDesc('id')->limit(2)->get();

        return [
            'pending' => (int) $totals->pending,
            'delivered' => (int) $totals->delivered,
            'people' => $people,
            'recent' => $pending->concat($delivered)->sortByDesc(fn (DocumentReviewTask $task) => $task->submitted_at ?? $task->created_at),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function secretaryCards(User $user, int $unreadCount): array
    {
        return [
            $this->taskCard($user),
            $this->workCard($user, 'Copias y envíos por recibir', 'pending_receive', 'Copias de su unidad y envíos personales pendientes de recepción.'),
            $this->workCard($user, 'Mis borradores', 'my_drafts', 'Documentos propios aún no enviados.'),
            $this->workCard($user, 'Devueltos para corregir', 'returned_correction', 'Documentos propios pendientes de reenvío.'),
            ...$this->deadlineCards($user),
            $this->notificationCard($unreadCount),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function assistantCards(User $user, int $unreadCount): array
    {
        return [
            $this->taskCard($user),
            ...$this->deadlineCards($user),
            $this->notificationCard($unreadCount),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function extraCards(User $user, int $unreadCount): array
    {
        return [
            $this->workCard($user, 'Documentos autorizados', 'authorized', 'Accesos explícitos vigentes.'),
            $this->notificationCard($unreadCount),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function deadlineCards(User $user): array
    {
        $now = $this->deadlines->normalizeNow();

        return [
            $this->card(
                'Próximos a vencer',
                (clone $this->workQueries->visibleTo($user))->dueSoon($now, $this->deadlines->dueSoonCutoff($now))->count(),
                route('documents.index', ['deadline' => 'due_soon']),
                'Dentro de los próximos '.$this->deadlines->dueSoonDays().' días.',
                'warning',
            ),
            $this->card(
                'Vencidos',
                (clone $this->workQueries->visibleTo($user))->overdue($now)->count(),
                route('documents.index', ['deadline' => 'overdue']),
                'Documentos visibles fuera de plazo.',
                'danger',
            ),
        ];
    }

    private function workCard(User $user, string $label, string $work, string $description): array
    {
        $query = $this->workQueries->visibleTo($user);
        $this->workQueries->apply($query, $user, $work);

        return $this->card($label, $query->count(), route('documents.index', ['work' => $work]), $description);
    }

    private function notificationCard(int $count): array
    {
        return $this->card('Notificaciones sin leer', $count, route('notifications.index'), 'Avisos pendientes de lectura.');
    }

    private function taskCard(User $user): array
    {
        $count = DocumentReviewTask::query()->assignedTo($user)
            ->whereHas('recipient.document')->pending()->count();

        return $this->card('Mis tareas', $count, route('reviews.mine'), 'Revisiones internas pendientes de entregar.');
    }

    /** @return Collection<int, array<string, mixed>> */
    private function attention(User $user, ?RoleName $role): Collection
    {
        $now = $this->deadlines->normalizeNow();
        $query = $this->workQueries->visibleTo($user)
            ->with(['documentType:id,name', 'originUnit:id,name'])
            ->withMax([
                'events as last_correction_version_event_id' => fn (Builder $events) => $events
                    ->where('event_type', DocumentEventType::DOCUMENT_VERSION_CREATED->value),
            ], 'id')
            ->withMax([
                'events as last_correction_sent_event_id' => fn (Builder $events) => $events
                    ->where('event_type', DocumentEventType::CORRECTION_SENT->value),
            ], 'id');

        $this->workQueries->attention($query, $user);

        if ($role === RoleName::EXTRA) {
            $query->latest('documents.updated_at');
        } else {
            $this->orderByAttention($query, $now);
        }

        return $query->limit(5)->get()->map(function (Document $document) use ($user, $now): array {
            $lastVersion = (int) ($document->last_correction_version_event_id ?? 0);
            $lastSent = (int) ($document->last_correction_sent_event_id ?? 0);

            return [
                'document' => $document,
                'subject' => $user->hasInstitutionalGlobalScope()
                    && $document->confidentiality === DocumentConfidentiality::RESTRICTED
                        ? 'Documento de acceso restringido'
                        : $document->subject,
                'deadlineStatus' => $this->deadlines->status($document, $now),
                'correctionPrepared' => $document->general_status === DocumentStatus::RETURNED
                    && $lastVersion > $lastSent,
            ];
        });
    }

    /** @param Builder<Document> $query */
    private function orderByAttention(Builder $query, CarbonInterface $now, bool $pendingByDestination = false): void
    {
        // A destination can still have work after another destination has made a decision.
        $deadlineFilter = $pendingByDestination ? '' : ' AND general_status IN (?, ?, ?, ?)';
        $deadlineStatuses = $pendingByDestination ? [] : DocumentStatus::deadlineActionableValues();

        $query->orderByRaw(
            "CASE
                WHEN due_at <= ?{$deadlineFilter} THEN 0
                WHEN priority = ? THEN 1
                WHEN due_at > ? AND due_at <= ?{$deadlineFilter} THEN 2
                WHEN priority = ? THEN 3
                ELSE 4
            END",
            [
                $now,
                ...$deadlineStatuses,
                DocumentPriority::URGENT->value,
                $now,
                $this->deadlines->dueSoonCutoff($now),
                ...$deadlineStatuses,
                DocumentPriority::IMPORTANT->value,
            ],
        )->orderBy('documents.created_at')->orderBy('documents.id');
    }

    /** @return array<int, array{label: string, url: string}> */
    private function quickLinks(User $user, ?RoleName $role): array
    {
        $links = [];

        if ($user->can('documents.view') || $this->delegations->hasAny($user, PermissionName::DOCUMENTS_VIEW)) {
            $links[] = ['label' => $role === RoleName::EXTRA ? 'Ver documentos autorizados' : 'Ver documentos', 'url' => route('documents.index')];
        }
        if ($user->can('documents.create') || $this->delegations->hasAny($user, PermissionName::DOCUMENTS_CREATE)) {
            $links[] = ['label' => 'Crear borrador', 'url' => route('documents.create')];
        }
        if ($user->can('users.view') || $this->delegations->hasAny($user, PermissionName::USERS_VIEW)) {
            $links[] = ['label' => 'Gestionar usuarios', 'url' => route('users.index')];
        }
        if ($user->can('units.view')) {
            $links[] = ['label' => 'Ver carreras y unidades', 'url' => route('units.index')];
        }
        if ($user->can('document_types.view')) {
            $links[] = ['label' => 'Ver tipos documentales', 'url' => route('document-types.index')];
        }
        $links[] = ['label' => 'Abrir notificaciones', 'url' => route('notifications.index')];

        return $links;
    }

    /** @return array{label: string, count: int, url: string, description: string, tone: string} */
    private function card(string $label, int $count, string $url, string $description, string $tone = 'neutral'): array
    {
        return compact('label', 'count', 'url', 'description', 'tone');
    }
}
