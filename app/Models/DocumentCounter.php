<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['document_type_id', 'year', 'last_number'])]
class DocumentCounter extends Model
{
    protected $table = 'contadores_documentos';

    public function originUnit(): BelongsTo
    {
        return $this->belongsTo(OrganizationalUnit::class, 'origin_unit_id');
    }

    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class);
    }

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'last_number' => 'integer',
        ];
    }
}
