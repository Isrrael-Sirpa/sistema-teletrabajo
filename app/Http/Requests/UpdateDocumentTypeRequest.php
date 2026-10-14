<?php

namespace App\Http\Requests;

use App\Models\DocumentType;
use Illuminate\Support\Facades\Gate;

class UpdateDocumentTypeRequest extends StoreDocumentTypeRequest
{
    public function authorize(): bool
    {
        $documentType = $this->route('documentType');

        return $documentType instanceof DocumentType && Gate::allows('update', $documentType);
    }

    protected function nameExists(?DocumentType $except = null): bool
    {
        /** @var DocumentType $documentType */
        $documentType = $this->route('documentType');

        return parent::nameExists($documentType);
    }
}
