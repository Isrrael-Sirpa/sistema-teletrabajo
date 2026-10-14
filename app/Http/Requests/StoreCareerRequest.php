<?php

namespace App\Http\Requests;

use App\Enums\PermissionName;
use App\Models\OrganizationalUnit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

class StoreCareerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(PermissionName::UNITS_CREATE->value) === true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'acronym' => ['required', 'string', 'max:20', 'regex:/^[\pL\pN][\pL\pN .&\/-]*$/u'],
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
                $validator->errors()->add('name', 'Ya existe una unidad con este nombre.');
            }

            if (! $validator->errors()->has('acronym') && $this->unitExists('acronym', $this->string('acronym')->toString())) {
                $validator->errors()->add('acronym', 'Ya existe una unidad con esta sigla.');
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
            'description.max' => 'La descripción no puede superar los 2000 caracteres.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $description = $this->input('description');

        $this->merge([
            'name' => Str::squish((string) $this->input('name')),
            'acronym' => Str::upper(Str::squish((string) $this->input('acronym'))),
            'description' => filled($description) ? trim((string) $description) : null,
        ]);
    }

    protected function unitExists(string $column, string $value): bool
    {
        return OrganizationalUnit::query()
            ->pluck($column)
            ->contains(fn (string $existing): bool => Str::lower($existing) === Str::lower($value));
    }
}
