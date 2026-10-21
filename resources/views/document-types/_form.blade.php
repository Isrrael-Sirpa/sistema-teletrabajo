@if ($errors->any())
    <div role="alert" class="ft-alert ft-alert-error mb-6" tabindex="-1" data-form-errors>
        <p class="font-semibold">Revise los datos ingresados.</p>
        <ul class="mt-1 list-disc space-y-1 pl-5">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="space-y-5">
    <div class="ft-form-section">
        <h2 class="ft-form-section-title">Datos del tipo documental</h2>
        <p class="ft-field-help">El nombre es obligatorio. Use una denominación que resulte fácil de reconocer al crear un documento.</p>
    </div>
    <div>
        <label for="name" class="block text-sm font-semibold text-slate-800">Nombre del tipo documental</label>
        <input id="name" name="name" type="text" value="{{ old('name', $documentType?->name) }}" required maxlength="120" autocomplete="off" class="mt-2 block min-h-12 w-full rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-base text-slate-950 shadow-sm focus:border-blue-600 focus:ring-2 focus:ring-blue-600/20" aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}" @error('name') aria-describedby="name-error" @enderror>
        @error('name')<p id="name-error" class="ft-field-error">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="description" class="block text-sm font-semibold text-slate-800">Descripción <span class="font-normal text-slate-500">(opcional)</span></label>
        <textarea id="description" name="description" rows="5" maxlength="2000" class="mt-2 block w-full rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-base text-slate-950 shadow-sm focus:border-blue-600 focus:ring-2 focus:ring-blue-600/20" aria-invalid="{{ $errors->has('description') ? 'true' : 'false' }}" aria-describedby="description-help{{ $errors->has('description') ? ' description-error' : '' }}">{{ old('description', $documentType?->description) }}</textarea>
        <p id="description-help" class="ft-field-help">Explique brevemente cuándo corresponde utilizar este tipo. Máximo 2.000 caracteres.</p>
        @error('description')<p id="description-error" class="ft-field-error">{{ $message }}</p>@enderror
    </div>

    @if ($documentType)
        <p class="ft-alert ft-alert-info">
            El código interno permanece estable aunque cambie el nombre. El estado se modifica mediante las acciones Activar o Desactivar.
        </p>
    @else
        <p class="ft-alert ft-alert-info">
            El nuevo tipo se creará activo. Su código interno estable se generará automáticamente.
        </p>
    @endif
</div>

<div class="ft-form-actions">
    <a href="{{ $cancelUrl }}" class="ft-button-secondary">Cancelar</a>
    <button type="submit" class="ft-button-primary">{{ $submitLabel }}</button>
</div>
