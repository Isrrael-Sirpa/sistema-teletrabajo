@php($editing = isset($document))

@if ($errors->any())
    <div role="alert" class="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">
        <p class="font-bold">Revise los datos señalados.</p>
        <ul class="mt-2 list-disc space-y-1 pl-5">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="grid gap-6 lg:grid-cols-2">
    <div class="lg:col-span-2">
        <label for="subject" class="block text-sm font-bold text-slate-800">Asunto <span class="text-red-700" aria-hidden="true">*</span></label>
        <input id="subject" name="subject" type="text" required maxlength="255" value="{{ old('subject', $document->subject ?? '') }}" class="mt-2 block min-h-11 w-full rounded-lg border-slate-300 shadow-sm focus:border-blue-600 focus:ring-blue-600" autocomplete="off">
        @error('subject')<p class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="document_type_id" class="block text-sm font-bold text-slate-800">Tipo documental <span class="text-red-700" aria-hidden="true">*</span></label>
        <select id="document_type_id" name="document_type_id" required class="mt-2 block min-h-11 w-full rounded-lg border-slate-300 shadow-sm focus:border-blue-600 focus:ring-blue-600">
            <option value="">Seleccione una opción</option>
            @foreach ($documentTypes as $documentType)
                <option value="{{ $documentType->id }}" @selected((string) old('document_type_id', $document->document_type_id ?? '') === (string) $documentType->id)>{{ $documentType->name }}</option>
            @endforeach
        </select>
        <p class="mt-2 text-xs text-slate-600">Solo se muestran tipos documentales activos.</p>
        @error('document_type_id')<p class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
    </div>

    <div>
        @if ($canSelectOrigin)
            <label for="origin_unit_id" class="block text-sm font-bold text-slate-800">Unidad de origen <span class="text-red-700" aria-hidden="true">*</span></label>
            <select id="origin_unit_id" name="origin_unit_id" required class="mt-2 block min-h-11 w-full rounded-lg border-slate-300 shadow-sm focus:border-blue-600 focus:ring-blue-600">
                <option value="">Seleccione una opción</option>
                @foreach ($originUnits as $unit)
                    <option value="{{ $unit->id }}" @selected((string) old('origin_unit_id', $document->origin_unit_id ?? '') === (string) $unit->id)>{{ $unit->name }} ({{ $unit->type->label() }})</option>
                @endforeach
            </select>
            @error('origin_unit_id')<p class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
        @else
            <p class="block text-sm font-bold text-slate-800">Unidad de origen</p>
            <div class="mt-2 min-h-11 rounded-lg border border-slate-300 bg-slate-50 px-3 py-2.5 text-sm text-slate-800">{{ $fixedOrigin?->name ?? 'Sin unidad activa asignada' }}</div>
            <p class="mt-2 text-xs text-slate-600">Se determina automáticamente según su ámbito institucional.</p>
        @endif
    </div>

    <div>
        <label for="priority" class="block text-sm font-bold text-slate-800">Prioridad <span class="text-red-700" aria-hidden="true">*</span></label>
        <select id="priority" name="priority" required class="mt-2 block min-h-11 w-full rounded-lg border-slate-300 shadow-sm focus:border-blue-600 focus:ring-blue-600">
            @foreach ($priorities as $priority)
                <option value="{{ $priority->value }}" @selected(old('priority', isset($document) ? $document->priority->value : 'normal') === $priority->value)>{{ $priority->label() }}</option>
            @endforeach
        </select>
        @error('priority')<p class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="confidentiality" class="block text-sm font-bold text-slate-800">Confidencialidad <span class="text-red-700" aria-hidden="true">*</span></label>
        <select id="confidentiality" name="confidentiality" required class="mt-2 block min-h-11 w-full rounded-lg border-slate-300 shadow-sm focus:border-blue-600 focus:ring-blue-600">
            @foreach ($confidentialities as $confidentiality)
                <option value="{{ $confidentiality->value }}" @selected(old('confidentiality', isset($document) ? $document->confidentiality->value : 'normal') === $confidentiality->value)>{{ $confidentiality->label() }}</option>
            @endforeach
        </select>
        @error('confidentiality')<p class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="due_at" class="block text-sm font-bold text-slate-800">Fecha límite <span class="font-normal text-slate-500">(opcional)</span></label>
        <input id="due_at" name="due_at" type="date" min="{{ now()->toDateString() }}" value="{{ old('due_at', isset($document) ? $document->due_at?->toDateString() : '') }}" class="mt-2 block min-h-11 w-full rounded-lg border-slate-300 shadow-sm focus:border-blue-600 focus:ring-blue-600">
        @error('due_at')<p class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
    </div>

    <div class="lg:col-span-2">
        <label for="description" class="block text-sm font-bold text-slate-800">Descripción <span class="font-normal text-slate-500">(opcional)</span></label>
        <textarea id="description" name="description" rows="6" maxlength="5000" class="mt-2 block w-full rounded-lg border-slate-300 shadow-sm focus:border-blue-600 focus:ring-blue-600">{{ old('description', $document->description ?? '') }}</textarea>
        @error('description')<p class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
    </div>
</div>

@if ($editing)
    <input type="hidden" name="lock_version" value="{{ old('lock_version', $document->lock_version) }}">
@endif

<div class="mt-8 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
    <a href="{{ $editing ? route('documents.show', $document) : route('documents.index') }}" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-50">Cancelar</a>
    <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-institutional-700 px-5 py-2.5 text-sm font-bold text-white hover:bg-institutional-900">{{ $editing ? 'Guardar cambios' : 'Guardar borrador' }}</button>
</div>
