<?php

namespace App\Http\Requests;

use App\Models\Document;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class SendDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $document = $this->route('document');

        return $document instanceof Document && Gate::allows('send', $document);
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'document_lock_version' => ['required', 'integer', 'min:0'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'document_lock_version.required' => 'No se recibió la versión de control del documento.',
            'document_lock_version.integer' => 'La versión de control del documento no es válida.',
            'document_lock_version.min' => 'La versión de control del documento no es válida.',
        ];
    }
}
