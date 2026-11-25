<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['activity_id', 'user_id', 'detail', 'progress_percent'])]
class ActivityUpdate extends Model
{
    protected $table = 'avances_actividades';

    public function activity(): BelongsTo
    {
        return $this->belongsTo(WorkActivity::class, 'activity_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return ['progress_percent' => 'integer'];
    }
}
