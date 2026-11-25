<x-layouts.authenticated title="Asignar actividad">
    <div class="ft-page-header">
        <h1 class="ft-page-title">Asignar actividad</h1>
        <a href="{{ route('activities.index') }}" class="ft-button-secondary">Volver</a>
    </div>

    @if($errors->any())
        <div role="alert" class="ft-alert ft-alert-error mt-6"><p class="font-bold">Revise los datos indicados.</p><ul class="mt-2 list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <form method="POST" action="{{ route('activities.store') }}" enctype="multipart/form-data" class="ft-card mt-6 max-w-4xl space-y-6 p-5 sm:p-7">
        @csrf
        @if ($canSelectTargetUnit)
            <div><label for="target_unit_id" class="block text-sm font-bold text-slate-800">Unidad responsable</label><select id="target_unit_id" name="target_unit_id" class="mt-1 block min-h-11 w-full rounded-lg border-slate-300">@foreach($targetUnits as $unit)<option value="{{ $unit->id }}" @selected((int) old('target_unit_id', $targetUnitId) === $unit->id)>{{ $unit->name }}</option>@endforeach</select>@error('target_unit_id')<p class="ft-field-error">{{ $message }}</p>@enderror</div>
        @else
            <input type="hidden" name="target_unit_id" value="{{ $targetUnitId }}">
            <div><p class="text-sm font-bold text-slate-800">Unidad responsable</p><p class="mt-1 text-slate-700">{{ $targetUnits->first()?->name }}</p></div>
        @endif
        @if($canSelectTargetUnit)<p class="-mt-4 text-sm text-slate-600">Seleccione la unidad a la que pertenece la persona responsable. Luego se mostrará su personal activo.</p>@endif
        <div>
            <label for="title" class="block text-sm font-bold text-slate-800">Título de la actividad</label>
            <input id="title" name="title" value="{{ old('title') }}" required maxlength="180" class="mt-1 block min-h-11 w-full rounded-lg border-slate-300" placeholder="Ej.: Redactar nota de respuesta al informe recibido">
        </div>
        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <label for="activity_type" class="block text-sm font-bold text-slate-800">Tipo de actividad</label>
                <select id="activity_type" name="activity_type" required class="mt-1 block min-h-11 w-full rounded-lg border-slate-300">
                    <option value="">Seleccione</option>
                    @foreach(App\Enums\ActivityType::cases() as $type)<option value="{{ $type->value }}" @selected(old('activity_type') === $type->value)>{{ $type->label() }}</option>@endforeach
                </select>
            </div>
            <div>
                <label for="document_public_id" class="block text-sm font-bold text-slate-800">Correspondencia relacionada <span class="font-normal text-slate-500">(opcional)</span></label>
                <select id="document_public_id" name="document_public_id" class="mt-1 block min-h-11 w-full rounded-lg border-slate-300">
                    <option value="">Actividad interna, sin correspondencia</option>
                    @foreach($documents as $document)<option value="{{ $document->public_id }}" @selected(old('document_public_id', $selectedDocument?->public_id) === $document->public_id)>{{ $document->code ?? 'Sin cite' }} — {{ Str::limit($document->subject, 70) }}</option>@endforeach
                </select>
            </div>
        </div>
        <div>
            <label for="instructions" class="block text-sm font-bold text-slate-800">Indicaciones</label>
            <textarea id="instructions" name="instructions" required maxlength="5000" rows="5" class="mt-1 block w-full rounded-lg border-slate-300" placeholder="Explique qué debe realizar y qué antecedentes debe considerar.">{{ old('instructions') }}</textarea>
        </div>
        <div><label for="files" class="block text-sm font-bold text-slate-800">Archivos de respaldo <span class="font-normal text-slate-500">(opcional)</span></label><input id="files" type="file" name="files[]" multiple class="mt-1 block w-full rounded-lg border border-blue-300 bg-blue-50 p-2 text-sm file:mr-3 file:rounded-md file:border-0 file:bg-blue-700 file:px-3 file:py-2 file:font-semibold file:text-white hover:file:bg-blue-800"></div>
        <div class="grid gap-5 sm:grid-cols-2">
            <div><label for="priority" class="block text-sm font-bold text-slate-800">Prioridad</label><select id="priority" name="priority" required class="mt-1 block min-h-11 w-full rounded-lg border-slate-300">@foreach(App\Enums\ActivityPriority::cases() as $priority)<option value="{{ $priority->value }}" @selected(old('priority', 'normal') === $priority->value)>{{ $priority->label() }}</option>@endforeach</select></div>
            <div><label for="due_at" class="block text-sm font-bold text-slate-800">Fecha límite <span class="font-normal text-slate-500">(opcional)</span></label><input id="due_at" type="date" name="due_at" value="{{ old('due_at') }}" min="{{ now()->format('Y-m-d') }}" class="mt-1 block min-h-11 w-full rounded-lg border-slate-300"></div>
        </div>
        <fieldset>
            <legend class="text-sm font-bold text-slate-800">Personal responsable</legend>
            <p class="mt-1 text-sm text-slate-600">Marque a quienes participarán y elija una persona responsable de presentar el resultado.</p>
            <div class="mt-3 space-y-2">
                @forelse($team as $person)
                    <div data-activity-member-unit="{{ $person->organizational_unit_id }}" class="flex flex-wrap items-center gap-4 rounded-lg border border-slate-200 p-3" @if((int) $person->organizational_unit_id !== $targetUnitId) hidden @endif>
                        <label class="flex min-h-11 flex-1 items-center gap-3"><input type="checkbox" name="assignees[]" value="{{ $person->id }}" @checked(in_array($person->id, old('assignees', [$defaultPrimaryByUnit[$targetUnitId] ?? 0]))) class="h-5 w-5 rounded border-slate-400"> <span><span class="font-bold text-slate-900">{{ $person->displayName() }}</span><span class="block text-xs text-slate-600">{{ $person->institutionalPosition() }}</span></span></label>
                        <label class="flex min-h-11 items-center gap-2 text-sm font-semibold"><input type="radio" name="primary_assignee_id" value="{{ $person->id }}" data-default-primary="{{ ($defaultPrimaryByUnit[$person->organizational_unit_id] ?? null) === $person->id ? 'true' : 'false' }}" @checked((int) old('primary_assignee_id', $defaultPrimaryByUnit[$targetUnitId] ?? 0) === $person->id) class="h-5 w-5"> Responsable principal</label>
                    </div>
                @empty
                    <p class="ft-alert ft-alert-info">No hay personal activo disponible en esta unidad.</p>
                @endforelse
            </div>
        </fieldset>
        <button type="submit" class="ft-button-primary" @disabled($team->isEmpty())>Asignar actividad</button>
    </form>
    @if($canSelectTargetUnit)<script>document.getElementById('target_unit_id').addEventListener('change',function(){document.querySelectorAll('[data-activity-member-unit]').forEach(function(member){var visible=member.dataset.activityMemberUnit===this.value;member.hidden=!visible;member.querySelectorAll('input').forEach(function(input){input.disabled=!visible;if(!visible){input.checked=false;}});if(visible){var primary=member.querySelector('[data-default-primary="true"]');if(primary){primary.checked=true;member.querySelector('input[type="checkbox"]').checked=true;}}},this);});</script>@endif
</x-layouts.authenticated>
