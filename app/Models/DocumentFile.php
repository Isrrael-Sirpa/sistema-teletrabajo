<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'document_version_id', 'disk', 'path', 'original_name', 'stored_name',
    'extension', 'detected_mime_type', 'size_bytes', 'sha256', 'uploaded_by',
])]
class DocumentFile extends Model
{
    use HasUuids;

    protected $table = 'archivos_documentos';

    public $timestamps = false;

    /** @return array<int, string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function supportsInlinePreview(): bool
    {
        return in_array($this->detected_mime_type, ['application/pdf', 'image/jpeg', 'image/png'], true);
    }

    public function documentVersion(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'created_at' => 'datetime',
        ];
    }
}
