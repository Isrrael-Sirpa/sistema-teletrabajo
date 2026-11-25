<?php

namespace App\Http\Controllers;

use App\Enums\ActivityPriority;
use App\Enums\ActivityStatus;
use App\Enums\ActivityType;
use App\Models\Document;
use App\Models\OrganizationalUnit;
use App\Models\User;
use App\Models\WorkActivity;
use App\Services\WorkActivityService;
use App\Services\WorkActivityFileService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class WorkActivityController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', WorkActivity::class);
        $actor = $request->user();
        $isResponsible = $actor->isResponsibleForUnit((int) $actor->organizational_unit_id);
        $availableUnits = collect();
        $selectedUnitId = null;
        $adminSection = null;
        $pageTitle = 'Tareas asignadas';
        $query = WorkActivity::query()->visibleTo($actor)
            ->with(['organizationalUnit', 'document', 'assignees'])
            ->orderByRaw("CASE status WHEN 'observed' THEN 0 WHEN 'submitted' THEN 1 WHEN 'assigned' THEN 2 WHEN 'in_progress' THEN 3 ELSE 4 END")
            ->orderByRaw('CASE WHEN due_at IS NULL THEN 1 ELSE 0 END')->orderBy('due_at')->latest('id');

        if ($actor->hasInstitutionalGlobalScope()) {
            $adminSection = $request->query('section') === 'unit' ? 'unit' : 'all';
            $pageTitle = $adminSection === 'unit' ? 'Actividades de mi unidad' : 'Todas las actividades';
            if ($adminSection === 'unit') {
                $query->where('organizational_unit_id', $actor->organizational_unit_id);
            } else {
                $availableUnits = OrganizationalUnit::query()->availableAsDocumentDestination()->orderBy('name')->get();
            }
        } elseif ($isResponsible) {
            $pageTitle = 'Actividades de mi unidad';
            $query->where(function ($activities) use ($actor): void {
                $activities->where('organizational_unit_id', $actor->organizational_unit_id)
                    ->orWhere('assigned_by', $actor->getKey());
            });
        } else {
            $query->whereHas('assignees', fn ($assignees) => $assignees->whereKey($actor->getKey()));
        }

        if ($actor->hasInstitutionalGlobalScope() && $adminSection === 'all' && $availableUnits->isNotEmpty()) {
            $selectedUnitId = (int) $request->query('unit_id', 0);
            if ($selectedUnitId !== 0 && ! $availableUnits->contains('id', $selectedUnitId)) {
                $selectedUnitId = 0;
            }
            if ($selectedUnitId !== 0) {
                $query->where('organizational_unit_id', $selectedUnitId);
            }
        }

        if ($status = ActivityStatus::tryFrom((string) $request->query('status'))) {
            $query->where('status', $status->value);
        }

        if ($document = $request->query('document')) {
            $query->whereRelation('document', 'public_id', $document);
        }

        $activities = $query->paginate(15)->withQueryString();
        return view('activities.index', compact('activities', 'pageTitle', 'availableUnits', 'selectedUnitId', 'adminSection'));
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', WorkActivity::class);
        $actor = $request->user();
        $targetUnits = OrganizationalUnit::query()->availableAsDocumentDestination()
            ->whereIn('id', $actor->activityScopeUnitIds())->orderBy('name')->get();
        $targetUnitId = (int) ($request->query('target_unit_id') ?? $request->session()->getOldInput('target_unit_id') ?? $actor->organizational_unit_id);
        if (! $targetUnits->contains('id', $targetUnitId)) {
            $targetUnitId = (int) $targetUnits->first()->id;
        }
        $canSelectTargetUnit = $targetUnits->count() > 1;
        $team = User::query()->whereIn('organizational_unit_id', $targetUnits->pluck('id'))
            ->where('status', 'active')
            ->whereKeyNot($actor->getKey())
            ->with('organizationalUnit')->orderBy('name')->get();
        $rolePriority = [
            'administrator' => 1, 'co_administrator' => 2, 'career_head' => 3,
            'kardex_head' => 4, 'secretary' => 5, 'secretary_assistant' => 6, 'extra' => 7,
        ];
        $defaultPrimaryByUnit = $team->groupBy('organizational_unit_id')->map(function ($members) use ($rolePriority): int {
            return (int) $members->sortBy(fn (User $user): int => $rolePriority[$user->roleName()?->value] ?? 99)->first()->id;
        })->all();
        $documents = Document::query()->visibleTo($actor)->where(function ($query) use ($targetUnitId): void {
            $query->where('origin_unit_id', $targetUnitId)
                ->orWhereHas('recipients', fn ($recipients) => $recipients->where('unit_id', $targetUnitId));
        })->latest('id')->limit(100)->get(['id', 'public_id', 'code', 'subject']);
        $selectedDocument = $documents->firstWhere('public_id', $request->query('document'));

        return view('activities.create', compact('team', 'documents', 'selectedDocument', 'targetUnits', 'targetUnitId', 'canSelectTargetUnit', 'defaultPrimaryByUnit'));
    }

    public function store(Request $request, WorkActivityService $service, WorkActivityFileService $files): RedirectResponse
    {
        Gate::authorize('create', WorkActivity::class);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'activity_type' => ['required', Rule::enum(ActivityType::class)],
            'instructions' => ['required', 'string', 'max:5000'],
            'priority' => ['required', Rule::enum(ActivityPriority::class)],
            'due_at' => ['nullable', 'date', 'after_or_equal:today'],
            'target_unit_id' => ['required', 'integer', 'exists:unidades_organizacionales,id'],
            'document_public_id' => ['nullable', 'uuid'],
            'primary_assignee_id' => ['required', 'integer', 'min:1'],
            'assignees' => ['required', 'array', 'min:1'],
            'assignees.*' => ['integer', 'distinct', 'min:1'],
            'files' => ['nullable', 'array', 'max:10'],
            'files.*' => ['file', 'max:20480'],
        ], [
            'title.required' => 'Indique la labor encomendada.',
            'instructions.required' => 'Explique qué debe realizar el personal asignado.',
            'assignees.required' => 'Seleccione al menos una persona responsable.',
            'target_unit_id.required' => 'Seleccione la unidad responsable.',
            'target_unit_id.exists' => 'La unidad responsable no existe.',
            'due_at.after_or_equal' => 'La fecha límite no puede ser anterior a hoy.',
        ]);

        $activity = $service->create($request->user(), $data, array_map('intval', $data['assignees']));
        if ($request->hasFile('files')) {
            $files->store($request->user(), $activity, $request->file('files'));
        }

        return redirect()->route('activities.show', $activity)->with('status', 'Actividad asignada al personal seleccionado.');
    }

    public function show(Request $request, WorkActivity $activity): View
    {
        Gate::authorize('view', $activity);
        $activity->load(['organizationalUnit', 'document.originUnit', 'resultDocument', 'creator', 'assigner', 'reviewer', 'assignees', 'updates.user', 'files.uploader']);
        $resultDocuments = collect();
        if (Gate::allows('submit', $activity)) {
            $resultDocuments = Document::query()->where('created_by', $request->user()->getKey())
                ->where('origin_unit_id', $activity->organizational_unit_id)
                ->latest('id')->limit(50)->get(['id', 'public_id', 'code', 'subject', 'general_status']);
        }

        return view('activities.show', compact('activity', 'resultDocuments'));
    }

    public function progress(Request $request, WorkActivity $activity, WorkActivityService $service): RedirectResponse
    {
        Gate::authorize('recordProgress', $activity);
        $data = $request->validate([
            'detail' => ['required', 'string', 'max:3000'],
            'progress_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
        ], ['detail.required' => 'Describa el avance realizado.']);
        $service->recordProgress($request->user(), $activity, $data['detail'], isset($data['progress_percent']) ? (int) $data['progress_percent'] : null);

        return back()->with('status', 'Avance registrado.');
    }

    public function submit(Request $request, WorkActivity $activity, WorkActivityService $service): RedirectResponse
    {
        Gate::authorize('submit', $activity);
        $data = $request->validate([
            'result_summary' => ['required', 'string', 'max:5000'],
            'result_document_public_id' => ['nullable', 'uuid'],
        ], ['result_summary.required' => 'Describa el producto o resultado presentado.']);
        $resultDocument = filled($data['result_document_public_id'] ?? null)
            ? Document::query()->where('public_id', $data['result_document_public_id'])->firstOrFail()
            : null;
        $service->submit($request->user(), $activity, $data['result_summary'], $resultDocument);

        return back()->with('status', 'Resultado presentado a Jefatura para su revisión.');
    }

    public function review(Request $request, WorkActivity $activity, WorkActivityService $service): RedirectResponse
    {
        Gate::authorize('review', $activity);
        $data = $request->validate([
            'decision' => ['required', Rule::in(['complete', 'observe'])],
            'review_note' => [Rule::requiredIf($request->input('decision') === 'observe'), 'nullable', 'string', 'max:3000'],
        ], ['review_note.required' => 'Explique qué debe corregirse o completarse.']);
        $service->review($request->user(), $activity, $data['decision'], $data['review_note'] ?? null);

        return back()->with('status', $data['decision'] === 'complete' ? 'Actividad concluida.' : 'Actividad devuelta con observaciones.');
    }

    public function cancel(Request $request, WorkActivity $activity, WorkActivityService $service): RedirectResponse
    {
        Gate::authorize('cancel', $activity);
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']], ['reason.required' => 'Explique por qué se cancela la actividad.']);
        $service->cancel($request->user(), $activity, $data['reason']);

        return back()->with('status', 'Actividad cancelada con registro del motivo.');
    }
}
