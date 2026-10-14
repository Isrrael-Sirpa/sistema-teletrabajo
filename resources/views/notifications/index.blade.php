<x-layouts.authenticated title="Notificaciones">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <p class="text-sm font-semibold text-blue-700">Centro de notificaciones</p>
            <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-950 sm:text-3xl">Notificaciones internas</h1>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">Avisos relacionados con documentos en los que participa. Una notificación no reemplaza la autorización del documento.</p>
        </div>

        @if ($unreadNotifications->total() > 0)
            <form method="POST" action="{{ route('notifications.read-all') }}">
                @csrf
                @method('PATCH')
                <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-50">Marcar todas como leídas</button>
            </form>
        @endif
    </div>

    @if (session('status'))
        <div role="status" class="mt-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">{{ session('status') }}</div>
    @endif

    <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7" aria-labelledby="unread-title">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h2 id="unread-title" class="text-lg font-bold text-slate-950">No leídas</h2>
                <p class="mt-1 text-sm text-slate-600">Avisos nuevos pendientes de revisión.</p>
            </div>
            <span class="rounded-full bg-blue-100 px-3 py-1 text-xs font-bold text-blue-800">{{ $unreadNotifications->total() }}</span>
        </div>

        <div class="mt-5 space-y-3">
            @forelse ($unreadNotifications as $notification)
                @include('notifications.partials.item', ['notification' => $notification, 'unread' => true])
            @empty
                <p class="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4 py-6 text-center text-sm text-slate-600">No tiene notificaciones pendientes.</p>
            @endforelse
        </div>

        @if ($unreadNotifications->hasPages())
            <div class="mt-6 border-t border-slate-200 pt-5">{{ $unreadNotifications->links() }}</div>
        @endif
    </section>

    <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7" aria-labelledby="read-title">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h2 id="read-title" class="text-lg font-bold text-slate-950">Leídas</h2>
                <p class="mt-1 text-sm text-slate-600">Avisos que ya fueron revisados.</p>
            </div>
            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-700">{{ $readNotifications->total() }}</span>
        </div>

        <div class="mt-5 space-y-3">
            @forelse ($readNotifications as $notification)
                @include('notifications.partials.item', ['notification' => $notification, 'unread' => false])
            @empty
                <p class="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4 py-6 text-center text-sm text-slate-600">Todavía no tiene notificaciones leídas.</p>
            @endforelse
        </div>

        @if ($readNotifications->hasPages())
            <div class="mt-6 border-t border-slate-200 pt-5">{{ $readNotifications->links() }}</div>
        @endif
    </section>
</x-layouts.authenticated>
