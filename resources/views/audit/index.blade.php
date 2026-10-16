<x-layouts.authenticated title="Auditoría">
    <div class="space-y-6">
        <header>
            <p class="text-sm font-semibold uppercase tracking-wide text-blue-700">Administración</p>
            <h1 class="mt-1 text-3xl font-bold text-slate-950">Auditoría</h1>
            <p class="mt-2 max-w-3xl text-sm text-slate-600">
                Registro de acciones sensibles y administrativas. El historial del ciclo documental permanece separado.
            </p>
        </header>

        <form method="GET" action="{{ route('audit.index') }}" class="ft-card p-4 sm:p-5" aria-label="Filtros de auditoría">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <label class="text-sm font-medium text-slate-700">
                    Desde
                    <input type="date" name="from" value="{{ request('from') }}" class="mt-1 min-h-11 w-full rounded-lg border-slate-300">
                </label>
                <label class="text-sm font-medium text-slate-700">
                    Hasta
                    <input type="date" name="to" value="{{ request('to') }}" class="mt-1 min-h-11 w-full rounded-lg border-slate-300">
                </label>
                <label class="text-sm font-medium text-slate-700">
                    Actor
                    <select name="actor" class="mt-1 min-h-11 w-full rounded-lg border-slate-300">
                        <option value="">Todos</option>
                        @foreach ($actors as $actor)
                            <option value="{{ $actor->id }}" @selected((string) request('actor') === (string) $actor->id)>
                                {{ $actor->name }} — {{ $actor->email }}
                            </option>
                        @endforeach
                    </select>
                </label>
                <label class="text-sm font-medium text-slate-700">
                    Acción
                    <select name="action" class="mt-1 min-h-11 w-full rounded-lg border-slate-300">
                        <option value="">Todas</option>
                        @foreach ($actions as $action)
                            <option value="{{ $action->value }}" @selected(request('action') === $action->value)>{{ $action->label() }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-sm font-medium text-slate-700">
                    Recurso
                    <select name="resource" class="mt-1 min-h-11 w-full rounded-lg border-slate-300">
                        <option value="">Todos</option>
                        @foreach ($resourceTypes as $value => $label)
                            <option value="{{ $value }}" @selected(request('resource') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-sm font-medium text-slate-700">
                    Resultado
                    <select name="result" class="mt-1 min-h-11 w-full rounded-lg border-slate-300">
                        <option value="">Todos</option>
                        @foreach ($results as $result)
                            <option value="{{ $result->value }}" @selected(request('result') === $result->value)>{{ $result->label() }}</option>
                        @endforeach
                    </select>
                </label>
            </div>
            <div class="mt-4 flex flex-wrap gap-3">
                <button type="submit" class="ft-button-primary">Aplicar filtros</button>
                <a href="{{ route('audit.index') }}" class="ft-button-secondary">Limpiar</a>
            </div>
        </form>

        <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm" aria-label="Registros de auditoría">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-600">
                        <tr>
                            <th class="px-4 py-3">Fecha y hora</th>
                            <th class="px-4 py-3">Actor</th>
                            <th class="px-4 py-3">Acción</th>
                            <th class="px-4 py-3">Recurso</th>
                            <th class="px-4 py-3">Resultado</th>
                            <th class="px-4 py-3">IP</th>
                            <th class="px-4 py-3"><span class="sr-only">Detalle</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($logs as $log)
                            <tr class="align-top">
                                <td class="whitespace-nowrap px-4 py-4 text-slate-700">{{ $log->occurred_at->timezone(config('app.timezone'))->format('d/m/Y H:i:s') }}</td>
                                <td class="px-4 py-4">
                                    <span class="font-medium text-slate-900">{{ $log->actor?->name ?? 'Sistema / visitante' }}</span>
                                    @if ($log->actor)<span class="block text-xs text-slate-500">{{ $log->actor->email }}</span>@endif
                                </td>
                                <td class="px-4 py-4 text-slate-800">{{ $log->action->label() }}</td>
                                <td class="px-4 py-4 text-slate-700">
                                    {{ $resourceTypes[$resourceNames[$log->auditable_type] ?? ''] ?? 'Sin recurso' }}
                                    @if ($log->auditable_id)<span class="sr-only">Identificador interno {{ $log->auditable_id }}</span>@endif
                                </td>
                                <td class="px-4 py-4">
                                    @php($result = $log->result())
                                    <span @class([
                                        'inline-flex rounded-full px-2 py-1 text-xs font-semibold',
                                        'bg-emerald-100 text-emerald-800' => $result->value === 'success',
                                        'bg-amber-100 text-amber-800' => $result->value === 'denied',
                                        'bg-red-100 text-red-800' => $result->value === 'failed',
                                    ])>{{ $result->label() }}</span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-4 text-slate-700">{{ $log->ip_address ?? 'No disponible' }}</td>
                                <td class="px-4 py-4">
                                    @if ($log->old_values || $log->new_values || $log->user_agent)
                                        <details class="min-w-64">
                                            <summary class="cursor-pointer font-semibold text-blue-800">Ver detalle seguro</summary>
                                            <div class="mt-3 space-y-3 rounded-lg bg-slate-50 p-3 text-xs text-slate-700">
                                                @foreach (['old_values' => 'Valores anteriores', 'new_values' => 'Valores nuevos'] as $field => $heading)
                                                    @if ($log->{$field})
                                                        <div>
                                                            <p class="font-semibold text-slate-900">{{ $heading }}</p>
                                                            <dl class="mt-1 space-y-1">
                                                                @foreach ($log->{$field} as $key => $value)
                                                                    <div class="grid grid-cols-[minmax(7rem,auto)_1fr] gap-2">
                                                                        <dt>{{ str($key)->replace('_', ' ')->headline() }}</dt>
                                                                        <dd class="break-words font-medium">{{ is_array($value) ? implode(', ', array_map(fn ($item) => is_scalar($item) ? (string) $item : '[dato]', $value)) : (is_bool($value) ? ($value ? 'Sí' : 'No') : ($value ?? 'Sin valor')) }}</dd>
                                                                    </div>
                                                                @endforeach
                                                            </dl>
                                                        </div>
                                                    @endif
                                                @endforeach
                                                @if ($log->user_agent)
                                                    <div><span class="font-semibold text-slate-900">Agente:</span> <span class="break-words">{{ $log->user_agent }}</span></div>
                                                @endif
                                                <div><span class="font-semibold text-slate-900">Solicitud:</span> {{ $log->request_id }}</div>
                                            </div>
                                        </details>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-4 py-10 text-center text-slate-600"><span class="block font-bold text-slate-800">No hay registros para esta búsqueda.</span><span class="mt-1 block">Ajuste el período o los filtros para ampliar los resultados.</span></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($logs->hasPages())
                <div class="border-t border-slate-200 px-4 py-4">{{ $logs->links() }}</div>
            @endif
        </section>
    </div>
</x-layouts.authenticated>
