<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UnitCostPeriod extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'code',
        'name',
        'period_year',
        'status',
        'created_by',
    ];

    protected $casts = [
        'period_year' => 'integer',
    ];

    /**
     * @return HasMany<UnitCostParameterEntry, $this>
     */
    public function entries(): HasMany
    {
        return $this->hasMany(UnitCostParameterEntry::class, 'period_id');
    }

    /**
     * @return HasMany<UnitCostResultSnapshot, $this>
     */
    public function snapshots(): HasMany
    {
        return $this->hasMany(UnitCostResultSnapshot::class, 'period_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @param  Builder<UnitCostPeriod>  $query
     * @return Builder<UnitCostPeriod>
     */
    public function scopeStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }
}
