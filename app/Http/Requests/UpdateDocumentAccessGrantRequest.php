<?php

namespace App\Http\Requests;

use App\Enums\DocumentAccessPermission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateDocumentAccessGrantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('updateAccessGrant', [
            $this->route('document'),
            $this->route('documentAccessGrant'),
        ]);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return ['permission' => ['required', Rule::enum(DocumentAccessPermission::class)]];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'permission.required' => 'Seleccione el nuevo nivel de acceso.',
            'permission.enum' => 'El nivel de acceso seleccionado no es válido.',
        ];
    }
}
