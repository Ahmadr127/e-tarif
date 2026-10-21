<?php

namespace App\Http\Services\Calculation;

/**
 * Hasil kalkulasi di memori sebelum/sesudah disimpan.
 */
class CalculationResult
{
    /**
     * @param  array<string, float>  $values
     * @param  array<int, array<string, mixed>>  $details
     */
    public function __construct(
        public readonly string $unitCode,
        public readonly int $periodYear,
        public readonly array $values,
        public readonly array $details,
        public readonly int $formulaVersion,
        public readonly ?float $totalUnitCost = null,
    ) {}

    /**
     * @return array<int, string>
     */
    public function formulaCodes(): array
    {
        $codes = [];

        foreach ($this->details as $detail) {
            if (($detail['detail_type'] ?? null) === 'formula'
                || ($detail['detail_type'] ?? null) === 'allocation') {
                $codes[] = (string) $detail['code'];
            }
        }

        return $codes;
    }
}
