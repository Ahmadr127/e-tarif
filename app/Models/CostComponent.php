<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CostComponent extends Model
{
    protected $fillable = [
        'code',
        'name',
        'category',
        'description',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * @return HasMany<CalculationVariable, $this>
     */
    public function variables(): HasMany
    {
        return $this->hasMany(CalculationVariable::class, 'component_id');
    }

    /**
     * @return HasMany<CalculationFormula, $this>
     */
    public function formulas(): HasMany
    {
        return $this->hasMany(CalculationFormula::class, 'component_id');
    }

    /**
     * @param  Builder<CostComponent>  $query
     * @return Builder<CostComponent>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
