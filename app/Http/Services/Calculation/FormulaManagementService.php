<?php

namespace App\Http\Services\Calculation;

use App\Models\CalculationFormula;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Menyimpan formula sebagai versi baru.
 *
 * Formula lama tidak diubah agar snapshot run historis tetap konsisten.
 */
class FormulaManagementService
{
    public function __construct(protected FormulaEngine $engine) {}

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws CalculationException
     */
    public function save(array $data, ?CalculationFormula $existing = null): CalculationFormula
    {
        $code = trim((string) ($existing?->code ?? $data['code']));
        $expression = trim((string) $data['expression']);
        $effectiveFrom = $data['effective_from'] ?? null;
        $effectiveUntil = $data['effective_until'] ?? null;
        $periodYear = $effectiveFrom
            ? CarbonImmutable::parse((string) $effectiveFrom)->year
            : (int) now()->year;

        $validation = $this->engine->validateFormula(
            $expression,
            $code,
            $periodYear,
            isset($data['component_id']) ? (int) $data['component_id'] : null,
        );

        return DB::transaction(function () use (
            $data,
            $existing,
            $code,
            $expression,
            $effectiveFrom,
            $effectiveUntil,
            $validation,
        ): CalculationFormula {
            $nextVersion = ((int) CalculationFormula::query()
                ->where('code', $code)
                ->max('version')) + 1;

            $isActive = (bool) ($data['is_active'] ?? false);

            if (! $isActive) {
                CalculationFormula::query()
                    ->where('code', $code)
                    ->update(['is_active' => false]);
            }

            $validationNotes = sprintf(
                'Tervalidasi %s. Referensi: %s.',
                now()->format('d/m/Y H:i'),
                $validation['references'] === [] ? 'tidak ada' : implode(', ', $validation['references'])
            );

            $formula = new CalculationFormula([
                'code' => $code,
                'name' => trim((string) $data['name']),
                'component_id' => $data['component_id'] ?? null,
                'expression' => $expression,
                'result_unit' => $data['result_unit'] ?? null,
                'result_scope' => $data['result_scope'] ?? 'unit_period',
                'version' => $nextVersion,
                'effective_from' => $effectiveFrom ?: null,
                'effective_until' => $effectiveUntil ?: null,
                'source_note' => $data['source_note'] ?? null,
                'validation_notes' => $validationNotes,
                'is_active' => $isActive,
            ]);

            $formula->save();

            return $formula->fresh(['component']);
        });
    }
}
