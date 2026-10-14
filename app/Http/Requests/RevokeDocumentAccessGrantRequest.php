<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class RevokeDocumentAccessGrantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('revokeAccessGrant', [
            $this->route('document'),
            $this->route('documentAccessGrant'),
        ]);
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return ['revocation_reason' => ['required', 'string', 'max:1000']];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'revocation_reason.required' => 'Indique el motivo de la revocación.',
            'revocation_reason.max' => 'El motivo no puede superar los 1000 caracteres.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'revocation_reason' => Str::squish((string) $this->input('revocation_reason')),
        ]);
    }
}
