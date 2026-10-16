@php
    $routeName = data_get($notification->data, 'route.name');
    $routeParameters = data_get($notification->data, 'route.parameters', []);
    $documentUrl = $routeName === 'documents.show' && is_array($routeParameters)
        ? route($routeName, $routeParameters)
        : null;
    $occurredAt = filled(data_get($notification->data, 'occurred_at'))
        ? \Illuminate\Support\Carbon::parse(data_get($notification->data, 'occurred_at'))->timezone('America/La_Paz')
        : $notification->created_at->timezone('America/La_Paz');
@endphp

<article @class([
    'rounded-xl border p-4 sm:p-5',
    'border-l-4 border-l-[var(--career-primary)] border-[var(--ft-border)] bg-[var(--career-background)]' => $unread,
    'border-slate-200 bg-white' => ! $unread,
])>
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">@if($unread)<span class="ft-badge bg-blue-100 text-blue-900">Nueva</span>@endif<h3 class="font-bold text-slate-950">{{ data_get($notification->data, 'title', 'Notificación documental') }}</h3></div>
            <p class="mt-1 text-sm leading-6 text-slate-700">{{ data_get($notification->data, 'message', 'Existe una actualización documental.') }}</p>
            <time datetime="{{ $occurredAt->toIso8601String() }}" class="mt-2 block text-xs font-semibold text-slate-500">{{ $occurredAt->format('d/m/Y H:i:s') }}</time>
        </div>

        <div class="flex shrink-0 flex-wrap gap-2">
            @if ($documentUrl)
                <a href="{{ $documentUrl }}" class="ft-button-primary">Abrir documento</a>
            @endif
            @if ($unread)
                <form method="POST" action="{{ route('notifications.read', $notification->id) }}">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="inline-flex min-h-10 items-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-bold text-slate-700 hover:bg-slate-50">Marcar como leída</button>
                </form>
            @endif
        </div>
    </div>
</article>
