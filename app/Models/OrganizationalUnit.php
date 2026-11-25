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

#[Fillable(['parent_id', 'type', 'name', 'acronym', 'correlative_prefix', 'description', 'is_active'])]
class OrganizationalUnit extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'unidades_organizacionales';

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

    /** @return array<int, int> */
    public static function descendantIdsFor(int $unitId): array
    {
        $ids = [$unitId];
        $pending = [$unitId];

        while ($pending !== []) {
            $children = static::query()->whereIn('parent_id', $pending)->pluck('id')
                ->map(static fn ($id): int => (int) $id)->all();
            $pending = array_values(array_diff($children, $ids));
            $ids = [...$ids, ...$pending];
        }

        return $ids;
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
        $query->active()->where('type', '!=', OrganizationalUnitType::FACULTY->value);
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
