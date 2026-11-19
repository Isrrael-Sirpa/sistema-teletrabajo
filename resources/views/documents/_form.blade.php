@php($editing = isset($document))

@if ($errors->any())
    <div role="alert" class="ft-alert ft-alert-error mb-6" tabindex="-1" data-form-errors>
        <p class="font-bold">No se guardó el borrador. Revise los datos señalados.</p>
        <ul class="mt-2 list-disc space-y-1 pl-5">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<p class="mb-6 text-sm text-slate-600">Los campos con <span aria-hidden="true" class="font-bold text-red-700">*</span><span class="sr-only">asterisco</span> son obligatorios.</p>

<section class="ft-form-section" aria-labelledby="document-identification-title">
    <div>
        <h2 id="document-identification-title" class="ft-form-section-title">¿Qué documento va a preparar?</h2>
        <p class="ft-field-help">Un asunto claro ayuda a encontrar el documento y a comprender su propósito.</p>
    </div>
    <div class="mt-5 grid gap-6 lg:grid-cols-2">
        <div class="lg:col-span-2">
            <label for="subject" class="block text-sm font-bold text-slate-800">Asunto <span class="text-red-700" aria-hidden="true">*</span></label>
            <input id="subject" name="subject" type="text" required maxlength="255" value="{{ old('subject', $document->subject ?? '') }}" class="mt-2 block min-h-11 w-full" autocomplete="off" aria-describedby="subject-help @error('subject') subject-error @enderror" @error('subject') aria-invalid="true" @enderror>
            <p id="subject-help" class="ft-field-help">Resuma el motivo del documento en una frase. Máximo 255 caracteres.</p>
            @error('subject')<p id="subject-error" class="ft-field-error">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="document_type_id" class="block text-sm font-bold text-slate-800">Tipo documental <span class="text-red-700" aria-hidden="true">*</span></label>
            <select id="document_type_id" name="document_type_id" required class="mt-2 block min-h-11 w-full" aria-describedby="document-type-help @error('document_type_id') document-type-error @enderror" @error('document_type_id') aria-invalid="true" @enderror>
                <option value="">Seleccione una opción</option>
                @foreach ($documentTypes as $documentType)
                    <option value="{{ $documentType->id }}" @selected((string) old('document_type_id', $document->document_type_id ?? $selectedDocumentTypeId ?? '') === (string) $documentType->id)>{{ $documentType->name }}</option>
                @endforeach
            </select>
            <p id="document-type-help" class="ft-field-help">Solo se muestran tipos documentales activos.</p>
            @error('document_type_id')<p id="document-type-error" class="ft-field-error">{{ $message }}</p>@enderror
        </div>

        <div>
            @if ($canSelectOrigin)
                <label for="origin_unit_id" class="block text-sm font-bold text-slate-800">Unidad de origen <span class="text-red-700" aria-hidden="true">*</span></label>
                <select id="origin_unit_id" name="origin_unit_id" required class="mt-2 block min-h-11 w-full" aria-describedby="origin-help @error('origin_unit_id') origin-error @enderror" @error('origin_unit_id') aria-invalid="true" @enderror>
                    <option value="">Seleccione una opción</option>
                    @foreach ($originUnits as $unit)
                        <option value="{{ $unit->id }}" @selected((string) old('origin_unit_id', $document->origin_unit_id ?? '') === (string) $unit->id)>{{ $unit->name }} ({{ $unit->type->label() }})</option>
                    @endforeach
                </select>
                <p id="origin-help" class="ft-field-help">Carrera o unidad desde la que se emitirá el documento.</p>
                @error('origin_unit_id')<p id="origin-error" class="ft-field-error">{{ $message }}</p>@enderror
            @else
                <p class="block text-sm font-bold text-slate-800">Unidad de origen</p>
                <div class="mt-2 min-h-11 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm font-semibold text-slate-800">{{ $fixedOrigin?->name ?? 'Sin unidad activa asignada' }}</div>
                <p class="ft-field-help">Se determina automáticamente según su ámbito institucional.</p>
            @endif
        </div>

        <div class="lg:col-span-2">
            <label for="description" class="block text-sm font-bold text-slate-800">Descripción <span class="font-normal text-slate-500">(opcional)</span></label>
            <textarea id="description" name="description" rows="5" maxlength="5000" class="mt-2 block w-full" aria-describedby="description-help @error('description') description-error @enderror" @error('description') aria-invalid="true" @enderror>{{ old('description', $document->description ?? '') }}</textarea>
            <p id="description-help" class="ft-field-help">Agregue el contexto o las indicaciones necesarias para quien reciba el documento. Máximo 5.000 caracteres.</p>
            @error('description')<p id="description-error" class="ft-field-error">{{ $message }}</p>@enderror
        </div>
    </div>
