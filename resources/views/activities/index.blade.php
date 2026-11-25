<x-layouts.authenticated :title="$pageTitle">
    <div class="ft-page-header">
        <h1 class="ft-page-title">{{ $pageTitle }}</h1>
        @can('create', App\Models\WorkActivity::class)
            <a href="{{ route('activities.create') }}" class="ft-button-primary">Asignar actividad</a>
        @endcan
    </div>

    @if(auth()->user()->hasInstitutionalGlobalScope())
        <nav class="mt-6 flex flex-wrap gap-2" aria-label="Actividades administrativas">
            <a href="{{ route('activities.index', ['section' => 'all']) }}" class="ft-button-secondary @if($adminSection === 'all') ring-2 ring-blue-700 @endif">Todas las actividades</a>
            <a href="{{ route('activities.index', ['section' => 'unit']) }}" class="ft-button-secondary @if($adminSection === 'unit') ring-2 ring-blue-700 @endif">Actividades de mi unidad</a>
        </nav>
    @endif

    <form method="GET" action="{{ route('activities.index') }}" class="mt-6 flex flex-wrap items-end gap-3 rounded-xl border border-slate-200 bg-white p-4" aria-label="Filtrar actividades">
        @if($adminSection === 'all')<input type="hidden" name="section" value="all">@endif
        @if($availableUnits->isNotEmpty())
            <div><label for="unit_id" class="block text-sm font-bold text-slate-800">Unidad</label><select id="unit_id" name="unit_id" class="mt-1 min-h-11 rounded-lg border-slate-300"><option value="0" @selected($selectedUnitId === 0)>Todas las unidades</option>@foreach($availableUnits as $unit)<option value="{{ $unit->id }}" @selected($selectedUnitId === $unit->id)>{{ $unit->name }}</option>@endforeach</select></div>
        @endif
        <div><label for="status" class="block text-sm font-bold text-slate-800">Estado</label><select id="status" name="status" class="mt-1 min-h-11 rounded-lg border-slate-300"><option value="">Todos</option>@foreach (App\Enums\ActivityStatus::cases() as $status)<option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>@endforeach</select></div>
        <button class="ft-button-secondary" type="submit">Aplicar filtro</button>
        @if(request()->hasAny(['status', 'unit_id']))<a href="{{ route('activities.index') }}" class="ft-button-secondary">Limpiar</a>@endif
    </form>

    <div class="mt-6 space-y-4">
        @forelse ($activities as $activity)
            <article class="ft-card p-5"><div class="flex flex-wrap items-start justify-between gap-3"><div class="min-w-0"><p class="text-xs font-bold uppercase tracking-wide text-slate-600">{{ $activity->activity_type->label() }}</p><h2 class="mt-1 break-words text-lg font-bold text-slate-950"><a class="hover:underline" href="{{ route('activities.show', $activity) }}">{{ $activity->title }}</a></h2></div><span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-800">{{ $activity->status->label() }}</span></div><dl class="mt-3 grid gap-2 text-sm text-slate-700 sm:grid-cols-3"><div><dt class="font-bold">Responsable</dt><dd>{{ $activity->primaryAssignee()?->displayName() ?? 'Sin responsable' }}</dd></div><div><dt class="font-bold">Fecha límite</dt><dd>{{ $activity->due_at?->format('d/m/Y') ?? 'Sin fecha límite' }}</dd></div><div><dt class="font-bold">Unidad</dt><dd>{{ $activity->organizationalUnit->name }}</dd></div></dl><a href="{{ route('activities.show', $activity) }}" class="ft-button-secondary mt-4">Abrir tarea</a></article>
        @empty
            <div class="ft-card p-8 text-center"><p class="font-bold text-slate-900">No hay actividades para mostrar.</p></div>
        @endforelse
    </div>
    @if($activities->hasPages())<div class="mt-6">{{ $activities->links() }}</div>@endif
</x-layouts.authenticated>
