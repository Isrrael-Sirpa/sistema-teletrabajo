<?php

namespace App\Http\Requests;

use App\Enums\OrganizationalUnitType;
use App\Enums\UserStatus;
use App\Models\Document;
use App\Models\OrganizationalUnit;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreDocumentRecipientRequest extends FormRequest
{
    public function authorize(): bool
    {
        $document = $this->route('document');

        return $document instanceof Document && Gate::allows('manageRecipients', $document);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'recipient_type' => ['required', Rule::in(['unit', 'person'])],
            'unit_id' => [
                'nullable',
                'required_if:recipient_type,unit',
                'integer',
                Rule::exists(OrganizationalUnit::class, 'id')->where(function ($query): void {
                    $query->where('is_active', true)->whereIn('type', [
                        OrganizationalUnitType::CAREER->value,
                        OrganizationalUnitType::KARDEX->value,
                    ]);
                }),
            ],
            'user_id' => [
                'nullable',
                'required_if:recipient_type,person',
                'integer',
                Rule::exists(User::class, 'id')->where('status', UserStatus::ACTIVE->value),
            ],
            'requires_response' => ['required', 'boolean'],
            'lock_version' => ['required', 'integer', 'min:0'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'recipient_type.required' => 'Seleccione si enviará a una unidad o a una persona.',
            'recipient_type.in' => 'El tipo de destinatario no es válido.',
            'unit_id.required_if' => 'Seleccione una carrera o unidad.',
            'unit_id.exists' => 'Seleccione una carrera o Kardex activo.',
            'user_id.required_if' => 'Seleccione una persona.',
            'user_id.exists' => 'Seleccione un usuario activo.',
            'requires_response.required' => 'Indique si el destinatario requiere respuesta.',
            'requires_response.boolean' => 'La clasificación del destinatario no es válida.',
            'lock_version.required' => 'No se recibió la versión de control del borrador.',
            'lock_version.integer' => 'La versión de control del borrador no es válida.',
            'lock_version.min' => 'La versión de control del borrador no es válida.',
        ];
    }
}