</section>

<section class="ft-form-section mt-8 border-t border-slate-200 pt-7" aria-labelledby="document-attention-title">
    <div>
        <h2 id="document-attention-title" class="ft-form-section-title">¿Cómo debe atenderse?</h2>
        <p class="ft-field-help">Defina la prioridad, la clasificación y, si corresponde, una fecha límite.</p>
    </div>
    <div class="mt-5 grid gap-6 sm:grid-cols-2">
        <div>
            <label for="priority" class="block text-sm font-bold text-slate-800">Prioridad <span class="text-red-700" aria-hidden="true">*</span></label>
            <select id="priority" name="priority" required class="mt-2 block min-h-11 w-full" aria-describedby="priority-help @error('priority') priority-error @enderror" @error('priority') aria-invalid="true" @enderror>
                @foreach ($priorities as $priority)
                    <option value="{{ $priority->value }}" @selected(old('priority', isset($document) ? $document->priority->value : 'normal') === $priority->value)>{{ $priority->label() }}</option>
                @endforeach
            </select>
            <p id="priority-help" class="ft-field-help">Indique la urgencia real de atención.</p>
            @error('priority')<p id="priority-error" class="ft-field-error">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="confidentiality" class="block text-sm font-bold text-slate-800">Confidencialidad <span class="text-red-700" aria-hidden="true">*</span></label>
            <select id="confidentiality" name="confidentiality" required class="mt-2 block min-h-11 w-full" aria-describedby="confidentiality-help @error('confidentiality') confidentiality-error @enderror" @error('confidentiality') aria-invalid="true" @enderror>
                @foreach ($confidentialities as $confidentiality)
                    <option value="{{ $confidentiality->value }}" @selected(old('confidentiality', isset($document) ? $document->confidentiality->value : 'normal') === $confidentiality->value)>{{ $confidentiality->label() }}</option>
                @endforeach
            </select>
            <p id="confidentiality-help" class="ft-field-help">Seleccione la clasificación institucional que corresponda.</p>
            @error('confidentiality')<p id="confidentiality-error" class="ft-field-error">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="due_at" class="block text-sm font-bold text-slate-800">Fecha límite <span class="font-normal text-slate-500">(opcional)</span></label>
            <input id="due_at" name="due_at" type="date" min="{{ now()->toDateString() }}" value="{{ old('due_at', isset($document) ? $document->due_at?->toDateString() : '') }}" class="mt-2 block min-h-11 w-full" aria-describedby="due-at-help @error('due_at') due-at-error @enderror" @error('due_at') aria-invalid="true" @enderror>
            <p id="due-at-help" class="ft-field-help">Déjela vacía si el documento no tiene un plazo definido.</p>
            @error('due_at')<p id="due-at-error" class="ft-field-error">{{ $message }}</p>@enderror
        </div>
    </div>
</section>

@if ($editing)
    <input type="hidden" name="lock_version" value="{{ old('lock_version', $document->lock_version) }}">
@endif

<div class="ft-form-actions mt-8 border-t border-slate-200 pt-6">
    <p class="text-sm leading-6 text-slate-600 sm:mr-auto">Siguiente paso: revisar destinatarios y archivos en el detalle del documento.</p>
    <a href="{{ $editing ? route('documents.show', $document) : route('documents.index') }}" class="ft-button-secondary">Cancelar</a>
    <button type="submit" class="ft-button-primary">{{ $editing ? 'Guardar cambios' : 'Guardar borrador' }}</button>
</div>
