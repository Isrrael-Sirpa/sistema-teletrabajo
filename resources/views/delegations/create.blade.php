<x-layouts.authenticated title="Nueva delegación temporal">
    <div class="mx-auto max-w-4xl">
        <a href="{{ route('delegations.index') }}" class="text-sm font-semibold text-blue-800 hover:underline">← Volver a delegaciones</a>
        <h1 class="mt-3 text-3xl font-bold text-slate-950">Nueva delegación de Jefatura</h1>
        <p class="mt-2 text-sm text-slate-600">El inicio es inclusivo y el fin exclusivo. La Secretaria conservará su rol permanente.</p>

        <form method="POST" action="{{ route('delegations.store') }}" class="mt-6 space-y-6 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            @csrf
            <div class="grid gap-4 sm:grid-cols-2">
                @if ($fixedHead)
                    <input type="hidden" name="organizational_unit_id" value="{{ $fixedHead->organizational_unit_id }}">
                    <input type="hidden" name="delegator_user_id" value="{{ $fixedHead->id }}">
                    <div><p class="text-sm text-slate-500">Carrera</p><p class="font-semibold">{{ $fixedHead->organizationalUnit->name }}</p></div>
                    <div><p class="text-sm text-slate-500">Jefe titular</p><p class="font-semibold">{{ $fixedHead->name }}</p></div>
                @else
                    <label class="text-sm font-semibold text-slate-700">Carrera
                        <select name="organizational_unit_id" required class="mt-1 min-h-11 w-full rounded-lg border-slate-300">
                            <option value="">Seleccione</option>@foreach($careers as $career)<option value="{{ $career->id }}" @selected(old('organizational_unit_id') == $career->id)>{{ $career->name }}</option>@endforeach
                        </select>
                    </label>
                    <label class="text-sm font-semibold text-slate-700">Jefe titular
                        <select name="delegator_user_id" required class="mt-1 min-h-11 w-full rounded-lg border-slate-300">
                            <option value="">Seleccione</option>@foreach($heads as $head)<option value="{{ $head->id }}" @selected(old('delegator_user_id') == $head->id)>{{ $head->name }} — {{ $head->organizationalUnit?->name }}</option>@endforeach
                        </select>
                    </label>
                @endif
                <label class="text-sm font-semibold text-slate-700">Secretaria delegada
                    <select name="delegate_user_id" required class="mt-1 min-h-11 w-full rounded-lg border-slate-300">
                        <option value="">Seleccione</option>@foreach($secretaries as $secretary)<option value="{{ $secretary->id }}" @selected(old('delegate_user_id') == $secretary->id)>{{ $secretary->name }} — {{ $secretary->organizationalUnit?->name }}</option>@endforeach
                    </select>
                </label>
                <label class="text-sm font-semibold text-slate-700">Inicio
                    <input type="datetime-local" name="starts_at" value="{{ old('starts_at') }}" required class="mt-1 min-h-11 w-full rounded-lg border-slate-300">
                </label>
                <label class="text-sm font-semibold text-slate-700">Fin
                    <input type="datetime-local" name="ends_at" value="{{ old('ends_at') }}" required class="mt-1 min-h-11 w-full rounded-lg border-slate-300">
                </label>
            </div>
            <label class="block text-sm font-semibold text-slate-700">Motivo institucional
                <textarea name="reason" required maxlength="2000" rows="3" class="mt-1 w-full rounded-lg border-slate-300">{{ old('reason') }}</textarea>
            </label>
            <fieldset>
                <legend class="font-bold text-slate-900">Capacidades delegadas</legend>
                <p class="mt-1 text-sm text-slate-600">Seleccione solo las funciones necesarias durante la sustitución.</p>
                <div class="mt-3 grid gap-2 sm:grid-cols-2">
                    @foreach($capabilities as $capability)
                        <label class="flex items-start gap-3 rounded-lg border border-slate-200 p-3 text-sm">
                            <input type="checkbox" name="permissions[]" value="{{ $capability->value }}" @checked(in_array($capability->value, old('permissions', []), true)) class="mt-1 rounded border-slate-300">
                            <span>{{ $capability->label() }}</span>
                        </label>
                    @endforeach
                </div>
            </fieldset>
            @if ($errors->any())<div class="rounded-lg bg-red-50 p-4 text-sm text-red-900">{{ $errors->first() }}</div>@endif
            <button class="min-h-11 rounded-lg bg-blue-800 px-5 py-2 font-bold text-white hover:bg-blue-900">Registrar delegación</button>
        </form>
    </div>
</x-layouts.authenticated>
