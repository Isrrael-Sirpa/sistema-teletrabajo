<?php

namespace App\Http\Requests;

use App\Models\DocumentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

class StoreDocumentTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', DocumentType::class);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'uses_correlative' => ['required', 'boolean'],
            'correlative_label' => ['nullable', 'string', 'max:30', 'required_if:uses_correlative,1'],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if (! $validator->errors()->has('name') && $this->nameExists()) {
                $validator->errors()->add('name', 'Ya existe un tipo documental con este nombre.');
            }
        }];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Ingrese el nombre del tipo documental.',
            'name.max' => 'El nombre no puede superar los 120 caracteres.',
            'correlative_label.required' => 'Ingrese la abreviatura que se mostrará en el correlativo.',
            'correlative_label.max' => 'La abreviatura no puede superar los 30 caracteres.',
            'description.max' => 'La descripción no puede superar los 2000 caracteres.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $description = $this->input('description');

        $this->merge([
            'name' => Str::squish((string) $this->input('name')),
            'uses_correlative' => $this->boolean('uses_correlative'),
            'correlative_label' => Str::upper(Str::squish((string) $this->input('correlative_label'))),
            'description' => filled($description) ? Str::squish((string) $description) : null,
        ]);
    }

    protected function nameExists(?DocumentType $except = null): bool
    {
        $query = DocumentType::query();

        if ($except) {
            $query->whereKeyNot($except->getKey());
        }

        return $query->pluck('name')->contains(
            fn (string $name): bool => Str::lower($name) === Str::lower($this->string('name')->toString()),
        );
    }
}
