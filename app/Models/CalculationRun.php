<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CalculationRun extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_CALCULATED = 'calculated';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'run_code',
        'organization_unit_id',
        'unit_code',
        'period_year',
        'status',
        'formula_version',
        'parameter_snapshot',
        'total_unit_cost',
        'created_by',
        'calculated_at',
        'notes',
    ];

    protected $casts = [
        'period_year' => 'integer',
        'formula_version' => 'integer',
        'parameter_snapshot' => 'array',
        'total_unit_cost' => 'decimal:2',
        'calculated_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<OrganizationUnit, $this>
     */
    public function organizationUnit(): BelongsTo
    {
        return $this->belongsTo(OrganizationUnit::class)->withDefault();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<CalculationDetail, $this>
     */
    public function details(): HasMany
    {
        return $this->hasMany(CalculationDetail::class)->orderBy('id');
    }

    /**
     * @param  Builder<CalculationRun>  $query
     * @return Builder<CalculationRun>
     */
    public function scopeStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function isCalculated(): bool
    {
        return $this->status === self::STATUS_CALCULATED;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }
}
