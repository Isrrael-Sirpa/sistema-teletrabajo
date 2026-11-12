<div class="mt-7 grid items-start gap-5 xl:grid-cols-[minmax(0,1.65fr)_minmax(0,1fr)]">
    <section class="ft-card min-w-0" aria-labelledby="unit-pending-title">
        <header class="flex flex-wrap items-center justify-between gap-3 px-5 py-4 sm:px-6">
            <h2 id="unit-pending-title" class="text-xl font-bold text-[var(--ft-primary)]">Documentos pendientes</h2>
            @if ($unitPending->isNotEmpty())
                <a href="{{ route('documents.index', ['work' => 'unit_pending']) }}" class="ft-button-secondary">Ver todos los pendientes</a>
            @endif
        </header>

        <ul class="divide-y divide-slate-200 border-t border-slate-200">
            @forelse ($unitPending as $item)
                @php
                    $document = $item['document'];
                    $overdue = $item['deadlineStatus'] === App\Enums\DocumentDeadlineStatus::OVERDUE;
                    $dueSoon = $item['deadlineStatus'] === App\Enums\DocumentDeadlineStatus::DUE_SOON;
                    $attachments = $item['attachmentCount'];
                @endphp
                <li class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="ft-badge bg-[var(--career-background)] text-[var(--career-primary)]">{{ $item['step'] }}</span>
                            @if ($document->priority === App\Enums\DocumentPriority::URGENT)
                                <span class="ft-badge bg-red-50 text-red-900">Urgente</span>
                            @endif
                        </div>
                        <h3 class="mt-1.5 break-words font-bold text-slate-950">{{ $document->subject }}</h3>
                        <p class="mt-1 break-words text-sm text-slate-600">De {{ $document->originUnit->name }}</p>
                        @if ($document->due_at || $attachments > 0)
                            <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-slate-600">
                                @if ($document->due_at)
                                    <span @class(['font-semibold text-red-800' => $overdue, 'font-semibold text-amber-900' => $dueSoon])>
                                        {{ $overdue ? 'Vencido desde el' : ($dueSoon ? 'Próximo a vencer el' : 'Vence el') }}
                                        <time datetime="{{ $document->due_at->toIso8601String() }}">{{ $document->due_at->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</time>
                                    </span>
                                @endif
                                @if ($attachments > 0)
                                    <span>{{ $attachments }} {{ $attachments === 1 ? 'adjunto' : 'adjuntos' }}</span>
                                @endif
                            </div>
                        @endif
                    </div>
                    <a href="{{ $item['url'] }}" class="ft-button-primary shrink-0 self-start sm:self-auto">
                        {{ $item['actionLabel'] }}<span class="sr-only"> · {{ $document->subject }}</span>
                        <x-ui.icon name="arrow" class="h-4 w-4" />
                    </a>
                </li>
            @empty
                <li class="ft-empty-state px-5 py-9 text-center font-semibold text-slate-800">No hay documentos pendientes para su unidad.</li>
            @endforelse
        </ul>

        @if ($hasReturnedCorrections)
            <footer class="border-t border-slate-200 px-5 py-4 sm:px-6">
                <a href="{{ route('documents.index', ['work' => 'returned_correction']) }}" class="ft-button-secondary">Correcciones por reenviar</a>
            </footer>
        @endif
    </section>

    <section class="ft-card min-w-0 p-5 sm:p-6" aria-labelledby="team-tasks-title">
        <h2 id="team-tasks-title" class="text-xl font-bold text-[var(--ft-primary)]">Tareas del equipo</h2>

        @if ($teamTasks['people']->isNotEmpty())
            <dl class="mt-4 grid grid-cols-2 gap-2">
                <div class="rounded-lg bg-slate-100 px-3 py-2">
                    <dt class="text-sm text-slate-700">Pendientes</dt>
                    <dd class="text-2xl font-bold text-slate-950">{{ $teamTasks['pending'] }}</dd>
                </div>
                <div class="rounded-lg bg-emerald-50 px-3 py-2">
                    <dt class="text-sm text-emerald-900">Entregadas</dt>
                    <dd class="text-2xl font-bold text-emerald-950">{{ $teamTasks['delivered'] }}</dd>
                </div>
            </dl>

            <h3 class="mt-5 text-sm font-bold text-slate-900">Por persona</h3>
            <ul class="mt-1 divide-y divide-slate-200 text-sm">
                @foreach ($teamTasks['people'] as $person)
                    <li class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1 py-2">
                        <span class="break-words font-semibold text-slate-800">{{ $person->assignee->name }}</span>
                        <span class="text-slate-600">{{ $person->pending }} {{ $person->pending == 1 ? 'pendiente' : 'pendientes' }} · {{ $person->delivered }} {{ $person->delivered == 1 ? 'entregada' : 'entregadas' }}</span>
                    </li>
                @endforeach
            </ul>

            <h3 class="mt-4 text-sm font-bold text-slate-900">Asignaciones y entregas recientes</h3>
            <ul class="mt-1 divide-y divide-slate-200 text-sm">
                @foreach ($teamTasks['recent'] as $task)
                    <li class="py-2">
                        <a href="{{ route('documents.show', $task->recipient->document) }}#revision-{{ $task->public_id }}" class="block min-h-11 rounded break-words py-1">
                            <span class="font-semibold text-[var(--ft-secondary)] underline underline-offset-4">{{ $task->recipient->document->subject }}</span>
                            <span class="block text-slate-600">{{ $task->assignee->name }} · {{ $task->submitted_at ? 'Entregada' : 'Pendiente' }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @else
            <p class="mt-3 text-sm text-slate-600">Su unidad no tiene revisiones pendientes ni entregadas.</p>
        @endif

        <a href="{{ route('reviews.unit') }}" class="ft-button-secondary mt-4">Ver tablero completo</a>
    </section>
</div>
