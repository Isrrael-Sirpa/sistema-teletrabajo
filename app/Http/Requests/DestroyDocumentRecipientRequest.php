<?php

namespace App\Http\Requests;

class DestroyDocumentRecipientRequest extends UpdateDocumentRecipientRequest
{
    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'recipient_id' => ['required', 'integer', 'min:1'],
            'lock_version' => ['required', 'integer', 'min:0'],
        ];
    }
}
