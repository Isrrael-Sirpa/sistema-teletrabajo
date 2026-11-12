@props(['title' => null, 'unreadNotificationCount' => null, 'careerUnit' => null])

@php
    $viewer = auth()->user();
    $role = $viewer->roleName();
    $activeCareer = $careerUnit ?? ($role?->requiresUnit() ? $viewer->organizationalUnit : null);
    $navigationUnreadCount = $unreadNotificationCount ?? $viewer->unreadNotifications()->count();
    $moduleTitle = $title ? str($title)->before(' — ')->toString() : 'Inicio';
    $section = match (true) {
        request()->routeIs('documents.*') => ['Documentos', route('documents.index')],
        request()->routeIs('reviews.mine') => ['Mis tareas', route('reviews.mine')],
        request()->routeIs('reviews.unit') => ['Revisiones de mi unidad', route('reviews.unit')],
        request()->routeIs('users.*') => ['Usuarios', route('users.index')],
        request()->routeIs('units.*') => ['Carreras y unidades', route('units.index')],
        request()->routeIs('document-types.*') => ['Tipos documentales', route('document-types.index')],
        request()->routeIs('delegations.*') => ['Delegaciones', route('delegations.index')],
        request()->routeIs('reports.*') => ['Reportes', route('reports.documents.index')],
        request()->routeIs('audit.*') => ['Auditoría', route('audit.index')],
        request()->routeIs('notifications.*') => ['Notificaciones', route('notifications.index')],
        default => null,
    };
@endphp

