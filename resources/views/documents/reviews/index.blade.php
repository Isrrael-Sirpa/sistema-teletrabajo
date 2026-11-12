<x-layouts.authenticated :title="$unitBoard ? 'Revisiones de mi unidad' : 'Mis tareas'">
    <div class="ft-page-header">
        <div>
            <h1 class="ft-page-title">{{ $unitBoard ? 'Revisiones de mi unidad' : 'Mis tareas' }}</h1>
            <p class="ft-page-description">
                {{ $unitBoard
                    ? 'Consulte el avance del equipo. Para asignar una revisión, abra un documento recibido.'
                    : 'Abra una tarea para consultar el documento y entregar su recomendación al responsable de la unidad.' }}
            </p>
        </div>
        @if ($unitBoard)
            <a href="{{ route('documents.index', ['work' => 'decision']) }}" class="ft-button-secondary">Ver documentos recibidos</a>
        @endif
    </div>

    <div class="mt-6 grid items-start gap-4 xl:grid-cols-3">
        @foreach ($lanes as $label => $tasks)
            <section class="rounded-xl border border-slate-200 bg-slate-50 p-4" aria-label="{{ $label }}">
                <h2 class="flex items-center justify-between gap-2 text-lg font-bold text-slate-950">
                    {{ $label }}
                    <span class="rounded-full bg-white px-2.5 py-1 text-sm">{{ $tasks->total() }}</span>
                </h2>
                <div class="mt-3 space-y-3">
                    @forelse ($tasks as $task)
                        <article class="ft-card p-4">
                            <p class="text-xs font-semibold text-slate-600">{{ $task->recipient->document->code }} · {{ $task->recipient->unit->name }}</p>
                            <h3 class="mt-1 break-words font-bold text-slate-950">{{ $task->recipient->document->subject }}</h3>
                            <p class="mt-2 text-sm text-slate-700">{{ $unitBoard ? 'Asignada a '.$task->assignee->name : 'Asignada por '.$task->assigner->name }}</p>
                            <p class="mt-2 break-words text-sm text-slate-700">{{ Illuminate\Support\Str::limit($task->instructions, 160) }}</p>
                            @if ($task->recommendation)
                                <p class="mt-2 text-sm font-semibold text-slate-800">{{ $task->recommendation->label() }}</p>
                            @endif
                            <a href="{{ route('documents.show', $task->recipient->document) }}#revision-{{ $task->public_id }}" class="ft-button-secondary mt-3">Abrir revisión</a>
                        </article>
                    @empty
                        <p class="py-4 text-sm text-slate-600">No hay revisiones en esta columna.</p>
                    @endforelse
                </div>
                @if ($tasks->hasPages())
                    <div class="mt-4">{{ $tasks->links('pagination::simple-tailwind') }}</div>
                @endif
            </section>
        @endforeach
    </div>
</x-layouts.authenticated>
