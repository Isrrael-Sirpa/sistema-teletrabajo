<?php

namespace App\Http\Requests;

use App\Enums\DocumentConfidentiality;
use App\Enums\DocumentPriority;
use App\Enums\DocumentRecipientStatus;
use App\Enums\DocumentStatus;
use App\Models\DocumentType;
use App\Models\OrganizationalUnit;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SearchDocumentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::enum(DocumentStatus::class)],
            'recipient_status' => ['nullable', Rule::enum(DocumentRecipientStatus::class)],
            'priority' => ['nullable', Rule::enum(DocumentPriority::class)],
            'confidentiality' => ['nullable', Rule::enum(DocumentConfidentiality::class)],
            'deadline' => ['nullable', Rule::in(['due_soon', 'overdue', 'has_due', 'no_due'])],
            'document_type' => ['nullable', 'uuid', Rule::exists(DocumentType::class, 'public_id')],
            'origin_unit' => ['nullable', 'uuid', Rule::exists(OrganizationalUnit::class, 'public_id')],
            'recipient_unit' => ['nullable', 'uuid', Rule::exists(OrganizationalUnit::class, 'public_id')],
            'recipient_user' => ['nullable', 'uuid', Rule::exists(User::class, 'public_id')],
            'creator' => ['nullable', 'uuid', Rule::exists(User::class, 'public_id')],
            'sender' => ['nullable', 'uuid', Rule::exists(User::class, 'public_id')],
            'sent_from' => ['nullable', 'date_format:Y-m-d'],
            'sent_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:sent_from'],
            'due_from' => ['nullable', 'date_format:Y-m-d'],
            'due_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:due_from'],
            'created_from' => ['nullable', 'date_format:Y-m-d'],
            'created_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:created_from'],
            'correction_pending' => ['nullable', 'boolean'],
            'version_min' => ['nullable', 'integer', 'min:1', 'max:999'],
            'sort' => ['nullable', Rule::in(['created_at', 'sent_at', 'due_at', 'priority', 'code'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'work' => ['nullable', Rule::in([
                'pending_receive', 'in_review', 'decision', 'collaboration',
                'assigned', 'returned_correction', 'my_drafts', 'authorized', 'unit_pending',
                'my_unit',
            ])],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            '*.uuid' => 'El filtro seleccionado no es válido.',
            '*.exists' => 'El filtro seleccionado ya no está disponible.',
            '*.date_format' => 'Ingrese una fecha válida.',
            '*.after_or_equal' => 'La fecha final no puede ser anterior a la fecha inicial.',
            'sort.in' => 'El orden solicitado no es válido.',
            'direction.in' => 'La dirección de orden no es válida.',
            'work.in' => 'La vista de trabajo solicitada no es válida.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('q')) {
            $this->merge(['q' => str($this->input('q'))->squish()->toString()]);
        }
    }
}
