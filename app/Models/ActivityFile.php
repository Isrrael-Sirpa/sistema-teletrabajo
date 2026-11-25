<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['activity_id', 'uploaded_by', 'disk', 'path', 'original_name', 'mime_type', 'size_bytes'])]
class ActivityFile extends Model
{
    protected $table = 'archivos_actividades';

    public function activity(): BelongsTo
    {
        return $this->belongsTo(WorkActivity::class, 'activity_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
