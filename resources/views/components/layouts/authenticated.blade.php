@props(['title' => null, 'unreadNotificationCount' => null])

<x-layouts.app :title="$title ?? config('app.name')">
    <div class="min-h-screen bg-slate-100">
        <header class="border-b border-blue-950 bg-institutional-900 text-white">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="flex flex-wrap items-center justify-between gap-4 py-4">
                    <a href="{{ route('dashboard') }}" class="rounded-sm">
                        <p class="text-xs font-semibold uppercase tracking-wider text-blue-200">{{ config('institution.faculty') }}</p>
                        <p class="mt-1 font-bold">Sistema de Gestión Documental</p>
                    </a>

                    <div class="flex items-center gap-3">
                        <span class="hidden text-sm text-blue-100 sm:inline">{{ auth()->user()->name }}</span>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="min-h-11 rounded-lg border border-white/30 px-4 py-2 text-sm font-semibold text-white transition hover:bg-white/10">
                                Cerrar sesión
                            </button>
                        </form>
                    </div>
                </div>

                <nav aria-label="Navegación principal" class="flex gap-1 overflow-x-auto pb-3">
                    <a href="{{ route('dashboard') }}" @class([
                        'min-h-10 whitespace-nowrap rounded-lg px-3 py-2 text-sm font-semibold transition',
                        'bg-white text-blue-950' => request()->routeIs('dashboard'),
                        'text-blue-100 hover:bg-white/10 hover:text-white' => ! request()->routeIs('dashboard'),
                    ])>
                        Inicio
                    </a>
                    @php($navigationUnreadCount = $unreadNotificationCount ?? auth()->user()->unreadNotifications()->count())
                    <a href="{{ route('notifications.index') }}" @class([
                        'min-h-10 whitespace-nowrap rounded-lg px-3 py-2 text-sm font-semibold transition',
                        'bg-white text-blue-950' => request()->routeIs('notifications.*'),
                        'text-blue-100 hover:bg-white/10 hover:text-white' => ! request()->routeIs('notifications.*'),
                    ]) aria-label="Notificaciones: {{ $navigationUnreadCount }} sin leer">
                        Notificaciones ({{ $navigationUnreadCount > 99 ? '99+' : $navigationUnreadCount }})
                        @if ($navigationUnreadCount > 0)
                            <span class="sr-only">pendientes</span>
                        @endif
                    </a>
                    @can('viewAny', App\Models\Document::class)
                        <a href="{{ route('documents.index') }}" @class([
                            'min-h-10 whitespace-nowrap rounded-lg px-3 py-2 text-sm font-semibold transition',
                            'bg-white text-blue-950' => request()->routeIs('documents.*'),
                            'text-blue-100 hover:bg-white/10 hover:text-white' => ! request()->routeIs('documents.*'),
                        ])>
                            Documentos
                        </a>
                    @endcan
                    @can('units.view')
                        <a href="{{ route('units.index') }}" @class([
                            'min-h-10 whitespace-nowrap rounded-lg px-3 py-2 text-sm font-semibold transition',
                            'bg-white text-blue-950' => request()->routeIs('units.*'),
                            'text-blue-100 hover:bg-white/10 hover:text-white' => ! request()->routeIs('units.*'),
                        ])>
                            Carreras y unidades
                        </a>
                    @endcan
                    @can('users.view')
                        <a href="{{ route('users.index') }}" @class([
                            'min-h-10 whitespace-nowrap rounded-lg px-3 py-2 text-sm font-semibold transition',
                            'bg-white text-blue-950' => request()->routeIs('users.*'),
                            'text-blue-100 hover:bg-white/10 hover:text-white' => ! request()->routeIs('users.*'),
                        ])>
                            Usuarios
                        </a>
                    @endcan
                    @can('document_types.view')
                        <a href="{{ route('document-types.index') }}" @class([
                            'min-h-10 whitespace-nowrap rounded-lg px-3 py-2 text-sm font-semibold transition',
                            'bg-white text-blue-950' => request()->routeIs('document-types.*'),
                            'text-blue-100 hover:bg-white/10 hover:text-white' => ! request()->routeIs('document-types.*'),
                        ])>
                            Tipos documentales
                        </a>
                    @endcan
                    @can('audit.view')
                        <a href="{{ route('audit.index') }}" @class([
                            'min-h-10 whitespace-nowrap rounded-lg px-3 py-2 text-sm font-semibold transition',
                            'bg-white text-blue-950' => request()->routeIs('audit.*'),
                            'text-blue-100 hover:bg-white/10 hover:text-white' => ! request()->routeIs('audit.*'),
                        ])>
                            Auditoría
                        </a>
                    @endcan
                    @canany(['reports.view_global', 'reports.view_unit'])
                        <a href="{{ route('reports.documents.index') }}" @class([
                            'min-h-10 whitespace-nowrap rounded-lg px-3 py-2 text-sm font-semibold transition',
                            'bg-white text-blue-950' => request()->routeIs('reports.*'),
                            'text-blue-100 hover:bg-white/10 hover:text-white' => ! request()->routeIs('reports.*'),
                        ])>
                            Reportes
                        </a>
                    @endcanany
                    @canany(['delegations.manage_global', 'delegations.manage_unit'])
                        <a href="{{ route('delegations.index') }}" @class([
                            'min-h-10 whitespace-nowrap rounded-lg px-3 py-2 text-sm font-semibold transition',
                            'bg-white text-blue-950' => request()->routeIs('delegations.*'),
                            'text-blue-100 hover:bg-white/10 hover:text-white' => ! request()->routeIs('delegations.*'),
                        ])>Delegaciones</a>
                    @endcanany
                </nav>
            </div>
        </header>

        <main id="contenido-principal" class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            {{ $slot }}
        </main>
    </div>
</x-layouts.app>
