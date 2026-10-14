<?php

namespace App\Http\Requests;

use App\Enums\DocumentAccessPermission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreDocumentAccessGrantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('grantAccess', $this->route('document'));
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'permission' => ['required', Rule::enum(DocumentAccessPermission::class)],
            'ends_at' => ['nullable', 'date_format:Y-m-d\TH:i', 'after:now'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'user_id.required' => 'Seleccione el usuario que recibirá el acceso.',
            'user_id.exists' => 'El usuario seleccionado no existe.',
            'permission.required' => 'Seleccione el nivel de acceso.',
            'permission.enum' => 'El nivel de acceso seleccionado no es válido.',
            'ends_at.date_format' => 'La fecha de vencimiento no tiene un formato válido.',
            'ends_at.after' => 'La fecha de vencimiento debe ser posterior al momento actual.',
        ];
    }
}
