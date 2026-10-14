<?php

namespace App\Http\Requests;

use App\Models\Document;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class AnnulDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $document = $this->route('document');

        return $document instanceof Document && Gate::allows('annul', $document);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'document_lock_version' => ['required', 'integer', 'min:0'],
            'reason' => [
                'required',
                'string',
                'max:'.max(1, min(10_000, (int) config('documents.workflow.annulment_reason_max_length', 2_000))),
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (blank(trim((string) $value))) {
                        $fail('Debe indicar el motivo de la anulación.');
                    }
                },
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'document_lock_version.required' => 'No se recibió la versión de control del documento.',
            'document_lock_version.integer' => 'La versión de control del documento no es válida.',
            'document_lock_version.min' => 'La versión de control del documento no es válida.',
            'reason.required' => 'Debe indicar el motivo de la anulación.',
            'reason.string' => 'El motivo de la anulación no es válido.',
            'reason.max' => 'El motivo de la anulación supera la longitud máxima permitida.',
        ];
    }
}
