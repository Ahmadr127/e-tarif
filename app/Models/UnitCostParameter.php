<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UnitCostParameter extends Model
{
    public const VALUE_DECIMAL = 'decimal';

    public const VALUE_INTEGER = 'integer';

    public const VALUE_BOOLEAN = 'boolean';

    public const SCOPE_GLOBAL = 'global';

    public const SCOPE_UNIT = 'unit';

    public const SCOPE_PERIOD = 'period';

    public const SCOPE_UNIT_PERIOD = 'unit_period';

    public const SOURCE_GLOBAL = 'global';

    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_INTEGRATION = 'integration';

    public const SOURCE_DERIVED = 'derived';

    protected $fillable = [
        'component_id',
        'code',
        'variable',
        'name',
        'value_type',
        'unit',
        'scope',
        'source_type',
        'source_name',
        'description',
        'is_required',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $parameter): void {
            if (blank($parameter->variable)) {
                $parameter->variable = $parameter->code;
            }
        });
    }

    /**
     * Nama variabel global yang dipakai pada formula.
     */
    public function formulaKey(): string
    {
        return (string) ($this->variable ?: $this->code);
    }

    /**
     * @return BelongsTo<UnitCostComponent, $this>
     */
    public function component(): BelongsTo
    {
        return $this->belongsTo(UnitCostComponent::class, 'component_id');
    }

    /**
     * @return HasMany<UnitCostParameterEntry, $this>
     */
    public function entries(): HasMany
    {
        return $this->hasMany(UnitCostParameterEntry::class, 'parameter_id');
    }

    /**
     * @return HasMany<UnitCostFormulaVersion, $this>
     */
    public function formulaVersions(): HasMany
    {
        return $this->hasMany(UnitCostFormulaVersion::class, 'parameter_id');
    }

    /**
     * @param  Builder<UnitCostParameter>  $query
     * @return Builder<UnitCostParameter>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function isDerived(): bool
    {
        return $this->source_type === self::SOURCE_DERIVED;
    }

    public function requiresUnit(): bool
    {
        return in_array($this->scope, [self::SCOPE_UNIT, self::SCOPE_UNIT_PERIOD], true);
    }

    public function requiresPeriod(): bool
    {
        return in_array($this->scope, [self::SCOPE_PERIOD, self::SCOPE_UNIT_PERIOD], true);
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

    public function dataType(): string
    {
        return (string) ($this->value_type ?: self::VALUE_DECIMAL);
    }
}
