<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['document_id', 'version_number', 'uploaded_by', 'note'])]
class DocumentVersion extends Model
{
    protected $table = 'versiones_documentos';

    public $timestamps = false;

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function files(): HasMany
    {
        return $this->hasMany(DocumentFile::class);
    }

    protected function casts(): array
    {
        return [
            'version_number' => 'integer',
            'created_at' => 'datetime',
        ];
    }
}
