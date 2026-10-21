<?php

namespace App\Http\Services\Calculation;

use App\Models\CalculationDetail;
use App\Models\CalculationFormula;
use App\Models\CalculationVariable;
use App\Models\CalculationVariableValue;
use App\Models\OrganizationUnit;
use Illuminate\Support\Collection;

/**
 * Engine formula generik untuk Global Variable dan formula free-text.
 *
 * Semua formula dievaluasi dari database. Tidak ada mapping formula per
 * komponen di controller/service dan tidak ada eval().
 */
class FormulaEngine
{
    public function __construct(
        protected FormulaParser $parser,
        protected DependencyResolver $dependencyResolver,
        protected VariableResolver $variableResolver,
    ) {}

    /**
     * Hitung semua formula aktif untuk konteks unit/periode tanpa menyimpan run.
     *
     * @param  array<string, mixed>  $inputs
     *
     * @throws CalculationException
     */
    public function preview(string $unitCode, int $periodYear, array $inputs = []): CalculationResult
    {
        $unitCode = $this->normalizeUnitCode($unitCode);
        $this->validatePeriod($periodYear);

        $unit = OrganizationUnit::query()->where('code', $unitCode)->first();
        $variables = $this->activeVariables();
        $formulas = $this->activeFormulas($periodYear);

        return $this->evaluate(
            unitCode: $unitCode,
            periodYear: $periodYear,
            inputs: $inputs,
            formulas: $formulas,
            variables: $variables,
            unitId: $unit?->id,
        );
    }

    /**
     * Preview formula yang belum tentu sudah disimpan.
     *
     * Formula kandidat digabung dengan formula aktif lain, lalu dihitung
     * melalui graph dependensi yang sama dengan perhitungan final.
     *
     * @param  array<string, mixed>  $inputs
     *
     * @throws CalculationException
     */
    public function previewFormula(
        string $unitCode,
        int $periodYear,
        string $expression,
        ?string $code = null,
        ?int $componentId = null,
        ?string $resultUnit = null,
        array $inputs = [],
    ): CalculationResult {
        $unitCode = $this->normalizeUnitCode($unitCode);
        $this->validatePeriod($periodYear);

        $candidateCode = $this->normalizeFormulaCode($code ?: '__preview__');
        $variables = $this->activeVariables();
        $formulas = $this->activeFormulas($periodYear);

        $this->validateCandidate($expression, $candidateCode, $variables, $formulas);

        $existing = $formulas->firstWhere('code', $candidateCode);
        $currentVersion = $formulas
            ->where('code', $candidateCode)
            ->max('version') ?? 0;

        $candidate = new CalculationFormula([
            'code' => $candidateCode,
            'name' => 'Preview formula',
            'component_id' => $componentId,
            'expression' => $expression,
            'result_unit' => $resultUnit,
            'result_scope' => 'unit_period',
            'version' => $currentVersion + 1,
            'is_active' => true,
        ]);

        if ($existing !== null) {
            $candidate->setAttribute('id', $existing->id);
        }

        $withCandidate = $formulas
            ->reject(static fn (CalculationFormula $formula): bool => $formula->code === $candidateCode)
            ->push($candidate)
            ->values();

        $unit = OrganizationUnit::query()->where('code', $unitCode)->first();

        return $this->evaluate(
            unitCode: $unitCode,
            periodYear: $periodYear,
            inputs: $inputs,
            formulas: $withCandidate,
            variables: $variables,
            unitId: $unit?->id,
        );
    }

    /**
     * Validasi sintaks, referensi, dan siklus formula.
     *
     * @return array{valid: bool, candidate_code: string, references: array<int, string>, ordered_codes: array<int, string>}
     *
     * @throws CalculationException
     */
    public function validateFormula(
        string $expression,
        ?string $code = null,
        ?int $periodYear = null,
        ?int $componentId = null,
    ): array {
        $periodYear = $periodYear ?? (int) now()->year;
        $this->validatePeriod($periodYear);

        $candidateCode = $this->normalizeFormulaCode($code ?: '__candidate__');
        $variables = $this->activeVariables();
        $formulas = $this->activeFormulas($periodYear);

        $references = $this->validateCandidate($expression, $candidateCode, $variables, $formulas);
        $ordered = $this->dependencyResolver->resolve(
            $formulas
                ->reject(static fn (CalculationFormula $formula): bool => $formula->code === $candidateCode)
                ->push((object) ['code' => $candidateCode, 'expression' => $expression])
                ->values()
        );

        return [
            'valid' => true,
            'candidate_code' => $candidateCode,
            'references' => $references,
            'ordered_codes' => $ordered,
        ];
    }

    /**
     * @return array<int, string>
     *
     * @throws CalculationException
     */
    public function references(string $expression): array
    {
        return $this->parser->dependencies($expression);
    }

