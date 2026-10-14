<?php

namespace App\Http\Requests;

use App\Models\Document;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreDocumentFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        $document = $this->route('document');

        return $document instanceof Document && Gate::allows('manageFiles', $document);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'files' => [
                'required',
                'array',
                'min:1',
                'max:'.config('documents.uploads.max_files_per_request'),
            ],
            'files.*' => [
                'required',
                'file',
                'max:'.config('documents.uploads.max_size_kb'),
            ],
            'lock_version' => ['required', 'integer', 'min:0'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        $maxMegabytes = config('documents.uploads.max_size_kb') / 1024;
        $maxFiles = config('documents.uploads.max_files_per_request');

        return [
            'files.required' => 'Seleccione al menos un archivo.',
            'files.array' => 'La selección de archivos no es válida.',
            'files.min' => 'Seleccione al menos un archivo.',
            'files.max' => "Puede cargar como máximo {$maxFiles} archivos por vez.",
            'files.*.required' => 'Uno de los archivos seleccionados no es válido.',
            'files.*.file' => 'Uno de los elementos seleccionados no es un archivo válido.',
            'files.*.max' => "Cada archivo puede pesar como máximo {$maxMegabytes} MB.",
            'lock_version.required' => 'No se recibió la versión de control del borrador.',
            'lock_version.integer' => 'La versión de control del borrador no es válida.',
            'lock_version.min' => 'La versión de control del borrador no es válida.',
        ];
    }
}
