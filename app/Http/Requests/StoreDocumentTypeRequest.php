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
            'description.max' => 'La descripción no puede superar los 2000 caracteres.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $description = $this->input('description');

        $this->merge([
            'name' => Str::squish((string) $this->input('name')),
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
