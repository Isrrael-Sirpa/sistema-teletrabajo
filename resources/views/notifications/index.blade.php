<x-layouts.authenticated title="Notificaciones">
    <header class="ft-page-header">
        <div>
            <p class="ft-eyebrow">Su actividad documental</p>
            <h1 class="ft-page-title">Notificaciones internas</h1>
            <p class="ft-page-description">Revise los avisos nuevos y abra el documento para conocer el detalle o realizar la acción que corresponda.</p>
        </div>

        @if ($unreadNotifications->total() > 0)
            <form method="POST" action="{{ route('notifications.read-all') }}">
                @csrf
                @method('PATCH')
                <button type="submit" class="ft-button-secondary">Marcar todas como leídas</button>
            </form>
        @endif
    </header>

    <p class="ft-alert ft-alert-info mt-6">Marcar un aviso como leído no recibe ni aprueba el documento. Las acciones documentales se realizan dentro de su detalle, según sus permisos.</p>

    <section class="ft-card mt-6 p-5 sm:p-7" aria-labelledby="unread-title">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h2 id="unread-title" class="text-lg font-bold text-slate-950">No leídas</h2>
                <p class="mt-1 text-sm text-slate-600">Avisos nuevos pendientes de revisión.</p>
            </div>
            <span class="ft-badge bg-blue-100 text-blue-800" aria-label="{{ $unreadNotifications->total() }} notificaciones no leídas">{{ $unreadNotifications->total() }}</span>
        </div>

        <div class="mt-5 space-y-3">
            @forelse ($unreadNotifications as $notification)
                @include('notifications.partials.item', ['notification' => $notification, 'unread' => true])
            @empty
                <div class="ft-empty-state rounded-xl bg-slate-50"><x-ui.icon name="bell" class="mx-auto mb-3 text-emerald-700" /><p class="font-bold text-slate-800">Está al día.</p><p class="mt-2 text-sm text-slate-600">No tiene notificaciones nuevas. Los próximos avisos aparecerán aquí.</p></div>
            @endforelse
        </div>

        @if ($unreadNotifications->hasPages())
            <div class="mt-6 border-t border-slate-200 pt-5">{{ $unreadNotifications->links() }}</div>
        @endif
    </section>

    <section class="ft-card mt-6 p-5 sm:p-7" aria-labelledby="read-title">
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
                <p class="ft-empty-state rounded-xl bg-slate-50 text-sm text-slate-600">Todavía no tiene notificaciones leídas. Al revisar sus avisos, podrá encontrarlos en esta sección.</p>
            @endforelse
        </div>

        @if ($readNotifications->hasPages())
            <div class="mt-6 border-t border-slate-200 pt-5">{{ $readNotifications->links() }}</div>
        @endif
    </section>
</x-layouts.authenticated>
