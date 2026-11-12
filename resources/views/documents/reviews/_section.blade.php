@if ($assignableDestinations->isNotEmpty() || $reviewTasks->isNotEmpty())
    <section id="revisiones" tabindex="-1" class="ft-card mt-6 scroll-mt-24 p-5 sm:p-7" aria-labelledby="reviews-title">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 id="reviews-title" class="text-lg font-bold text-slate-950">{{ $isUnitResponsible ? 'Revisiones internas de su unidad' : 'Mis tareas en este documento' }}</h2>
                <p class="mt-1 text-sm text-slate-600">El responsable de la unidad consulta las recomendaciones y registra la respuesta oficial por separado.</p>
            </div>
            <a href="{{ route($isUnitResponsible ? 'reviews.unit' : 'reviews.mine') }}" class="ft-button-secondary">{{ $isUnitResponsible ? 'Ver tablero de mi unidad' : 'Ver Mis tareas' }}</a>
        </div>

        @if ($isUnitResponsible && $ownRecipient && in_array($summaryAction, ['start_review', 'approve'], true))
            <a href="#destino-{{ $ownRecipient->id }}" class="mt-3 inline-block text-sm font-bold text-[var(--ft-secondary)] underline underline-offset-4">Ir a la revisión oficial</a>
        @endif

        @if ($isUnitResponsible && $reviewTasks->contains(fn ($task) => $task->isPending()))
            <p class="mt-3 text-sm text-slate-700">Al registrar una respuesta oficial, se cerrarán las revisiones pendientes de ese destino.</p>
        @endif

        @foreach ($assignableDestinations as $destination)
            @if ($reviewTeam->isEmpty())
                <p class="ft-alert ft-alert-info mt-4">Agregue una Secretaria o un Auxiliar activo al equipo de su unidad para asignar revisiones.</p>
            @else
                <form id="asignar-revision-{{ $destination->id }}" tabindex="-1" method="POST" action="{{ route('documents.reviews.store', [$document, $destination]) }}" class="mt-4 scroll-mt-24 space-y-3 rounded-xl border border-blue-200 bg-blue-50/40 p-4">
                    @csrf
                    <input type="hidden" name="assignment_key" value="{{ Str::uuid() }}">
                    <input type="hidden" name="recipient_lock_version" value="{{ $destination->lock_version }}">
                    @if ($assignableDestinations->count() > 1)
                        <p class="font-semibold text-slate-900">Destino {{ $destination->user?->name ?? $destination->unit->name }}</p>
                    @endif
                    <div>
                        <label for="assignee-{{ $destination->id }}" class="block text-sm font-bold text-slate-800">¿Quién revisará?</label>
                        <select id="assignee-{{ $destination->id }}" name="assigned_to" required class="mt-1 block min-h-11 w-full rounded-lg border-slate-300">
                            <option value="">Seleccione a alguien de su equipo</option>
                            @foreach ($reviewTeam as $person)
                                <option value="{{ $person->id }}" @selected(old('assigned_to') == $person->id)>{{ $person->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="instructions-{{ $destination->id }}" class="block text-sm font-bold text-slate-800">¿Qué debe revisar?</label>
                        <textarea id="instructions-{{ $destination->id }}" name="instructions" required maxlength="2000" rows="3" class="mt-1 block w-full rounded-lg border-slate-300">{{ old('instructions') }}</textarea>
                    </div>
                    <button type="submit" class="ft-button-primary">Asignar revisión</button>
                </form>
            @endif
        @endforeach

        <div class="mt-4 space-y-3">
            @foreach ($reviewTasks as $task)
                <article id="revision-{{ $task->public_id }}" tabindex="-1" class="scroll-mt-24 rounded-xl border border-slate-200 p-4">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <h3 class="font-bold text-slate-950">{{ $isUnitResponsible ? $task->assignee->name : 'Revisión asignada por '.$task->assigner->name }}</h3>
                        <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-800">
                            {{ $task->submitted_at ? 'Entregada' : ($task->closed_at ? 'Cerrada' : 'Pendiente') }}
                        </span>
                    </div>
                    <p class="mt-1 text-sm text-slate-600">
                        {{ $task->recipient->unit->name }}
                        · {{ $task->version ? 'Versión '.$task->version->version_number : 'Sin archivos adjuntos' }}
                        · {{ $task->created_at->format('d/m/Y H:i') }}
                    </p>
                    <p class="mt-2 whitespace-pre-wrap break-words text-sm text-slate-800">{{ $task->instructions }}</p>
                    @if ($task->submitted_at)
                        <div class="mt-3 rounded-lg bg-slate-50 p-3">
                            <p class="text-sm font-bold text-slate-950">{{ $task->recommendation->label() }} · {{ $task->submitted_at->format('d/m/Y H:i') }}</p>
                            <p class="mt-1 whitespace-pre-wrap break-words text-sm text-slate-800">{{ $task->message }}</p>
                        </div>
                    @elseif ($task->isPending() && Gate::allows('submitReview', [$document, $task->recipient, $task]))
                        @if ($task->version)
                            <a href="#version-{{ $task->version->version_number }}" class="mt-3 inline-block text-sm font-bold text-blue-800 underline">Ver adjuntos de esta revisión</a>
                        @endif
                        <form method="POST" action="{{ route('documents.reviews.update', [$document, $task]) }}" class="mt-3 space-y-3">
                            @csrf
                            @method('PATCH')
                            <div>
                                <label for="recommendation-{{ $task->id }}" class="block text-sm font-bold text-slate-800">Su recomendación</label>
                                <select id="recommendation-{{ $task->id }}" name="recommendation" required class="mt-1 block min-h-11 w-full rounded-lg border-slate-300">
                                    <option value="">Seleccione una recomendación</option>
                                    @foreach (App\Enums\ReviewRecommendation::cases() as $recommendation)
                                        <option value="{{ $recommendation->value }}" @selected(old('recommendation') === $recommendation->value)>{{ $recommendation->label() }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label for="message-{{ $task->id }}" class="block text-sm font-bold text-slate-800">Mensaje para el responsable</label>
                                <textarea id="message-{{ $task->id }}" name="message" required maxlength="2000" rows="3" class="mt-1 block w-full rounded-lg border-slate-300">{{ old('message') }}</textarea>
                            </div>
                            <button type="submit" class="ft-button-primary">Entregar revisión</button>
                        </form>
                    @endif
                </article>
            @endforeach
        </div>
    </section>
@endif
