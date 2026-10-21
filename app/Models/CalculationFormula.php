<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CalculationFormula extends Model
{
    protected $fillable = [
        'code',
        'name',
        'component_id',
        'expression',
        'result_unit',
        'result_scope',
        'version',
        'effective_from',
        'effective_until',
        'source_note',
        'validation_notes',
        'is_active',
    ];

    protected $casts = [
        'version' => 'integer',
        'effective_from' => 'date',
        'effective_until' => 'date',
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
     * @return HasMany<CalculationDetail, $this>
     */
    public function details(): HasMany
    {
        return $this->hasMany(CalculationDetail::class, 'formula_id');
    }

    /**
     * @param  Builder<CalculationFormula>  $query
     * @return Builder<CalculationFormula>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Formula yang berlaku pada tahun periode tertentu.
     *
     * @param  Builder<CalculationFormula>  $query
     * @return Builder<CalculationFormula>
     */
    public function scopeEffectiveForYear(Builder $query, int $year): Builder
    {
        $start = sprintf('%04d-01-01', $year);
        $end = sprintf('%04d-12-31', $year);

        return $query
            ->where(function (Builder $query) use ($end): void {
                $query->whereNull('effective_from')
                    ->orWhere('effective_from', '<=', $end);
            })
            ->where(function (Builder $query) use ($start): void {
                $query->whereNull('effective_until')
                    ->orWhere('effective_until', '>=', $start);
            });
    }

    public function isAllocationFormula(): bool
    {
        return str_contains($this->code, 'allocation');
    }
}
