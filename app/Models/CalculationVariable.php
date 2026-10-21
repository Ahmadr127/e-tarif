<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CalculationVariable extends Model
{
    public const SOURCE_GLOBAL = 'global';

    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_INTEGRATION = 'integration';

    public const SOURCE_DERIVED = 'derived';

    public const SCOPE_GLOBAL = 'global';

    public const SCOPE_UNIT = 'unit';

    public const SCOPE_PERIOD = 'period';

    public const SCOPE_UNIT_PERIOD = 'unit_period';

    protected $fillable = [
        'code',
        'name',
        'component_id',
        'value_type',
        'default_value',
        'unit',
        'scope',
        'source_type',
        'source_name',
        'description',
        'is_required',
        'is_active',
    ];

    protected $casts = [
        'default_value' => 'decimal:6',
        'is_required' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * @return BelongsTo<CostComponent, $this>
     */
    public function component(): BelongsTo
    {
        return $this->belongsTo(CostComponent::class, 'component_id');
    }

    /**
     * @param  Builder<CalculationVariable>  $query
     * @return Builder<CalculationVariable>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @return HasMany<CalculationVariableValue, $this>
     */
    public function values(): HasMany
    {
        return $this->hasMany(CalculationVariableValue::class);
    }

    public function isDerived(): bool
    {
        return $this->source_type === self::SOURCE_DERIVED;
    }

    public function isRatio(): bool
    {
        return str_starts_with(mb_strtolower(trim((string) $this->unit)), 'rasio');
    }

    public function isPercentage(): bool
    {
        $unit = mb_strtolower(trim((string) $this->unit));

        return str_starts_with($unit, 'persen') || $unit === '%';
    }

    public function requiresUnit(): bool
    {
        return in_array($this->scope, [self::SCOPE_UNIT, self::SCOPE_UNIT_PERIOD], true);
    }

    public function requiresPeriod(): bool
    {
        return in_array($this->scope, [self::SCOPE_PERIOD, self::SCOPE_UNIT_PERIOD], true);
    }

    public function valueScopeKey(?int $unitId, ?int $periodYear): string
    {
        return CalculationVariableValue::scopeKeyFor($this, $unitId, $periodYear);
    }

    public function dataType(): string
    {
        return (string) ($this->value_type ?: 'decimal');
    }

    public function inputType(): string
    {
        return (string) ($this->source_type ?: self::SOURCE_MANUAL);
    }
}
