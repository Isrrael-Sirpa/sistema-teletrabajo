<?php

namespace App\Http\Requests;

use App\Models\Document;
use Illuminate\Support\Facades\Gate;

class UpdateDocumentRequest extends StoreDocumentRequest
{
    public function authorize(): bool
    {
        $document = $this->route('document');

        return $document instanceof Document && Gate::allows('update', $document);
    }

    public function rules(): array
    {
        return [
            ...parent::rules(),
            'lock_version' => ['required', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            ...parent::messages(),
            'lock_version.required' => 'No se recibió la versión de control del borrador.',
            'lock_version.integer' => 'La versión de control del borrador no es válida.',
            'lock_version.min' => 'La versión de control del borrador no es válida.',
        ];
    }
}
