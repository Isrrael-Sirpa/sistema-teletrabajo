@if ($errors->any())
    <div role="alert" aria-live="polite" class="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">
        <p class="font-semibold">Revise los datos ingresados.</p>
        <ul class="mt-1 list-disc space-y-1 pl-5">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="space-y-5">
    <div>
        <label for="name" class="block text-sm font-semibold text-slate-800">Nombre de la carrera</label>
        <input id="name" name="name" type="text" value="{{ old('name', $unit?->name) }}" required maxlength="160" autocomplete="organization" @class([
            'mt-2 block min-h-12 w-full rounded-lg border bg-white px-3.5 py-2.5 text-base text-slate-950 shadow-sm focus:ring-2 focus:ring-blue-600/20',
            'border-red-400' => $errors->has('name'),
            'border-slate-300 focus:border-blue-600' => ! $errors->has('name'),
        ]) aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}">
        @error('name')<p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="acronym" class="block text-sm font-semibold text-slate-800">Sigla</label>
        <input id="acronym" name="acronym" type="text" value="{{ old('acronym', $unit?->acronym) }}" required maxlength="20" autocapitalize="characters" class="mt-2 block min-h-12 w-full rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-base uppercase text-slate-950 shadow-sm focus:border-blue-600 focus:ring-2 focus:ring-blue-600/20" aria-describedby="acronym-help" aria-invalid="{{ $errors->has('acronym') ? 'true' : 'false' }}">
        <p id="acronym-help" class="mt-1.5 text-xs text-slate-500">La sigla se guardará automáticamente en mayúsculas.</p>
        @error('acronym')<p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="description" class="block text-sm font-semibold text-slate-800">Descripción <span class="font-normal text-slate-500">(opcional)</span></label>
        <textarea id="description" name="description" rows="4" maxlength="2000" class="mt-2 block w-full rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-base text-slate-950 shadow-sm focus:border-blue-600 focus:ring-2 focus:ring-blue-600/20" aria-invalid="{{ $errors->has('description') ? 'true' : 'false' }}">{{ old('description', $unit?->description) }}</textarea>
        @error('description')<p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>@enderror
    </div>

    <div class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
        <span class="font-semibold">Unidad padre:</span> {{ $faculty->name }}.
        Las nuevas carreras se crean activas y pueden desactivarse posteriormente sin perder historial.
    </div>
</div>

<div class="mt-7 flex flex-col-reverse gap-3 border-t border-slate-200 pt-6 sm:flex-row sm:justify-end">
    <a href="{{ $cancelUrl }}" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancelar</a>
    <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-institutional-700 px-5 py-2.5 text-sm font-bold text-white transition hover:bg-institutional-900">{{ $submitLabel }}</button>
</div>