<x-layouts.app :title="$title ?? config('app.name')">
    <div class="ft-workspace" data-career="{{ $activeCareer?->acronym ?? '' }}">
        <div class="fixed inset-0 z-40 hidden bg-slate-950/50 lg:hidden" data-sidebar-overlay></div>

        <aside id="navegacion-principal" class="ft-sidebar fixed inset-y-0 left-0 z-50 flex -translate-x-full flex-col transition-transform duration-200 lg:translate-x-0" aria-label="Navegación principal" data-sidebar>
            <div class="ft-sidebar-brand flex min-h-24 items-center justify-between gap-3 px-5 py-5">
                <a href="{{ route('dashboard') }}" class="flex min-w-0 items-center gap-3 rounded-md text-white" aria-label="Facultad de Tecnología UMSA. Ir al inicio">
                    <x-ui.brand-logos :compact="true" />
                    <span class="min-w-0"><span class="block text-[.65rem] font-bold uppercase tracking-[.16em] text-blue-200">UMSA</span><span class="mt-1 block text-sm font-bold leading-tight">Facultad de Tecnología</span><span class="mt-1 block text-xs leading-tight text-blue-100">Gestión Documental</span></span>
                </a>
                <button type="button" class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-lg text-white hover:bg-white/15 lg:hidden" aria-label="Cerrar menú" data-sidebar-close>
                    <x-ui.icon name="close" />
                </button>
            </div>

            <nav class="flex-1 overflow-y-auto px-3 pb-6" aria-label="Secciones del sistema">
                <p class="ft-nav-label">INICIO</p>
                <x-ui.nav-link :href="route('dashboard')" icon="home" :active="request()->routeIs('dashboard')">Inicio</x-ui.nav-link>

                @can('viewAny', App\Models\Document::class)
                    <p class="ft-nav-label">DOCUMENTOS</p>
                    <x-ui.nav-link :href="route('documents.index')" icon="documents" :active="request()->routeIs('documents.*') && ! request()->routeIs('documents.create')">Documentos</x-ui.nav-link>
                    @if (App\Models\DocumentReviewTask::canBeAssignedTo($viewer))
                        <x-ui.nav-link :href="route('reviews.mine')" icon="list" :active="request()->routeIs('reviews.mine')">Mis tareas</x-ui.nav-link>
                    @elseif ($viewer->isResponsibleForUnit((int) $viewer->organizational_unit_id))
                        <x-ui.nav-link :href="route('reviews.unit')" icon="list" :active="request()->routeIs('reviews.unit')">Revisiones de mi unidad</x-ui.nav-link>
                    @endif
                    @can('create', App\Models\Document::class)
                        <x-ui.nav-link :href="route('documents.create')" icon="plus" :active="request()->routeIs('documents.create')">Nuevo documento</x-ui.nav-link>
                    @endcan
                @else
                    <p class="ft-nav-label">AVISOS</p>
                @endcan
                <x-ui.nav-link :href="route('notifications.index')" icon="bell" :active="request()->routeIs('notifications.*')">Notificaciones @if($navigationUnreadCount > 0)<span class="ml-auto rounded-full bg-white/20 px-2 py-0.5 text-xs">{{ $navigationUnreadCount > 99 ? '99+' : $navigationUnreadCount }}</span>@endif</x-ui.nav-link>

                @if($viewer->can('users.view') || $viewer->can('units.view') || $viewer->can('document_types.view') || $viewer->canAny(['delegations.manage_global', 'delegations.manage_unit']))
                    <p class="ft-nav-label">GESTIÓN</p>
                    @can('users.view')<x-ui.nav-link :href="route('users.index')" icon="users" :active="request()->routeIs('users.*')">Usuarios</x-ui.nav-link>@endcan
                    @can('units.view')<x-ui.nav-link :href="route('units.index')" icon="building" :active="request()->routeIs('units.*')">Carreras y unidades</x-ui.nav-link>@endcan
                    @can('document_types.view')<x-ui.nav-link :href="route('document-types.index')" icon="list" :active="request()->routeIs('document-types.*')">Tipos documentales</x-ui.nav-link>@endcan
                    @canany(['delegations.manage_global', 'delegations.manage_unit'])<x-ui.nav-link :href="route('delegations.index')" icon="calendar" :active="request()->routeIs('delegations.*')">Delegaciones</x-ui.nav-link>@endcanany
                @endif

                @if($viewer->canAny(['reports.view_global', 'reports.view_unit']) || $viewer->can('audit.view'))
                    <p class="ft-nav-label">ANÁLISIS</p>
                    @canany(['reports.view_global', 'reports.view_unit'])<x-ui.nav-link :href="route('reports.documents.index')" icon="chart" :active="request()->routeIs('reports.*')">Reportes</x-ui.nav-link>@endcanany
                    @can('audit.view')<x-ui.nav-link :href="route('audit.index')" icon="shield" :active="request()->routeIs('audit.*')">Auditoría</x-ui.nav-link>@endcan
                @endif
            </nav>

            <div class="border-t border-white/15 px-5 py-4 text-xs leading-5 text-blue-100">
                <span class="block font-semibold text-white">Facultad de Tecnología</span>
                Universidad Mayor de San Andrés
            </div>
        </aside>

        <div class="flex min-h-screen min-w-0 flex-col lg:pl-[16.75rem]" data-workspace-content>
            <header class="ft-header sticky top-0 z-30 flex items-center justify-between gap-3 px-4 sm:px-7">
                <div class="flex min-w-0 items-center gap-3">
                    <button type="button" class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-lg border border-slate-200 text-slate-800 hover:bg-slate-50 lg:hidden" aria-controls="navegacion-principal" aria-expanded="false" aria-label="Abrir menú" data-sidebar-open>
                        <x-ui.icon name="menu" />
                    </button>
                    <div class="min-w-0">
                        <p class="truncate text-sm font-bold text-slate-900 sm:text-base">{{ $moduleTitle }}</p>
                        @if($activeCareer)
                            <p class="mt-0.5 flex items-center gap-1.5 truncate text-xs text-slate-600"><span class="ft-career-dot" aria-hidden="true"></span> {{ $careerUnit && request()->routeIs('documents.show') ? 'Origen: ' : ($activeCareer->type === App\Enums\OrganizationalUnitType::CAREER ? 'Carrera de ' : '') }}{{ $activeCareer->name }}</p>
                        @else
                            <p class="mt-0.5 hidden text-xs text-slate-500 sm:block">Facultad de Tecnología · UMSA</p>
                        @endif
                    </div>
                </div>

                <div class="flex shrink-0 items-center gap-2 sm:gap-4">
                    <a href="{{ route('notifications.index') }}" class="relative inline-flex min-h-11 min-w-11 items-center justify-center rounded-lg text-slate-700 hover:bg-slate-100" aria-label="Notificaciones: {{ $navigationUnreadCount }} sin leer">
                        <x-ui.icon name="bell" />
                        @if($navigationUnreadCount > 0)<span class="absolute -right-0.5 -top-0.5 rounded-full bg-red-700 px-1.5 py-0.5 text-[.65rem] font-bold text-white">{{ $navigationUnreadCount > 99 ? '99+' : $navigationUnreadCount }}</span>@endif
                    </a>
                    <details class="relative" data-profile-menu>
                        <summary class="flex min-h-11 cursor-pointer list-none items-center gap-2 rounded-lg px-1.5 hover:bg-slate-100 sm:gap-3">
                            <span class="grid h-9 w-9 place-items-center rounded-full bg-institutional-100 text-sm font-bold text-institutional-900" aria-hidden="true">{{ mb_strtoupper(mb_substr($viewer->name, 0, 1)) }}</span>
                            <span class="hidden max-w-36 text-left sm:block"><span class="block truncate text-xs font-bold text-slate-900">{{ $viewer->name }}</span><span class="block truncate text-xs text-slate-600">{{ $role?->label() ?? 'Usuario' }}</span></span>
                            <span class="sr-only">Menú de {{ $viewer->name }}</span>
                        </summary>
                        <div class="absolute right-0 z-50 mt-2 w-64 rounded-xl border border-slate-200 bg-white p-3 shadow-xl">
                            <p class="break-words px-2 text-sm font-bold text-slate-900">{{ $viewer->name }}</p>
                            <p class="px-2 text-xs text-slate-600">{{ $role?->label() ?? 'Usuario' }}</p>
                            <p class="mt-1 break-all px-2 text-xs text-slate-500">{{ $viewer->email }}</p>
                            <form method="POST" action="{{ route('logout') }}" class="mt-3 border-t border-slate-200 pt-3">
                                @csrf
                                <button type="submit" class="ft-button-secondary w-full">Cerrar sesión</button>
                            </form>
                        </div>
                    </details>
                </div>
            </header>

            <main id="contenido-principal" class="ft-page flex-1" tabindex="-1">
                @if ($section)
                    <nav class="ft-breadcrumb" aria-label="Ubicación actual">
                        <ol>
                            <li><a href="{{ route('dashboard') }}">Inicio</a></li>
                            <li aria-hidden="true">/</li>
                            @if (url()->current() !== $section[1])
                                <li><a href="{{ $section[1] }}">{{ $section[0] }}</a></li>
                                <li aria-hidden="true">/</li>
                                <li aria-current="page" class="truncate">{{ $moduleTitle }}</li>
                            @else
                                <li aria-current="page">{{ $section[0] }}</li>
                            @endif
                        </ol>
                    </nav>
                @endif
                @if (session('status'))
                    <div role="status" class="ft-alert ft-alert-success mb-6" data-status-message><span class="font-bold">Operación completada.</span> {{ session('status') }}</div>
                @endif
                {{ $slot }}
            </main>
            <footer class="ft-workspace-footer"><span>Facultad de Tecnología · UMSA</span><span>Gestión documental institucional</span></footer>
        </div>
    </div>
</x-layouts.app>
