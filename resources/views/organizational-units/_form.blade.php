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
        <h2 class="ft-form-section-title">Identificación de la carrera</h2>
        <p class="ft-field-help">El nombre y la sigla son obligatorios. Revise la sigla antes de guardar: forma parte de los códigos de documentos futuros.</p>
    </div>
    <div>
        <label for="name" class="block text-sm font-semibold text-slate-800">Nombre de la carrera</label>
        <input id="name" name="name" type="text" value="{{ old('name', $unit?->name) }}" required maxlength="160" autocomplete="organization" @class([
            'mt-2 block min-h-12 w-full rounded-lg border bg-white px-3.5 py-2.5 text-base text-slate-950 shadow-sm focus:ring-2 focus:ring-blue-600/20',
            'border-red-400' => $errors->has('name'),
            'border-slate-300 focus:border-blue-600' => ! $errors->has('name'),
        ]) aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}" @error('name') aria-describedby="name-error" @enderror>
        @error('name')<p id="name-error" class="ft-field-error">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="acronym" class="block text-sm font-semibold text-slate-800">Sigla</label>
        <input id="acronym" name="acronym" type="text" value="{{ old('acronym', $unit?->acronym) }}" required maxlength="20" autocapitalize="characters" spellcheck="false" class="mt-2 block min-h-12 w-full rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-base uppercase text-slate-950 shadow-sm focus:border-blue-600 focus:ring-2 focus:ring-blue-600/20" aria-describedby="acronym-help{{ $errors->has('acronym') ? ' acronym-error' : '' }}" aria-invalid="{{ $errors->has('acronym') ? 'true' : 'false' }}">
        <p id="acronym-help" class="ft-field-help">La sigla se guardará en mayúsculas. Si la cambia, solo los documentos enviados en adelante utilizarán la nueva sigla; los códigos históricos permanecerán iguales.</p>
        @error('acronym')<p id="acronym-error" class="ft-field-error">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="description" class="block text-sm font-semibold text-slate-800">Descripción <span class="font-normal text-slate-500">(opcional)</span></label>
        <textarea id="description" name="description" rows="4" maxlength="2000" class="mt-2 block w-full rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-base text-slate-950 shadow-sm focus:border-blue-600 focus:ring-2 focus:ring-blue-600/20" aria-invalid="{{ $errors->has('description') ? 'true' : 'false' }}" aria-describedby="description-help{{ $errors->has('description') ? ' description-error' : '' }}">{{ old('description', $unit?->description) }}</textarea>
        <p id="description-help" class="ft-field-help">Puede añadir una breve referencia institucional. Máximo 2.000 caracteres.</p>
        @error('description')<p id="description-error" class="ft-field-error">{{ $message }}</p>@enderror
    </div>

    <div class="ft-alert ft-alert-info">
        <span class="font-semibold">Pertenece a:</span> {{ $faculty->name }}.
        Las nuevas carreras se crean activas y pueden desactivarse posteriormente sin perder historial.
    </div>
</div>

<div class="ft-form-actions">
    <a href="{{ $cancelUrl }}" class="ft-button-secondary">Cancelar</a>
    <button type="submit" class="ft-button-primary">{{ $submitLabel }}</button>
</div>
