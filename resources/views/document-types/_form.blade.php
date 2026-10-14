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
        <label for="name" class="block text-sm font-semibold text-slate-800">Nombre del tipo documental</label>
        <input id="name" name="name" type="text" value="{{ old('name', $documentType?->name) }}" required maxlength="120" autocomplete="off" class="mt-2 block min-h-12 w-full rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-base text-slate-950 shadow-sm focus:border-blue-600 focus:ring-2 focus:ring-blue-600/20" aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}">
        @error('name')<p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="description" class="block text-sm font-semibold text-slate-800">Descripción <span class="font-normal text-slate-500">(opcional)</span></label>
        <textarea id="description" name="description" rows="5" maxlength="2000" class="mt-2 block w-full rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-base text-slate-950 shadow-sm focus:border-blue-600 focus:ring-2 focus:ring-blue-600/20" aria-invalid="{{ $errors->has('description') ? 'true' : 'false' }}">{{ old('description', $documentType?->description) }}</textarea>
        <p class="mt-1.5 text-xs text-slate-500">Explique brevemente cuándo corresponde utilizar este tipo.</p>
        @error('description')<p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>@enderror
    </div>

    @if ($documentType)
        <p class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
            El código interno permanece estable aunque cambie el nombre. El estado se modifica mediante las acciones Activar o Desactivar.
        </p>
    @else
        <p class="rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-900">
            El nuevo tipo se creará activo. Su código interno estable se generará automáticamente.
        </p>
    @endif
</div>

<div class="mt-7 flex flex-col-reverse gap-3 border-t border-slate-200 pt-6 sm:flex-row sm:justify-end">
    <a href="{{ $cancelUrl }}" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancelar</a>
    <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-institutional-700 px-5 py-2.5 text-sm font-bold text-white transition hover:bg-institutional-900">{{ $submitLabel }}</button>
</div>
