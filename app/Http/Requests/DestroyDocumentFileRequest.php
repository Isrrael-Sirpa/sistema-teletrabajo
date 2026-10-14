<?php

namespace App\Http\Requests;

use App\Models\Document;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class DestroyDocumentFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        $document = $this->route('document');

        return $document instanceof Document && Gate::allows('manageFiles', $document);
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'lock_version' => ['required', 'integer', 'min:0'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'lock_version.required' => 'No se recibió la versión de control del borrador.',
            'lock_version.integer' => 'La versión de control del borrador no es válida.',
            'lock_version.min' => 'La versión de control del borrador no es válida.',
        ];
    }
}
