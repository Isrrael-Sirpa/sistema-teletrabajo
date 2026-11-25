@if ($errors->any())
    <div role="alert" class="ft-alert ft-alert-error mb-6" tabindex="-1" data-form-errors>
        <p class="font-semibold">Revise los datos ingresados.</p>
        <ul class="mt-1 list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif

<div class="space-y-5">
    <div class="ft-form-section"><h2 class="ft-form-section-title">Datos de la unidad</h2><p class="ft-field-help">Seleccione el tipo y la unidad de la que depende. El nombre y la sigla son obligatorios.</p></div>
    @if (! $unit)
        <div><label for="type" class="block text-sm font-semibold text-slate-800">Tipo de unidad</label><select id="type" name="type" required class="mt-2 block min-h-12 w-full rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-base text-slate-950"><option value="">Seleccione una opción</option>@foreach (App\Enums\OrganizationalUnitType::cases() as $type)@if ($type !== App\Enums\OrganizationalUnitType::FACULTY)<option value="{{ $type->value }}" @selected(old('type') === $type->value)>{{ $type->label() }}</option>@endif @endforeach</select>@error('type')<p class="ft-field-error">{{ $message }}</p>@enderror</div>
    @endif
    <div><label for="parent_id" class="block text-sm font-semibold text-slate-800">Depende de</label><select id="parent_id" name="parent_id" required class="mt-2 block min-h-12 w-full rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-base text-slate-950"><option value="">Seleccione la unidad superior</option>@foreach ($parents as $parent)<option value="{{ $parent->id }}" @selected((string) old('parent_id', $unit?->parent_id) === (string) $parent->id)>{{ $parent->name }} ({{ $parent->type->label() }})</option>@endforeach</select>@error('parent_id')<p class="ft-field-error">{{ $message }}</p>@enderror</div>
    <div><label for="name" class="block text-sm font-semibold text-slate-800">Nombre de la unidad</label><input id="name" name="name" type="text" value="{{ old('name', $unit?->name) }}" required maxlength="160" autocomplete="organization" class="mt-2 block min-h-12 w-full rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-base text-slate-950">@error('name')<p class="ft-field-error">{{ $message }}</p>@enderror</div>
    <div><label for="acronym" class="block text-sm font-semibold text-slate-800">Sigla</label><input id="acronym" name="acronym" type="text" value="{{ old('acronym', $unit?->acronym) }}" required maxlength="20" autocapitalize="characters" spellcheck="false" class="mt-2 block min-h-12 w-full rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-base uppercase text-slate-950">@error('acronym')<p class="ft-field-error">{{ $message }}</p>@enderror</div>
    @if ($unit && auth()->user()->hasRole('administrator'))
        <div><label for="correlative_prefix" class="block text-sm font-semibold text-slate-800">Prefijo de correspondencia <span class="font-normal text-slate-500">(ej.: FT C.MAZ.)</span></label><input id="correlative_prefix" name="correlative_prefix" type="text" value="{{ old('correlative_prefix', $unit->correlative_prefix) }}" maxlength="60" autocapitalize="characters" spellcheck="false" aria-describedby="correlative-prefix-help" class="mt-2 block min-h-12 w-full rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-base uppercase text-slate-950"><p id="correlative-prefix-help" class="ft-field-help">Se usa en los cites nuevos; los ya enviados no cambian.</p>@error('correlative_prefix')<p class="ft-field-error">{{ $message }}</p>@enderror</div>
    @endif
    <div><label for="description" class="block text-sm font-semibold text-slate-800">Descripción <span class="font-normal text-slate-500">(opcional)</span></label><textarea id="description" name="description" rows="4" maxlength="2000" class="mt-2 block w-full rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-base text-slate-950">{{ old('description', $unit?->description) }}</textarea>@error('description')<p class="ft-field-error">{{ $message }}</p>@enderror</div>
    <div class="ft-alert ft-alert-info">La unidad se crea activa. Puede actualizarse o desactivarse posteriormente sin perder el historial documental.</div>
</div>
<div class="ft-form-actions"><a href="{{ $cancelUrl }}" class="ft-button-secondary">Cancelar</a><button type="submit" class="ft-button-primary">{{ $submitLabel }}</button></div>
