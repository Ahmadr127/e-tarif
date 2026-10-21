<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnitCostParameterEntry extends Model
{
    public const INPUT_VALUE = 'value';

    public const INPUT_FORMULA = 'formula';

    public const STATUS_VALID = 'valid';

    public const STATUS_INCOMPLETE = 'incomplete';

    public const STATUS_INVALID = 'invalid';

    protected $fillable = [
        'period_id',
        'parameter_id',
        'unit_id',
        'scope_key',
        'input_type',
        'input_value',
        'formula_version_id',
        'formula_text',
        'result_value',
        'validation_status',
        'validation_error',
        'updated_by',
    ];

    protected $casts = [
        'input_value' => 'decimal:6',
        'result_value' => 'decimal:6',
    ];

    /**
     * @return BelongsTo<UnitCostPeriod, $this>
     */
    public function period(): BelongsTo
    {
        return $this->belongsTo(UnitCostPeriod::class, 'period_id');
    }

    /**
     * @return BelongsTo<UnitCostParameter, $this>
     */
    public function parameter(): BelongsTo
    {
        return $this->belongsTo(UnitCostParameter::class, 'parameter_id');
    }

    /**
     * @return BelongsTo<OrganizationUnit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(OrganizationUnit::class, 'unit_id');
    }

    /**
     * @return BelongsTo<UnitCostFormulaVersion, $this>
     */
    public function formulaVersion(): BelongsTo
    {
        return $this->belongsTo(UnitCostFormulaVersion::class, 'formula_version_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public static function scopeKeyFor(int $periodId, int $parameterId, ?int $unitId): string
    {
        return 'period:'.$periodId.'|parameter:'.$parameterId.'|unit:'.($unitId ?? 'global');
    }

    /**
     * @param  Builder<UnitCostParameterEntry>  $query
     * @return Builder<UnitCostParameterEntry>
     */
    public function scopeForPeriod(Builder $query, int $periodId): Builder
    {
        return $query->where('period_id', $periodId);
    }

    public function isConfigured(): bool
    {
        if ($this->input_type === self::INPUT_FORMULA) {
            return trim((string) $this->formula_text) !== '';
        }

        return $this->input_value !== null && $this->input_value !== '';
    }

    public function statusLabel(): string
    {
        return match ($this->validation_status) {
            self::STATUS_VALID => 'Valid',
            self::STATUS_INVALID => 'Error',
            default => 'Belum lengkap',
        };
    }
}
