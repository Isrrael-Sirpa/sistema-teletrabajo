<x-layouts.authenticated :title="'Inicio — '.config('app.name')" :unread-notification-count="$unreadNotificationCount">
    <header class="ft-page-header">
        <div>
            @unless ($unitDashboard)<p class="ft-eyebrow">Su espacio de trabajo</p>@endunless
            <h1 class="ft-page-title">Buen día, {{ auth()->user()->name }}</h1>
            @unless ($unitDashboard)<p class="ft-page-description">Revise lo que necesita atención y continúe desde donde lo dejó.</p>@endunless
            <p class="mt-3 inline-flex flex-wrap items-center gap-x-2 gap-y-1 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700"><span class="font-semibold">{{ $roleLabel }}</span><span aria-hidden="true" class="text-slate-300">|</span><span>{{ $scopeLabel }}</span></p>
        </div>
        @can('create', App\Models\Document::class)
            <a href="{{ route('documents.create') }}" @class(['self-start sm:self-auto', 'ft-button-secondary' => $unitDashboard, 'ft-button-primary' => ! $unitDashboard])><x-ui.icon name="plus" /> Nuevo documento</a>
        @endcan
    </header>

    @if ($temporaryHeadships->isNotEmpty())
        <section class="ft-card ft-card-accent mt-6 p-5" aria-labelledby="temporary-headship-title">
            <h2 id="temporary-headship-title" class="ft-form-section-title">Jefatura temporal activa</h2>
            <p class="ft-field-help">Las funciones delegadas estarán disponibles durante el periodo indicado.</p>
            @foreach ($temporaryHeadships as $delegation)
                <div class="mt-3 flex flex-col gap-3 border-t border-slate-200 pt-3 sm:flex-row sm:items-center sm:justify-between">
                    <div><p class="font-semibold">{{ $delegation->organizationalUnit->name }}</p><p class="text-sm text-slate-600">Delegada por {{ $delegation->delegator->name }} hasta {{ $delegation->ends_at->timezone(config('app.timezone'))->format('d/m/Y H:i') }}.</p></div>
                    <div class="flex flex-wrap gap-2"><a href="{{ route('documents.index') }}" class="ft-button-secondary">Trabajo documental</a>@if ($delegation->permissions->contains('name', 'users.view'))<a href="{{ route('users.index') }}" class="ft-button-secondary">Usuarios de la carrera</a>@endif</div>
                </div>
            @endforeach
        </section>
    @endif

    @if ($unitDashboard)
        @include('dashboard.unit')
    @else
    <section class="mt-8" aria-labelledby="attention-title">
        <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
            <div><p class="ft-eyebrow">Por dónde empezar</p><h2 id="attention-title" class="mt-1 text-xl font-bold text-[var(--ft-primary)]">{{ $role === App\Enums\RoleName::EXTRA ? 'Documentos autorizados recientes' : 'Necesitan su atención' }}</h2></div>
            @can('viewAny', App\Models\Document::class)<a href="{{ route('documents.index') }}" class="text-sm font-bold text-[var(--ft-secondary)] underline-offset-4 hover:underline">Ver todos los documentos <span aria-hidden="true">→</span></a>@endcan
        </div>
        <div class="ft-card overflow-hidden">
            @if ($role !== App\Enums\RoleName::EXTRA)
                <p class="border-b border-[var(--ft-border)] bg-slate-50 px-5 py-3 text-sm leading-6 text-slate-600 sm:px-6">Hasta cinco documentos, ordenados por vencimiento, urgencia y antigüedad. Abra uno para ver el siguiente paso disponible.</p>
            @else
                <p class="border-b border-[var(--ft-border)] bg-slate-50 px-5 py-3 text-sm text-slate-600 sm:px-6">Solo se muestran documentos con una autorización vigente.</p>
            @endif
            <ul class="divide-y divide-[var(--ft-border)]" aria-label="Documentos prioritarios">
                @forelse ($attention as $item)
                    @php($document = $item['document'])
                    <li class="border-l-4 border-l-[var(--career-primary)] p-5 sm:px-6">
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <x-ui.status-badge :status="$document->general_status" />
                                    @if ($item['deadlineStatus'] === App\Enums\DocumentDeadlineStatus::OVERDUE)<span class="ft-badge border border-red-200 bg-red-50 text-red-900">Vencido</span>
                                    @elseif ($item['deadlineStatus'] === App\Enums\DocumentDeadlineStatus::DUE_SOON)<span class="ft-badge border border-amber-200 bg-amber-50 text-amber-900">Próximo a vencer</span>
                                    @endif
                                    @if ($document->priority === App\Enums\DocumentPriority::URGENT)<span class="ft-badge bg-red-50 text-red-900">Urgente</span>@endif
                                    @if ($item['correctionPrepared'])<span class="ft-badge bg-emerald-50 text-emerald-900">CORRECCIÓN PREPARADA · FALTA ENVIAR</span>@endif
                                </div>
                                <a href="{{ route('documents.show', $document) }}" class="mt-2 block break-words font-bold text-[var(--ft-primary)] underline-offset-4 hover:underline">{{ $item['subject'] }}</a>
                                <p class="mt-1 break-words text-sm text-slate-600">{{ $document->code ?? 'Borrador sin código' }} · {{ $document->documentType->name }} · {{ $document->originUnit->name }}</p>
                                <p class="mt-1 text-xs text-slate-500">Fecha límite: {{ $document->due_at?->timezone(config('app.timezone'))->format('d/m/Y') ?? 'Sin fecha' }}</p>
                            </div>
                            <a href="{{ route('documents.show', $document) }}" class="ft-button-secondary shrink-0 self-start sm:self-auto" aria-label="Ver documento: {{ $item['subject'] }}">Ver documento <x-ui.icon name="arrow" class="h-4 w-4" /></a>
                        </div>
                    </li>
                @empty
                    <li class="ft-empty-state px-6 py-12 text-center"><div class="mx-auto grid h-12 w-12 place-items-center rounded-full bg-emerald-50 text-emerald-700"><x-ui.icon name="documents" /></div><p class="mt-3 font-bold text-slate-900">{{ $role === App\Enums\RoleName::EXTRA ? 'Todavía no tiene documentos autorizados.' : 'No tiene documentos pendientes por atender.' }}</p><p class="mt-2 text-sm text-slate-600">{{ $role === App\Enums\RoleName::EXTRA ? 'Cuando reciba una autorización vigente, sus documentos aparecerán aquí.' : 'Cuando exista trabajo dentro de su ámbito, aparecerá aquí. Puede consultar sus documentos desde el menú.' }}</p></li>
                @endforelse
            </ul>
        </div>
    </section>

    <section class="mt-9" aria-labelledby="summary-title">
        <p class="ft-eyebrow">Vista rápida</p>
        <h2 id="summary-title" class="mt-1 text-xl font-bold text-[var(--ft-primary)]">Resumen</h2>
        <p class="mt-2 text-sm leading-6 text-slate-600">Seleccione una categoría para abrir su listado. Las cifras corresponden a su ámbito de acceso.</p>
        <div class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($cards as $card)
                <a href="{{ $card['url'] }}" class="ft-card ft-card-accent group flex min-h-44 flex-col p-5 transition hover:-translate-y-0.5 hover:shadow-md" aria-label="{{ $card['label'] }}: {{ $card['count'] }}. Abrir listado relacionado">
                    <div class="flex items-start justify-between gap-3"><span class="grid h-9 w-9 place-items-center rounded-lg bg-[var(--career-background)] text-[var(--career-primary)]"><x-ui.icon name="documents" /></span>@if($card['tone'] === 'danger')<span class="ft-badge bg-red-50 text-red-900">Vencido</span>@elseif($card['tone'] === 'warning')<span class="ft-badge bg-amber-50 text-amber-900">Próximo</span>@endif</div>
                    <div class="mt-4 flex items-baseline gap-2"><span class="text-3xl font-bold tracking-tight text-[var(--ft-primary)]">{{ $card['count'] }}</span><span class="text-sm font-bold text-slate-800">{{ $card['label'] }}</span></div>
                    <p class="mt-1 text-sm leading-5 text-slate-600">{{ $card['description'] }}</p>
                    <span class="mt-auto pt-3 text-sm font-bold text-[var(--ft-secondary)] group-hover:underline">Abrir listado <span aria-hidden="true">→</span></span>
                </a>
            @endforeach
        </div>
    </section>

    @if ($quickLinks)
        <section class="ft-card mt-9 p-5 sm:p-6" aria-labelledby="quick-links-title"><h2 id="quick-links-title" class="ft-form-section-title">Accesos rápidos</h2><p class="ft-field-help">Herramientas disponibles para su trabajo institucional.</p><nav class="mt-4 flex flex-wrap gap-3" aria-label="Accesos rápidos del panel">@foreach ($quickLinks as $link)<a href="{{ $link['url'] }}" class="ft-button-secondary">{{ $link['label'] }} <x-ui.icon name="arrow" class="h-4 w-4" /></a>@endforeach</nav></section>
    @endif
    @endif
</x-layouts.authenticated>
