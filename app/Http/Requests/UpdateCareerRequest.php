<?php

namespace App\Http\Requests;

use App\Enums\RoleName;
use App\Models\OrganizationalUnit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateCareerRequest extends FormRequest
{
    public function authorize(): bool
    {
        $unit = $this->route('unit');

        return $unit instanceof OrganizationalUnit && Gate::allows('update', $unit);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'acronym' => ['required', 'string', 'max:20', 'regex:/^[\pL\pN][\pL\pN .&\/-]*$/u'],
            'correlative_prefix' => [Rule::prohibitedIf(! $this->user()?->hasRole(RoleName::ADMINISTRATOR->value)), 'nullable', 'string', 'max:60'],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if (! $validator->errors()->has('name') && $this->unitExists('name', $this->string('name')->toString())) {
                $validator->errors()->add('name', 'Ya existe otra unidad con este nombre.');
            }

            if (! $validator->errors()->has('acronym') && $this->unitExists('acronym', $this->string('acronym')->toString())) {
                $validator->errors()->add('acronym', 'Ya existe otra unidad con esta sigla.');
            }
        }];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Ingrese el nombre de la carrera.',
            'name.max' => 'El nombre no puede superar los 160 caracteres.',
            'acronym.required' => 'Ingrese la sigla de la carrera.',
            'acronym.max' => 'La sigla no puede superar los 20 caracteres.',
            'acronym.regex' => 'La sigla contiene caracteres no permitidos.',
            'correlative_prefix.prohibited' => 'Solo el Administrador puede modificar el prefijo institucional.',
            'correlative_prefix.max' => 'El prefijo no puede superar los 60 caracteres.',
            'description.max' => 'La descripción no puede superar los 2000 caracteres.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $description = $this->input('description');

        $data = [
            'name' => Str::squish((string) $this->input('name')),
            'acronym' => Str::upper(Str::squish((string) $this->input('acronym'))),
            'description' => filled($description) ? trim((string) $description) : null,
        ];

        if ($this->has('correlative_prefix')) {
            $data['correlative_prefix'] = filled($this->input('correlative_prefix'))
                ? Str::upper(Str::squish((string) $this->input('correlative_prefix')))
                : null;
        }

        $this->merge($data);
    }

    protected function unitExists(string $column, string $value): bool
    {
        /** @var OrganizationalUnit $unit */
        $unit = $this->route('unit');

        return OrganizationalUnit::query()
            ->whereKeyNot($unit->getKey())
            ->pluck($column)
            ->contains(fn (string $existing): bool => Str::lower($existing) === Str::lower($value));
    }
}
