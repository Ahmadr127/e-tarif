<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalculationVariableValue extends Model
{
    protected $fillable = [
        'calculation_variable_id',
        'organization_unit_id',
        'period_year',
        'scope_key',
        'value',
        'source_name',
        'notes',
    ];

    protected $casts = [
        'period_year' => 'integer',
        'value' => 'decimal:6',
    ];

    /**
     * @return BelongsTo<CalculationVariable, $this>
     */
    public function variable(): BelongsTo
    {
        return $this->belongsTo(CalculationVariable::class, 'calculation_variable_id');
    }

    /**
     * @return BelongsTo<OrganizationUnit, $this>
     */
    public function organizationUnit(): BelongsTo
    {
        return $this->belongsTo(OrganizationUnit::class);
    }

    /**
     * Kunci scope yang stabil untuk kombinasi variabel, unit, dan periode.
     */
    public static function scopeKeyFor(CalculationVariable $variable, ?int $unitId, ?int $periodYear): string
    {
        $suffix = match ($variable->scope) {
            CalculationVariable::SCOPE_GLOBAL => 'global',
            CalculationVariable::SCOPE_PERIOD => 'period:'.($periodYear ?? '-'),
            CalculationVariable::SCOPE_UNIT => 'unit:'.($unitId ?? '-'),
            default => 'unit:'.($unitId ?? '-').'|period:'.($periodYear ?? '-'),
        };

        return 'var:'.$variable->getKey().'|'.$suffix;
    }

    /**
     * Ambil nilai yang berlaku untuk konteks unit/periode dalam satu query.
     *
     * @param  iterable<int, CalculationVariable>  $variables
     * @return array<string, array{value: float, source_name: string|null}>
     */
    public static function valuesForContext(iterable $variables, ?int $unitId, int $periodYear): array
    {
        $scopeKeys = [];
        $variablesByScopeKey = [];

        foreach ($variables as $variable) {
            $key = self::scopeKeyFor($variable, $unitId, $periodYear);
            $scopeKeys[] = $key;
            $variablesByScopeKey[$key] = $variable;
        }

        if ($scopeKeys === []) {
            return [];
        }

        $records = self::query()
            ->whereIn('scope_key', array_values(array_unique($scopeKeys)))
            ->get()
            ->keyBy('scope_key');

        $values = [];

        foreach ($variablesByScopeKey as $key => $variable) {
            $record = $records->get($key);

            if ($record === null) {
                continue;
            }

            $values[(string) $variable->code] = [
                'value' => (float) $record->value,
                'source_name' => $record->source_name,
            ];
        }

        return $values;
    }

    /**
     * @param  Builder<CalculationVariableValue>  $query
     * @return Builder<CalculationVariableValue>
     */
    public function scopeForYear(Builder $query, int $periodYear): Builder
    {
        return $query->where(function (Builder $query) use ($periodYear): void {
            $query->whereNull('period_year')->orWhere('period_year', $periodYear);
        });
    }
}