    /**
     * Daftar variabel input yang dibutuhkan formula aktif pada tahun tertentu.
     *
     * @return array<int, string>
     */
    public function requiredVariableCodes(int $periodYear): array
    {
        $formulas = $this->activeFormulas($periodYear);
        $variables = $this->activeVariables()->keyBy('code');
        $formulaCodes = array_fill_keys($formulas->pluck('code')->map('strval')->all(), true);

        $codes = [];

        foreach ($formulas as $formula) {
            foreach ($this->parser->dependencies((string) $formula->expression) as $dependency) {
                if (array_key_exists($dependency, $formulaCodes)) {
                    continue;
                }

                $variable = $variables->get($dependency);

                if ($variable !== null && ! $variable->isDerived()) {
                    $codes[$dependency] = true;
                }
            }
        }

        $codes = array_keys($codes);
        sort($codes, SORT_STRING);

        return $codes;
    }

    /**
     * @return Collection<int, CalculationFormula>
     */
    public function activeFormulas(int $periodYear): Collection
    {
        $this->validatePeriod($periodYear);

        $formulas = CalculationFormula::query()
            ->active()
            ->effectiveForYear($periodYear)
            ->orderBy('code')
            ->orderByDesc('version')
            ->orderByDesc('effective_from')
            ->get();

        // Bila ada beberapa versi yang sama-sama berlaku, versi tertinggi
        // yang dipakai. Versi lama tetap tersimpan untuk audit.
        return $formulas->unique('code')->values();
    }

    /**
     * @return Collection<int, CalculationVariable>
     */
    public function activeVariables(): Collection
    {
        return CalculationVariable::query()
            ->active()
            ->orderBy('code')
            ->get();
    }

    /**
     * @param  Collection<int, CalculationFormula>  $formulas
     * @param  Collection<int, CalculationVariable>  $variables
     * @return array<int, string>
     *
     * @throws CalculationException
     */
    private function validateCandidate(
        string $expression,
        string $candidateCode,
        Collection $variables,
        Collection $formulas,
    ): array {
        $references = $this->parser->dependencies($expression);
        $formulaCodes = array_fill_keys($formulas->pluck('code')->map('strval')->all(), true);
        $formulaCodes[$candidateCode] = true;

        $variableMap = $variables->keyBy('code');
        $candidateVariable = $variableMap->get($candidateCode);

        if ($candidateVariable !== null && ! $candidateVariable->isDerived()) {
            throw new CalculationException(
                "Kode formula '{$candidateCode}' sudah dipakai oleh variabel input."
            );
        }

        foreach ($references as $reference) {
            if (array_key_exists($reference, $formulaCodes)) {
                continue;
            }

            $variable = $variableMap->get($reference);

            if ($variable !== null) {
                if ($variable->isDerived()) {
                    throw new CalculationException(
                        "Variabel turunan '{$reference}' tidak memiliki formula aktif."
                    );
                }

                continue;
            }

            $inactive = CalculationVariable::query()
                ->where('code', $reference)
                ->where('is_active', false)
                ->exists();

            if ($inactive) {
                throw new CalculationException(
                    "Variabel '{$reference}' tidak aktif sehingga tidak dapat dipakai formula."
                );
            }

            throw new CalculationException(
                "Referensi variabel '{$reference}' tidak ditemukan."
            );
        }

        return $references;
    }

