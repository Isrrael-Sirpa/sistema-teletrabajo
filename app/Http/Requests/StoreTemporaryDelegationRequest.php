<?php

namespace App\Http\Requests;

use App\Support\CareerHeadDelegationCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTemporaryDelegationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'organizational_unit_id' => ['required', 'integer', 'exists:organizational_units,id'],
            'delegator_user_id' => ['required', 'integer', 'exists:users,id'],
            'delegate_user_id' => ['required', 'integer', 'different:delegator_user_id', 'exists:users,id'],
            'starts_at' => ['required', 'date_format:Y-m-d\TH:i'],
            'ends_at' => ['required', 'date_format:Y-m-d\TH:i', 'after:starts_at'],
            'reason' => ['required', 'string', 'max:2000'],
            'permissions' => ['required', 'array', 'min:1'],
            'permissions.*' => ['required', 'string', 'distinct', Rule::in(CareerHeadDelegationCatalog::values())],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'ends_at.after' => 'La fecha final debe ser posterior al inicio.',
            'permissions.required' => 'Seleccione al menos una capacidad.',
            'permissions.min' => 'Seleccione al menos una capacidad.',
            'reason.required' => 'Indique el motivo institucional de la delegación.',
        ];
    }
}
