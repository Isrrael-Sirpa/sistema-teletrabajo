<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class DocumentReportRequest extends SearchDocumentsRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'report' => ['nullable', Rule::in([
                'summary', 'sent', 'received', 'approved', 'returned', 'rejected',
                'overdue', 'due_soon',
            ])],
            'period_from' => ['nullable', 'date_format:Y-m-d'],
            'period_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:period_from'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            ...parent::messages(),
            'report.in' => 'El reporte solicitado no es válido.',
            'period_from.date_format' => 'Ingrese una fecha inicial válida.',
            'period_to.date_format' => 'Ingrese una fecha final válida.',
            'period_to.after_or_equal' => 'La fecha final no puede ser anterior a la fecha inicial.',
        ];
    }
}
