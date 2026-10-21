<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UnitCostComponent extends Model
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
     * @return HasMany<UnitCostParameter, $this>
     */
    public function parameters(): HasMany
    {
        return $this->hasMany(UnitCostParameter::class, 'component_id');
    }

    /**
     * @param  Builder<UnitCostComponent>  $query
     * @return Builder<UnitCostComponent>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
