<x-layouts.authenticated title="Nueva delegación temporal">
    <div class="mx-auto max-w-4xl">
        <header class="ft-page-header">
            <div>
                <a href="{{ route('delegations.index') }}" class="inline-flex min-h-11 items-center text-sm font-semibold text-blue-800 hover:underline">← Volver a delegaciones</a>
                <p class="ft-eyebrow">Continuidad de la atención</p>
                <h1 class="ft-page-title">Nueva delegación de Jefatura</h1>
                <p class="ft-page-description">Autorice a una Secretaria para atender funciones de Jefatura durante un período definido. Su rol permanente no cambiará.</p>
                <p class="ft-field-help">Complete todos los campos y seleccione al menos una función.</p>
            </div>
        </header>

        <form method="POST" action="{{ route('delegations.store') }}" class="mt-6 space-y-6">
            @csrf
            @if ($errors->any())
                <div role="alert" data-form-errors tabindex="-1" class="ft-alert ft-alert-error">
                    <p class="font-bold">No se pudo registrar la delegación.</p>
                    <p class="mt-1">Revise los campos indicados. Sus datos se conservaron.</p>
                    <ul class="mt-2 list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif
            <section class="ft-card ft-form-section" aria-labelledby="delegation-people-title">
                <h2 id="delegation-people-title" class="ft-form-section-title">1. Carrera y responsables</h2>
                <p class="ft-field-help">La Secretaria debe pertenecer a la misma carrera que el Jefe titular.</p>
            <div class="mt-5 grid gap-5 sm:grid-cols-2">
                @if ($fixedHead)
                    <input type="hidden" name="organizational_unit_id" value="{{ $fixedHead->organizational_unit_id }}">
                    <input type="hidden" name="delegator_user_id" value="{{ $fixedHead->id }}">
                    <div><p class="text-sm text-slate-500">Carrera</p><p class="font-semibold">{{ $fixedHead->organizationalUnit->name }}</p></div>
                    <div><p class="text-sm text-slate-500">Jefe titular</p><p class="font-semibold">{{ $fixedHead->name }}</p></div>
                @else
                    <label class="text-sm font-semibold text-slate-700">Carrera
                        <select name="organizational_unit_id" required aria-invalid="{{ $errors->has('organizational_unit_id') ? 'true' : 'false' }}" @error('organizational_unit_id') aria-describedby="delegation-career-error" @enderror class="mt-1 min-h-11 w-full rounded-lg border-slate-300">
                            <option value="">Seleccione una carrera</option>@foreach($careers as $career)<option value="{{ $career->id }}" @selected(old('organizational_unit_id') == $career->id)>{{ $career->name }}</option>@endforeach
                        </select>
                        @error('organizational_unit_id')<span id="delegation-career-error" class="ft-field-error block">{{ $message }}</span>@enderror
                    </label>
                    <label class="text-sm font-semibold text-slate-700">Jefe titular
                        <select name="delegator_user_id" required aria-invalid="{{ $errors->has('delegator_user_id') ? 'true' : 'false' }}" @error('delegator_user_id') aria-describedby="delegation-head-error" @enderror class="mt-1 min-h-11 w-full rounded-lg border-slate-300">
                            <option value="">Seleccione al Jefe titular</option>@foreach($heads as $head)<option value="{{ $head->id }}" @selected(old('delegator_user_id') == $head->id)>{{ $head->name }} — {{ $head->organizationalUnit?->name }}</option>@endforeach
                        </select>
                        @error('delegator_user_id')<span id="delegation-head-error" class="ft-field-error block">{{ $message }}</span>@enderror
                    </label>
                @endif
                <label class="text-sm font-semibold text-slate-700">Secretaria delegada
                    <select name="delegate_user_id" required aria-invalid="{{ $errors->has('delegate_user_id') ? 'true' : 'false' }}" @error('delegate_user_id') aria-describedby="delegation-secretary-error" @enderror class="mt-1 min-h-11 w-full rounded-lg border-slate-300">
                        <option value="">Seleccione a la Secretaria</option>@foreach($secretaries as $secretary)<option value="{{ $secretary->id }}" @selected(old('delegate_user_id') == $secretary->id)>{{ $secretary->name }} — {{ $secretary->organizationalUnit?->name }}</option>@endforeach
                    </select>
                    @error('delegate_user_id')<span id="delegation-secretary-error" class="ft-field-error block">{{ $message }}</span>@enderror
                </label>
            </div>
            </section>
            <section class="ft-card ft-form-section" aria-labelledby="delegation-period-title">
                <h2 id="delegation-period-title" class="ft-form-section-title">2. Vigencia y motivo</h2>
                <p id="delegation-period-help" class="ft-field-help">Use la hora de Bolivia. La autorización comienza en la fecha y hora de inicio, y termina exactamente en la fecha y hora de fin.</p>
                <div class="mt-5 grid gap-5 sm:grid-cols-2">
                <label class="text-sm font-semibold text-slate-700">Fecha y hora de inicio
                    <input type="datetime-local" name="starts_at" value="{{ old('starts_at') }}" required aria-describedby="delegation-period-help{{ $errors->has('starts_at') ? ' delegation-start-error' : '' }}" aria-invalid="{{ $errors->has('starts_at') ? 'true' : 'false' }}" class="mt-1 min-h-11 w-full rounded-lg border-slate-300">
                    @error('starts_at')<span id="delegation-start-error" class="ft-field-error block">{{ $message }}</span>@enderror
                </label>
                <label class="text-sm font-semibold text-slate-700">Fecha y hora de fin
                    <input type="datetime-local" name="ends_at" value="{{ old('ends_at') }}" required aria-describedby="delegation-period-help{{ $errors->has('ends_at') ? ' delegation-end-error' : '' }}" aria-invalid="{{ $errors->has('ends_at') ? 'true' : 'false' }}" class="mt-1 min-h-11 w-full rounded-lg border-slate-300">
                    @error('ends_at')<span id="delegation-end-error" class="ft-field-error block">{{ $message }}</span>@enderror
                </label>
            </div>
            <label class="mt-5 block text-sm font-semibold text-slate-700">Motivo institucional
                <textarea name="reason" required maxlength="2000" rows="3" aria-describedby="delegation-reason-help{{ $errors->has('reason') ? ' delegation-reason-error' : '' }}" aria-invalid="{{ $errors->has('reason') ? 'true' : 'false' }}" class="mt-1 w-full rounded-lg border-slate-300">{{ old('reason') }}</textarea>
                @error('reason')<span id="delegation-reason-error" class="ft-field-error block">{{ $message }}</span>@enderror
            </label>
            <p id="delegation-reason-help" class="ft-field-help">Explique por qué se necesita la sustitución. Máximo 2.000 caracteres.</p>
            </section>
            <fieldset class="ft-card ft-form-section">
                <legend class="ft-form-section-title">3. Funciones autorizadas</legend>
                <p class="ft-field-help">Seleccione solo las funciones necesarias durante la sustitución.</p>
                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                    @foreach($capabilities as $capability)
                        <label class="flex min-h-14 cursor-pointer items-start gap-3 rounded-xl border border-slate-200 p-4 text-sm hover:bg-slate-50">
                            <input type="checkbox" name="permissions[]" value="{{ $capability->value }}" @checked(in_array($capability->value, old('permissions', []), true)) class="mt-1 rounded border-slate-300">
                            <span>{{ $capability->label() }}</span>
                        </label>
                    @endforeach
                </div>
            </fieldset>
            <div class="ft-form-actions">
                <p class="text-sm text-slate-600">Revise responsables, fechas y funciones antes de registrar.</p>
                <div class="flex flex-wrap gap-3">
                    <a href="{{ route('delegations.index') }}" class="ft-button-secondary">Cancelar</a>
                    <button type="submit" class="ft-button-primary">Registrar delegación</button>
                </div>
            </div>
        </form>
    </div>
</x-layouts.authenticated>
