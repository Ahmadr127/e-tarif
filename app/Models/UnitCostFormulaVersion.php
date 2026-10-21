<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnitCostFormulaVersion extends Model
{
    protected $fillable = [
        'parameter_id',
        'period_id',
        'version',
        'expression',
        'created_by',
    ];

    protected $casts = [
        'version' => 'integer',
    ];

    /**
     * @return BelongsTo<UnitCostParameter, $this>
     */
    public function parameter(): BelongsTo
    {
        return $this->belongsTo(UnitCostParameter::class, 'parameter_id');
    }

    /**
     * @return BelongsTo<UnitCostPeriod, $this>
     */
    public function period(): BelongsTo
    {
        return $this->belongsTo(UnitCostPeriod::class, 'period_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
