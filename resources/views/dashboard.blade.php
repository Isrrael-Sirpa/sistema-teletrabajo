<x-layouts.authenticated :title="'Panel — '.config('app.name')" :unread-notification-count="$unreadNotificationCount">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <p class="text-sm font-semibold text-blue-700">{{ $roleLabel }} · {{ $scopeLabel }}</p>
            <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-950 sm:text-3xl">Buenos días, {{ auth()->user()->name }}</h1>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">Aquí encontrará el trabajo que necesita revisar y los accesos más importantes para su función.</p>
        </div>
    </div>

    @if ($temporaryHeadships->isNotEmpty())
        <section class="mt-6 rounded-2xl border border-blue-300 bg-blue-50 p-5" aria-labelledby="temporary-headship-title">
            <h2 id="temporary-headship-title" class="text-lg font-bold text-blue-950">Jefatura temporal activa</h2>
            @foreach ($temporaryHeadships as $delegation)
                <div class="mt-3 flex flex-col gap-3 border-t border-blue-200 pt-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="font-semibold text-blue-950">{{ $delegation->organizationalUnit->name }}</p>
                        <p class="text-sm text-blue-900">Delegada por {{ $delegation->delegator->name }} hasta {{ $delegation->ends_at->timezone(config('app.timezone'))->format('d/m/Y H:i') }}.</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <a href="{{ route('documents.index') }}" class="rounded-lg bg-white px-3 py-2 text-sm font-bold text-blue-900">Trabajo documental</a>
                        @if ($delegation->permissions->contains('name', 'users.view'))
                            <a href="{{ route('users.index') }}" class="rounded-lg bg-white px-3 py-2 text-sm font-bold text-blue-900">Usuarios de la carrera</a>
                        @endif
                    </div>
                </div>
            @endforeach
        </section>
    @endif

    <section class="mt-7" aria-labelledby="summary-title">
        <h2 id="summary-title" class="text-lg font-bold text-slate-950">Resumen de trabajo</h2>
        <div class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($cards as $card)
                <a href="{{ $card['url'] }}" @class([
                    'group rounded-xl border bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md',
                    'border-red-300' => $card['tone'] === 'danger',
                    'border-amber-300' => $card['tone'] === 'warning',
                    'border-slate-200' => $card['tone'] === 'neutral',
                ]) aria-label="{{ $card['label'] }}: {{ $card['count'] }}. Abrir listado relacionado">
                    <div class="flex items-start justify-between gap-3">
                        <p class="text-sm font-bold text-slate-800">{{ $card['label'] }}</p>
                        @if ($card['tone'] === 'danger')
                            <span class="rounded-full bg-red-50 px-2 py-1 text-xs font-bold text-red-800">VENCIDO</span>
                        @elseif ($card['tone'] === 'warning')
                            <span class="rounded-full bg-amber-50 px-2 py-1 text-xs font-bold text-amber-900">PRÓXIMO</span>
                        @endif
                    </div>
                    <p class="mt-3 text-3xl font-bold tracking-tight text-slate-950">{{ $card['count'] }}</p>
                    <p class="mt-2 text-sm leading-5 text-slate-600">{{ $card['description'] }}</p>
                    <span class="mt-4 inline-flex text-sm font-bold text-blue-800 group-hover:underline">Abrir listado <span aria-hidden="true" class="ml-1">→</span></span>
                </a>
            @endforeach
        </div>
    </section>

    <div class="mt-8 grid gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(16rem,1fr)]">
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" aria-labelledby="attention-title">
            <div class="border-b border-slate-200 px-5 py-4 sm:px-6">
                <h2 id="attention-title" class="text-lg font-bold text-slate-950">{{ $attentionTitle }}</h2>
                @if ($role !== App\Enums\RoleName::EXTRA)
                    <p class="mt-1 text-sm text-slate-600">Primero vencidos, urgentes, próximos a vencer y luego los más antiguos.</p>
                @else
                    <p class="mt-1 text-sm text-slate-600">Solo aparecen documentos con una autorización explícita vigente.</p>
                @endif
            </div>

            <ul class="divide-y divide-slate-200" aria-label="Documentos prioritarios">
                @forelse ($attention as $item)
                    @php($document = $item['document'])
                    <li class="p-5 sm:px-6">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    @if ($item['deadlineStatus'] === App\Enums\DocumentDeadlineStatus::OVERDUE)
                                        <span class="rounded-full border border-red-300 bg-red-50 px-2.5 py-1 text-xs font-bold text-red-900">VENCIDO</span>
                                    @elseif ($document->priority === App\Enums\DocumentPriority::URGENT)
                                        <span class="rounded-full border border-red-200 bg-red-50 px-2.5 py-1 text-xs font-bold text-red-900">URGENTE</span>
                                    @elseif ($item['deadlineStatus'] === App\Enums\DocumentDeadlineStatus::DUE_SOON)
                                        <span class="rounded-full border border-amber-300 bg-amber-50 px-2.5 py-1 text-xs font-bold text-amber-900">PRÓXIMO A VENCER</span>
                                    @elseif ($document->priority === App\Enums\DocumentPriority::IMPORTANT)
                                        <span class="rounded-full border border-blue-200 bg-blue-50 px-2.5 py-1 text-xs font-bold text-blue-900">IMPORTANTE</span>
                                    @endif
                                    <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-700">{{ $document->general_status->label() }}</span>
                                    @if ($item['correctionPrepared'])
                                        <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-bold text-emerald-900">CORRECCIÓN PREPARADA · FALTA ENVIAR</span>
                                    @endif
                                </div>
                                <a href="{{ route('documents.show', $document) }}" class="mt-3 block break-words font-bold text-blue-900 underline-offset-4 hover:underline">{{ $item['subject'] }}</a>
                                <p class="mt-1 text-sm text-slate-600">{{ $document->documentType->name }} · {{ $document->originUnit->name }}</p>
                                <p class="mt-1 text-xs text-slate-500">Fecha límite: {{ $document->due_at?->timezone(config('app.timezone'))->format('d/m/Y') ?? 'Sin fecha' }}</p>
                            </div>
                            <a href="{{ route('documents.show', $document) }}" class="inline-flex min-h-10 shrink-0 items-center justify-center rounded-lg border border-slate-300 px-3 py-2 text-sm font-bold text-slate-700 hover:bg-slate-50">Ver documento</a>
                        </div>
                    </li>
                @empty
                    <li class="px-5 py-12 text-center sm:px-6">
                        <p class="font-bold text-slate-800">No tiene documentos pendientes por atender.</p>
                        <p class="mt-2 text-sm text-slate-600">Cuando exista trabajo dentro de su ámbito autorizado aparecerá aquí.</p>
                    </li>
                @endforelse
            </ul>
        </section>

        <aside class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6" aria-labelledby="quick-links-title">
            <h2 id="quick-links-title" class="text-lg font-bold text-slate-950">Accesos rápidos</h2>
            <nav class="mt-4 grid gap-3" aria-label="Accesos rápidos del panel">
                @foreach ($quickLinks as $link)
                    <a href="{{ $link['url'] }}" class="inline-flex min-h-11 items-center justify-between rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-bold text-slate-800 hover:border-blue-400 hover:bg-blue-50">
                        {{ $link['label'] }} <span aria-hidden="true">→</span>
                    </a>
                @endforeach
            </nav>
        </aside>
    </div>
</x-layouts.authenticated>
