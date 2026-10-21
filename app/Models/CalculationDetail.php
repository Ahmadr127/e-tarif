<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalculationDetail extends Model
{
    public const TYPE_INPUT = 'input';

    public const TYPE_FORMULA = 'formula';

    public const TYPE_ALLOCATION = 'allocation';

    protected $fillable = [
        'calculation_run_id',
        'formula_id',
        'code',
        'name',
        'detail_type',
        'formula_version',
        'input_snapshot',
        'expression',
        'result_value',
        'result_unit',
        'source_name',
        'notes',
    ];

    protected $casts = [
        'formula_version' => 'integer',
        'input_snapshot' => 'array',
        'result_value' => 'decimal:6',
    ];

    /**
     * @return BelongsTo<CalculationRun, $this>
     */
    public function calculationRun(): BelongsTo
    {
        return $this->belongsTo(CalculationRun::class);
    }

    /**
     * @return BelongsTo<CalculationFormula, $this>
     */
    public function formula(): BelongsTo
    {
        return $this->belongsTo(CalculationFormula::class, 'formula_id');
    }
}
