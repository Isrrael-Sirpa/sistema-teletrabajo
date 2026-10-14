<?php

namespace App\Http\Requests;

use App\Models\Document;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateDocumentRecipientRequest extends FormRequest
{
    public function authorize(): bool
    {
        $document = $this->route('document');

        return $document instanceof Document && Gate::allows('manageRecipients', $document);
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'recipient_id' => ['required', 'integer', 'min:1'],
            'requires_response' => ['required', 'boolean'],
            'lock_version' => ['required', 'integer', 'min:0'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'recipient_id.required' => 'No se identificó el destinatario.',
            'recipient_id.integer' => 'El destinatario no es válido.',
            'requires_response.required' => 'Indique si el destinatario requiere respuesta.',
            'requires_response.boolean' => 'La clasificación del destinatario no es válida.',
            'lock_version.required' => 'No se recibió la versión de control del borrador.',
            'lock_version.integer' => 'La versión de control del borrador no es válida.',
            'lock_version.min' => 'La versión de control del borrador no es válida.',
        ];
    }
}
