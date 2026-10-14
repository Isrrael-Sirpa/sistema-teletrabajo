<?php

namespace App\Models;

use App\Enums\OrganizationalUnitType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['parent_id', 'type', 'name', 'acronym', 'description', 'is_active'])]
class OrganizationalUnit extends Model
{
    use HasFactory, HasUuids;

    /**
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * @param  Builder<OrganizationalUnit>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @param  Builder<OrganizationalUnit>  $query
     */
    public function scopeCareers(Builder $query): void
    {
        $query->where('type', OrganizationalUnitType::CAREER->value);
    }

    /**
     * @param  Builder<OrganizationalUnit>  $query
     */
    public function scopeAvailableAsDocumentDestination(Builder $query): void
    {
        $query->active()->whereIn('type', [
            OrganizationalUnitType::CAREER->value,
            OrganizationalUnitType::KARDEX->value,
        ]);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => OrganizationalUnitType::class,
            'is_active' => 'boolean',
        ];
    }
}
