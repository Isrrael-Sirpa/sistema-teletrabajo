<?php

namespace App\Http\Requests;

use App\Enums\DocumentConfidentiality;
use App\Enums\DocumentPriority;
use App\Enums\RoleName;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\OrganizationalUnit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', Document::class);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'document_type_id' => [
                'required',
                'integer',
                Rule::exists(DocumentType::class, 'id')->where(function ($query): void {
                    $query->where('is_active', true);

                    if ($this->user()?->hasRole(RoleName::STUDENT->value)) {
                        $query->whereIn('code', ['solicitud', 'otros']);
                    }
                }),
            ],
            'origin_unit_id' => [
                Rule::requiredIf($this->user()?->hasInstitutionalGlobalScope() === true),
                'nullable',
                'integer',
                Rule::exists(OrganizationalUnit::class, 'id')->where('is_active', true),
            ],
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'priority' => ['required', $this->studentRule(Rule::enum(DocumentPriority::class), DocumentPriority::NORMAL->value)],
            'confidentiality' => ['required', $this->studentRule(Rule::enum(DocumentConfidentiality::class), DocumentConfidentiality::NORMAL->value)],
            'due_at' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'document_type_id.required' => 'Seleccione un tipo documental.',
            'document_type_id.exists' => 'Seleccione un tipo documental activo.',
            'origin_unit_id.required' => 'Seleccione la unidad de origen.',
            'origin_unit_id.exists' => 'Seleccione una unidad de origen activa.',
            'subject.required' => 'Ingrese el asunto del documento.',
            'subject.max' => 'El asunto no puede superar los 255 caracteres.',
            'description.max' => 'La descripción no puede superar los 5000 caracteres.',
            'priority.required' => 'Seleccione la prioridad.',
            'priority.enum' => 'La prioridad seleccionada no es válida.',
            'confidentiality.required' => 'Seleccione la confidencialidad.',
            'confidentiality.enum' => 'La confidencialidad seleccionada no es válida.',
            'due_at.date_format' => 'La fecha límite debe tener un formato válido.',
            'due_at.after_or_equal' => 'La fecha límite no puede estar en el pasado.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $description = $this->input('description');

        $this->merge([
            'subject' => Str::squish((string) $this->input('subject')),
            'description' => filled($description) ? trim((string) $description) : null,
            'priority' => $this->input('priority', DocumentPriority::NORMAL->value),
            'confidentiality' => $this->input('confidentiality', DocumentConfidentiality::NORMAL->value),
        ]);
    }

    private function studentRule(mixed $rule, string $studentValue): mixed
    {
        return $this->user()?->hasRole(RoleName::STUDENT->value) ? Rule::in([$studentValue]) : $rule;
    }
}