    /**
     * @param  array<string, mixed>  $inputs
     * @param  Collection<int, CalculationFormula>  $formulas
     * @param  Collection<int, CalculationVariable>  $variables
     *
     * @throws CalculationException
     */
    private function evaluate(
        string $unitCode,
        int $periodYear,
        array $inputs,
        Collection $formulas,
        Collection $variables,
        ?int $unitId,
    ): CalculationResult {
        /** @var array<string, CalculationFormula> $formulaMap */
        $formulaMap = $formulas->keyBy('code')->all();
        /** @var array<string, CalculationVariable> $variableMap */
        $variableMap = $variables->keyBy('code')->all();

        $formulaCodes = array_fill_keys(array_map('strval', array_keys($formulaMap)), true);
        $referencedVariables = [];
        $derivedWithoutFormula = [];

        foreach ($formulaMap as $formula) {
            foreach ($this->parser->dependencies((string) $formula->expression) as $dependency) {
                if (array_key_exists($dependency, $formulaCodes)) {
                    continue;
                }

                $variable = $variableMap[$dependency] ?? null;

                if ($variable === null) {
                    $inactive = CalculationVariable::query()
                        ->where('code', $dependency)
                        ->where('is_active', false)
                        ->exists();

                    throw new CalculationException(
                        $inactive
                            ? "Variabel '{$dependency}' tidak aktif sehingga tidak dapat dipakai formula '{$formula->code}'."
                            : "Formula '{$formula->code}' mereferensikan '{$dependency}' yang tidak dikenal."
                    );
                }

                if ($variable->isDerived()) {
                    $derivedWithoutFormula[] = $dependency;

                    continue;
                }

                $referencedVariables[$dependency] = true;
            }
        }

        if ($derivedWithoutFormula !== []) {
            $codes = array_values(array_unique($derivedWithoutFormula));
            sort($codes, SORT_STRING);

            throw new CalculationException(
                'Variabel turunan berikut tidak memiliki formula aktif: '.implode(', ', $codes).'.'
            );
        }

        $requiredCodes = array_keys($referencedVariables);
        $storedValues = CalculationVariableValue::valuesForContext($variables, $unitId, $periodYear);
        $resolution = $this->variableResolver->resolveDetailed(
            $variables,
            $inputs,
            $requiredCodes,
            $storedValues
        );

        $values = $resolution['values'];
        $details = $this->buildInputDetails(
            $variables,
            $values,
            $resolution['sources'],
            $referencedVariables
        );

        $orderedCodes = $this->dependencyResolver->resolve($formulas);
        $formulaVersion = 1;

        foreach ($orderedCodes as $code) {
            $formula = $formulaMap[$code] ?? null;

            if ($formula === null) {
                throw new CalculationException("Formula '{$code}' tidak ditemukan saat evaluasi.");
            }

            $dependencies = $this->parser->dependencies((string) $formula->expression);
            $snapshot = [];

            foreach ($dependencies as $dependency) {
                if (! array_key_exists($dependency, $values)) {
                    throw new CalculationException(
                        "Nilai '{$dependency}' belum tersedia saat mengevaluasi formula '{$code}'."
                    );
                }

                $snapshot[$dependency] = $values[$dependency];
            }

            try {
                $result = $this->parser->evaluate((string) $formula->expression, $values);
            } catch (CalculationException $exception) {
                throw new CalculationException(
                    "Formula '{$code}' gagal: {$exception->getMessage()}",
                    array_merge($exception->context, ['formula' => $code])
                );
            }

            $values[$code] = $result;
            $formulaVersion = max($formulaVersion, (int) $formula->version);

            $details[] = [
                'formula_id' => $formula->id,
                'code' => $formula->code,
                'name' => $formula->name,
                'detail_type' => $formula->isAllocationFormula()
                    ? CalculationDetail::TYPE_ALLOCATION
                    : CalculationDetail::TYPE_FORMULA,
                'formula_version' => (int) $formula->version,
                'input_snapshot' => $snapshot,
                'expression' => $formula->expression,
                'result_value' => $result,
                'result_unit' => $formula->result_unit,
                'source_name' => 'Formula',
                'notes' => $formula->source_note,
            ];
        }

        return new CalculationResult(
            unitCode: $unitCode,
            periodYear: $periodYear,
            values: $values,
            details: $details,
            formulaVersion: $formulaVersion,
            totalUnitCost: null,
        );
    }

    /**
     * @param  Collection<int, CalculationVariable>  $variables
     * @param  array<string, float>  $values
     * @param  array<string, array{source: string, source_name: string|null}>  $sources
     * @param  array<string, bool>  $referencedVariables
     * @return array<int, array<string, mixed>>
     */
    private function buildInputDetails(
        Collection $variables,
        array $values,
        array $sources,
        array $referencedVariables,
    ): array {
        $details = [];

        foreach ($variables as $variable) {
            $code = (string) $variable->code;

            if (! array_key_exists($code, $referencedVariables)
                || $variable->isDerived()
                || ! array_key_exists($code, $values)) {
                continue;
            }

            $source = $sources[$code] ?? ['source' => 'unknown', 'source_name' => null];
            $sourceName = $source['source_name'];

            if ($source['source'] === 'input' && $variable->source_name) {
                $sourceName = $variable->source_name;
            }

            $details[] = [
                'formula_id' => null,
                'code' => $code,
                'name' => $variable->name,
                'detail_type' => $variable->isRatio()
                    ? CalculationDetail::TYPE_ALLOCATION
                    : CalculationDetail::TYPE_INPUT,
                'formula_version' => null,
                'input_snapshot' => null,
                'expression' => null,
                'result_value' => $values[$code],
                'result_unit' => $variable->unit,
                'source_name' => $sourceName,
                'notes' => match ($source['source']) {
                    'default' => 'Nilai diambil dari default variabel.',
                    'stored' => 'Nilai diambil dari master nilai variabel.',
                    'input' => null,
                    default => null,
                },
            ];
        }

        return $details;
    }

    private function normalizeUnitCode(string $unitCode): string
    {
        $unitCode = mb_strtoupper(trim($unitCode));

        if ($unitCode === '') {
            throw new CalculationException('Kode unit wajib diisi.');
        }

        if (mb_strlen($unitCode) > 80) {
            throw new CalculationException('Kode unit maksimal 80 karakter.');
        }

        return $unitCode;
    }

    private function normalizeFormulaCode(string $code): string
    {
        $code = trim($code);

        if ($code === '') {
            return '__preview__';
        }

        if (mb_strlen($code) > 120 || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $code) !== 1) {
            throw new CalculationException(
                'Kode formula hanya boleh berisi huruf, angka, dan garis bawah, maksimal 120 karakter.'
            );
        }

        return $code;
    }

    private function validatePeriod(int $periodYear): void
    {
        if ($periodYear < 2000 || $periodYear > 2100) {
            throw new CalculationException('Tahun periode harus berada pada rentang 2000-2100.');
        }
    }
}
