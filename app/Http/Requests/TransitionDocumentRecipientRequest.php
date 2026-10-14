<?php

namespace App\Http\Requests;

use App\Enums\DocumentTransition;
use App\Models\Document;
use App\Models\DocumentRecipient;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class TransitionDocumentRecipientRequest extends FormRequest
{
    public function authorize(): bool
    {
        $document = $this->route('document');
        $recipient = $this->route('documentRecipient');
        $transition = DocumentTransition::tryFrom((string) $this->route('transition'));

        return $document instanceof Document
            && $recipient instanceof DocumentRecipient
            && $transition !== null
            && Gate::allows($transition->policyAbility(), [$document, $recipient]);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $transition = (string) $this->route('transition');

        return [
            'document_lock_version' => ['required', 'integer', 'min:0'],
            'recipient_lock_version' => ['required', 'integer', 'min:0'],
            'reason' => [
                Rule::requiredIf(in_array($transition, [
                    DocumentTransition::RETURN->value,
                    DocumentTransition::REJECT->value,
                ], true)),
                'nullable',
                'string',
                'max:'.config('documents.workflow.reason_max_length'),
                function (string $attribute, mixed $value, \Closure $fail) use ($transition): void {
                    if (in_array($transition, [DocumentTransition::RETURN->value, DocumentTransition::REJECT->value], true)
                        && blank(trim((string) $value))) {
                        $fail('Debe indicar un motivo para devolver o rechazar el documento.');
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
            'recipient_lock_version.required' => 'No se recibió la versión de control del destinatario.',
            'recipient_lock_version.integer' => 'La versión de control del destinatario no es válida.',
            'reason.required' => 'Debe indicar un motivo para devolver o rechazar el documento.',
            'reason.max' => 'El motivo supera la longitud máxima permitida.',
        ];
    }
}
