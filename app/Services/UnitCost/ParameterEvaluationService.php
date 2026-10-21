<?php

namespace App\Services\UnitCost;

use App\Models\OrganizationUnit;
use App\Models\UnitCostComponent;
use App\Models\UnitCostFormulaVersion;
use App\Models\UnitCostParameter;
use App\Models\UnitCostParameterEntry;
use App\Models\UnitCostPeriod;
use App\Models\UnitCostResultSnapshot;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ParameterEvaluationService
{
    public function __construct(protected FormulaParser $parser) {}

    /**
     * @return Collection<int, UnitCostParameter>
     */
    public function allActiveParameters(): Collection
    {
        return UnitCostParameter::query()
            ->active()
            ->with('component')
            ->orderBy('sort_order')
            ->orderBy('code')
            ->get();
    }

    /**
     * @return Collection<int, UnitCostParameter>
     */
    public function parametersForComponent(?int $componentId): Collection
    {
        return UnitCostParameter::query()
            ->active()
            ->with('component')
            ->when(
                $componentId === null,
                static fn ($query) => $query->whereNull('component_id'),
                static fn ($query) => $query->where('component_id', $componentId)
            )
            ->orderBy('sort_order')
            ->orderBy('code')
            ->get();
    }

    /**
     * Entry yang berlaku untuk periode dan konteks unit.
     *
     * Parameter non-unit memakai entry unit_id NULL. Parameter unit memakai
     * entry unit terpilih; entry NULL tidak dipakai agar nilai unit lain tidak
     * bocor ke konteks ini.
     *
     * @param  Collection<int, UnitCostParameter>|null  $parameters
     * @return Collection<int, UnitCostParameterEntry>
     */
    public function entriesForContext(
        UnitCostPeriod $period,
        ?OrganizationUnit $unit,
        ?Collection $parameters = null,
    ): Collection {
        $parameters ??= $this->allActiveParameters();
        $parametersById = $parameters->keyBy('id');
        $unitId = $unit?->id;

        $entries = UnitCostParameterEntry::query()
            ->with('formulaVersion')
            ->where('period_id', $period->id)
            ->where(function ($query) use ($unitId): void {
                $query->whereNull('unit_id');

                if ($unitId !== null) {
                    $query->orWhere('unit_id', $unitId);
                }
            })
            ->get();

        $map = collect();

        foreach ($entries as $entry) {
            /** @var UnitCostParameter|null $parameter */
            $parameter = $parametersById->get($entry->parameter_id);

            if ($parameter === null) {
                continue;
            }

            $expectedUnitId = $parameter->requiresUnit() ? $unitId : null;

            if ($parameter->requiresUnit() && $expectedUnitId === null) {
                continue;
            }

            if ((int) ($entry->unit_id ?? 0) !== (int) ($expectedUnitId ?? 0)) {
                continue;
            }

            $map->put($parameter->id, $entry);
        }

        return $map;
    }

    public function evaluate(
        UnitCostPeriod $period,
        ?OrganizationUnit $unit,
        array $overrides = [],
        ?array $onlyCodes = null,
    ): EvaluationResult {
        $parameters = $this->allActiveParameters();
        $entries = $this->entriesForContext($period, $unit, $parameters);

        return $this->evaluateContext($period, $unit, $parameters, $entries, $overrides, $onlyCodes);
    }

    /**
     * Simpan konfigurasi seluruh parameter pada satu komponen.
     *
     * @param  array<int|string, array<string, mixed>>  $entriesData
     *
     * @throws ValidationException
     */
    public function save(
        UnitCostPeriod $period,
        ?OrganizationUnit $unit,
        ?int $componentId,
        array $entriesData,
        ?int $userId,
    ): EvaluationResult {
        $this->ensureEditable($period);

        $parameters = $this->parametersForComponent($componentId)->keyBy('id');
        $changedCodes = [];

        DB::transaction(function () use (
            $period,
            $unit,
            $parameters,
            $entriesData,
            $userId,
            &$changedCodes,
        ): void {
            foreach ($parameters as $parameterId => $parameter) {
                if (! array_key_exists($parameterId, $entriesData)) {
                    continue;
                }

                $data = is_array($entriesData[$parameterId]) ? $entriesData[$parameterId] : [];
                $inputType = (string) ($data['input_type'] ?? UnitCostParameterEntry::INPUT_VALUE);

                if (! in_array($inputType, [UnitCostParameterEntry::INPUT_VALUE, UnitCostParameterEntry::INPUT_FORMULA], true)) {
                    throw ValidationException::withMessages([
                        "entries.{$parameterId}.input_type" => 'Metode input tidak dikenal.',
                    ]);
                }

                $unitId = $this->unitIdFor($parameter, $unit);

                if ($parameter->requiresUnit() && $unitId === null) {
                    throw ValidationException::withMessages([
                        "entries.{$parameterId}.input_value" => 'Pilih unit terlebih dahulu untuk parameter berscope unit.',
                    ]);
                }

                $scopeKey = UnitCostParameterEntry::scopeKeyFor($period->id, $parameter->id, $unitId);
                $current = UnitCostParameterEntry::query()->where('scope_key', $scopeKey)->first();

                $inputValue = null;
                $formulaText = null;
                $formulaVersionId = null;

                if ($inputType === UnitCostParameterEntry::INPUT_VALUE) {
                    $raw = $data['input_value'] ?? null;

                    if ($raw !== null && trim((string) $raw) !== '') {
                        try {
                            $inputValue = $this->normalizeValue($parameter, $raw);
                        } catch (FormulaException $exception) {
                            throw ValidationException::withMessages([
                                "entries.{$parameterId}.input_value" => $exception->getMessage(),
                            ]);
                        }
                    }
                } else {
                    $formulaText = trim((string) ($data['formula_text'] ?? ''));

                    if (mb_strlen($formulaText) > FormulaParser::MAX_EXPRESSION_LENGTH) {
                        throw ValidationException::withMessages([
                            "entries.{$parameterId}.formula_text" => 'Rumus maksimal '.FormulaParser::MAX_EXPRESSION_LENGTH.' karakter.',
                        ]);
                    }

                    if ($formulaText !== '') {
                        $formulaVersionId = $this->formulaVersionIdFor(
                            $period,
                            $parameter,
                            $formulaText,
                            $current,
                            $userId,
                        );
                    }
                }

                $changed = $current === null
                    || $current->input_type !== $inputType
                    || ! $this->sameNumericValue($current->input_value, $inputValue)
                    || trim((string) $current->formula_text) !== trim((string) $formulaText);

                if ($changed) {
                    $changedCodes[$parameter->formulaKey()] = true;
                }

                UnitCostParameterEntry::query()->updateOrCreate(
                    ['scope_key' => $scopeKey],
                    [
                        'period_id' => $period->id,
                        'parameter_id' => $parameter->id,
                        'unit_id' => $unitId,
                        'input_type' => $inputType,
                        'input_value' => $inputValue,
                        'formula_version_id' => $formulaVersionId,
                        'formula_text' => $formulaText,
                        'result_value' => null,
                        'validation_status' => UnitCostParameterEntry::STATUS_INCOMPLETE,
                        'validation_error' => null,
                        'updated_by' => $userId,
                    ]
                );
            }

            $affectedCodes = $this->affectedCodes(array_keys($changedCodes), $period, $unit);

            if ($affectedCodes === []) {
                return;
            }

            $result = $this->evaluate($period->fresh(), $unit, [], $affectedCodes);
            $persistCodes = array_fill_keys($affectedCodes, true);

            foreach ($result->parameters as $row) {
                /** @var UnitCostParameterEntry|null $entry */
                $entry = $row['entry'];

                if ($entry === null || ! isset($persistCodes[$row['parameter']->formulaKey()])) {
                    continue;
                }

                $entry->forceFill([
                    'result_value' => $row['result_value'],
                    'validation_status' => $row['status'],
                    'validation_error' => $row['error'],
                ])->save();
            }
        });

        return $this->evaluate($period->fresh(), $unit);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function componentSummaries(UnitCostPeriod $period, ?OrganizationUnit $unit): array
    {
        $result = $this->evaluate($period, $unit);
        $summaries = [];

        foreach ($result->parameters as $row) {
            $componentId = $row['parameter']->component_id;
            $key = $componentId === null ? 'global' : (string) $componentId;

            if (! array_key_exists($key, $summaries)) {
                $summaries[$key] = [
                    'total' => 0,
                    'configured' => 0,
                    'valid' => 0,
                    'incomplete' => 0,
                    'invalid' => 0,
                    'last_updated' => null,
                ];
            }

            $summaries[$key]['total']++;
            $summaries[$key][$row['status']]++;

            if ($row['entry']?->isConfigured()) {
                $summaries[$key]['configured']++;
            }

            $updatedAt = $row['entry']?->updated_at;

            if ($updatedAt !== null
                && ($summaries[$key]['last_updated'] === null || $updatedAt->gt($summaries[$key]['last_updated']))) {
                $summaries[$key]['last_updated'] = $updatedAt;
            }
        }

        foreach ($summaries as &$summary) {
            $summary['status'] = $summary['invalid'] > 0
                ? 'invalid'
                : ($summary['incomplete'] > 0 ? 'incomplete' : 'valid');
        }

        unset($summary);

        return $summaries;
    }

    /**
     * @return array{total: int, configured: int, valid: int, incomplete: int, invalid: int, components: int, last_updated: mixed}
     */
    public function periodStats(UnitCostPeriod $period): array
    {
        $total = UnitCostParameter::query()->active()->count();

        $entries = UnitCostParameterEntry::query()->where('period_id', $period->id);

        $configured = (clone $entries)
            ->where(function ($query): void {
                $query->where(function ($query): void {
                    $query->where('input_type', UnitCostParameterEntry::INPUT_VALUE)
                        ->whereNotNull('input_value');
                })->orWhere(function ($query): void {
                    $query->where('input_type', UnitCostParameterEntry::INPUT_FORMULA)
                        ->whereNotNull('formula_text')
                        ->where('formula_text', '!=', '');
                });
            })
            ->distinct()
            ->count('parameter_id');

        $valid = (clone $entries)
            ->where('validation_status', UnitCostParameterEntry::STATUS_VALID)
            ->distinct()
            ->count('parameter_id');

        $invalid = (clone $entries)
            ->where('validation_status', UnitCostParameterEntry::STATUS_INVALID)
            ->distinct()
            ->count('parameter_id');

        $components = UnitCostComponent::query()->active()->count();

        if (UnitCostParameter::query()->active()->whereNull('component_id')->exists()) {
            $components++;
        }

        return [
            'total' => $total,
            'configured' => $configured,
            'valid' => $valid,
            'incomplete' => max(0, $total - $valid),
            'invalid' => $invalid,
            'components' => $components,
            'last_updated' => (clone $entries)->max('updated_at'),
        ];
    }

    /**
     * @param  array<int, int>  $parameterIds
     * @return Collection<int, UnitCostFormulaVersion>
     */
    public function latestFormulaVersions(UnitCostPeriod $period, array $parameterIds): Collection
    {
        if ($parameterIds === []) {
            return collect();
        }

        return UnitCostFormulaVersion::query()
            ->where('period_id', $period->id)
            ->whereIn('parameter_id', $parameterIds)
            ->orderByDesc('version')
            ->get()
            ->unique('parameter_id')
            ->keyBy('parameter_id');
    }

    /**
     * @throws FormulaException
     */
    public function createSnapshot(
        UnitCostPeriod $period,
        ?OrganizationUnit $unit,
        ?int $userId,
    ): UnitCostResultSnapshot {
        $result = $this->evaluate($period, $unit);
        $blocking = $result->invalidOrIncomplete();

        if ($blocking !== []) {
            $codes = array_map(
                static fn (array $row): string => $row['parameter']->formulaKey(),
                $blocking
            );
            sort($codes, SORT_STRING);

            throw new FormulaException(
                'Hasil belum dapat disetujui. Parameter berikut belum valid: '.implode(', ', $codes).'.'
            );
        }

        $payload = [
            'period' => [
                'id' => $period->id,
                'code' => $period->code,
                'name' => $period->name,
                'period_year' => $period->period_year,
            ],
            'unit' => $unit === null ? null : [
                'id' => $unit->id,
                'code' => $unit->code,
                'name' => $unit->name,
            ],
            'evaluated_at' => now()->toIso8601String(),
            'parameters' => array_map(static function (array $row): array {
                return [
                    'parameter_id' => $row['parameter']->id,
                    'code' => $row['parameter']->code,
                    'variable' => $row['parameter']->formulaKey(),
                    'name' => $row['parameter']->name,
                    'unit' => $row['parameter']->unit,
                    'input_type' => $row['input_type'],
                    'input_value' => $row['input_value'],
                    'formula_text' => $row['formula_text'],
                    'formula_version' => $row['formula_version'],
                    'result_value' => $row['result_value'],
                    'status' => $row['status'],
                ];
            }, $result->parameters),
            'values' => $result->values,
            'summary' => $result->summary,
        ];

        return UnitCostResultSnapshot::create([
            'period_id' => $period->id,
            'unit_id' => $unit?->id,
            'status' => UnitCostResultSnapshot::STATUS_APPROVED,
            'payload' => $payload,
            'created_by' => $userId,
            'approved_by' => $userId,
            'approved_at' => now(),
        ]);
    }

    /**
     * @throws ValidationException
     */
    public function ensureEditable(UnitCostPeriod $period): void
    {
        if ($period->isClosed()) {
            throw ValidationException::withMessages([
                'period' => 'Periode yang sudah ditutup tidak dapat diubah.',
            ]);
        }
    }

    private function sameNumericValue(mixed $left, mixed $right): bool
    {
        $leftNull = $left === null || $left === '';
        $rightNull = $right === null;

        if ($leftNull || $rightNull) {
            return $leftNull && $rightNull;
        }

        return abs((float) $left - (float) $right) < 0.0000005;
    }

    /**
     * Kode parameter yang perlu dievaluasi ulang: parameter yang berubah dan
     * seluruh formula yang bergantung padanya (transitif).
     *
     * @param  array<int, string>  $changedCodes
     * @return array<int, string>
     */
    private function affectedCodes(array $changedCodes, UnitCostPeriod $period, ?OrganizationUnit $unit): array
    {
        if ($changedCodes === []) {
            return [];
        }

        $parameters = $this->allActiveParameters();
        $entries = $this->entriesForContext($period, $unit, $parameters);
        $reverseDependencies = [];

        foreach ($parameters as $parameter) {
            $entry = $entries->get($parameter->id);

            if ($entry === null || $entry->input_type !== UnitCostParameterEntry::INPUT_FORMULA) {
                continue;
            }

            $formulaText = trim((string) $entry->formula_text);

            if ($formulaText === '') {
                continue;
            }

            try {
                $dependencies = $this->parser->dependencies($formulaText);
            } catch (FormulaException) {
                continue;
            }

            foreach ($dependencies as $dependency) {
                $reverseDependencies[$dependency][] = $parameter->formulaKey();
            }
        }

        $affected = array_fill_keys($changedCodes, true);
        $queue = array_values($changedCodes);

        while ($queue !== []) {
            $code = array_shift($queue);

            foreach ($reverseDependencies[$code] ?? [] as $dependent) {
                if (! isset($affected[$dependent])) {
                    $affected[$dependent] = true;
                    $queue[] = $dependent;
                }
            }
        }

        return array_keys($affected);
    }

    private function unitIdFor(UnitCostParameter $parameter, ?OrganizationUnit $unit): ?int
    {
        return $parameter->requiresUnit() ? $unit?->id : null;
    }

    /**
     * @throws FormulaException
     */
    private function normalizeValue(UnitCostParameter $parameter, mixed $rawValue): float
    {
        $code = (string) $parameter->code;
        $valueType = $parameter->dataType();

        if ($valueType === 'string') {
            throw new FormulaException("Parameter '{$code}' bertipe teks dan tidak dapat dipakai dalam perhitungan.");
        }

        if ($valueType === 'boolean' && is_string($rawValue)) {
            $normalized = mb_strtolower(trim($rawValue));

            if (in_array($normalized, ['true', 'ya', '1'], true)) {
                $rawValue = 1;
            } elseif (in_array($normalized, ['false', 'tidak', '0'], true)) {
                $rawValue = 0;
            }
        }

        if (is_bool($rawValue)) {
            $value = $rawValue ? 1.0 : 0.0;
        } elseif (is_int($rawValue) || is_float($rawValue)) {
            $value = (float) $rawValue;
        } elseif (is_string($rawValue) && is_numeric(trim($rawValue))) {
            $value = (float) trim($rawValue);
        } else {
            throw new FormulaException("Parameter '{$code}' harus berupa angka.");
        }

        if (! is_finite($value)) {
            throw new FormulaException("Parameter '{$code}' bukan angka yang valid.");
        }

        if ($valueType === 'integer' && floor($value) !== $value) {
            throw new FormulaException("Parameter '{$code}' harus berupa bilangan bulat.");
        }

        if ($valueType === 'boolean' && ! in_array($value, [0.0, 1.0], true)) {
            throw new FormulaException("Parameter '{$code}' harus bernilai 0 atau 1.");
        }

        if ($parameter->isRatio() && ($value < 0.0 || $value > 1.0)) {
            throw new FormulaException("Parameter '{$code}' harus berada pada rentang 0 sampai 1.");
        }

        if ($parameter->isPercentage() && ($value < 0.0 || $value > 100.0)) {
            throw new FormulaException("Parameter '{$code}' harus berada pada rentang 0 sampai 100.");
        }

        return $value;
    }

    private function formulaVersionIdFor(
        UnitCostPeriod $period,
        UnitCostParameter $parameter,
        string $formulaText,
        ?UnitCostParameterEntry $current,
        ?int $userId,
    ): int {
        if ($current !== null
            && $current->formula_version_id !== null
            && trim((string) $current->formula_text) === $formulaText) {
            return (int) $current->formula_version_id;
        }

        $existing = UnitCostFormulaVersion::query()
            ->where('parameter_id', $parameter->id)
            ->where('period_id', $period->id)
            ->where('expression', $formulaText)
            ->orderByDesc('version')
            ->first();

        if ($existing !== null) {
            return (int) $existing->id;
        }

        $nextVersion = ((int) UnitCostFormulaVersion::query()
            ->where('parameter_id', $parameter->id)
            ->where('period_id', $period->id)
            ->max('version')) + 1;

        $version = UnitCostFormulaVersion::create([
            'parameter_id' => $parameter->id,
            'period_id' => $period->id,
            'version' => $nextVersion,
            'expression' => $formulaText,
            'created_by' => $userId,
        ]);

        return (int) $version->id;
    }

    /**
     * @param  Collection<int, UnitCostParameter>  $parameters
     * @param  Collection<int, UnitCostParameterEntry>  $entries
     * @param  array<int|string, array<string, mixed>>  $overrides
     * @param  array<int, string>|null  $onlyCodes
     */
    private function evaluateContext(
        UnitCostPeriod $period,
        ?OrganizationUnit $unit,
        Collection $parameters,
        Collection $entries,
        array $overrides,
        ?array $onlyCodes = null,
    ): EvaluationResult {
        $contexts = [];

        foreach ($parameters as $parameter) {
            $entry = $entries->get($parameter->id);
            $override = $overrides[$parameter->id] ?? $overrides[(string) $parameter->id] ?? null;
            $override = is_array($override) ? $override : null;

            $inputType = (string) ($override['input_type']
                ?? $entry?->input_type
                ?? ($parameter->isDerived() ? UnitCostParameterEntry::INPUT_FORMULA : UnitCostParameterEntry::INPUT_VALUE));

            $formulaText = array_key_exists('formula_text', $override ?? [])
                ? trim((string) $override['formula_text'])
                : trim((string) $entry?->formula_text);

            $formulaVersionId = $entry?->formula_version_id;

            if ($override !== null && array_key_exists('formula_text', $override)
                && trim((string) $override['formula_text']) !== trim((string) $entry?->formula_text)) {
                $formulaVersionId = null;
            }

            $inputValue = null;
            $valueError = null;
            $source = 'missing';

            if ($inputType === UnitCostParameterEntry::INPUT_VALUE) {
                $raw = array_key_exists('input_value', $override ?? [])
                    ? $override['input_value']
                    : $entry?->input_value;

                if ($raw !== null && trim((string) $raw) !== '') {
                    try {
                        $inputValue = $this->normalizeValue($parameter, $raw);
                        $source = 'value';
                    } catch (FormulaException $exception) {
                        $valueError = $exception->getMessage();
                    }
                } elseif ($entry === null && $parameter->default_value !== null) {
                    try {
                        $inputValue = $this->normalizeValue($parameter, $parameter->default_value);
                        $source = 'default';
                    } catch (FormulaException $exception) {
                        $valueError = $exception->getMessage();
                    }
                }
            }

            $contexts[$parameter->formulaKey()] = [
                'parameter' => $parameter,
                'entry' => $entry,
                'input_type' => $inputType,
                'input_value' => $inputValue,
                'formula_text' => $formulaText,
                'formula_version_id' => $formulaVersionId,
                'formula_version' => $entry?->formulaVersion?->version,
                'dependencies' => [],
                'parse_error' => null,
                'value_error' => $valueError,
                'status' => UnitCostParameterEntry::STATUS_INCOMPLETE,
                'result' => null,
                'error' => null,
                'source' => $source,
            ];
        }

        $formulaDependencies = [];

        foreach ($contexts as $code => &$context) {
            if ($context['input_type'] !== UnitCostParameterEntry::INPUT_FORMULA) {
                continue;
            }

            if ($context['formula_text'] === '') {
                continue;
            }

            try {
                $dependencies = $this->parser->dependencies($context['formula_text']);
            } catch (FormulaException $exception) {
                $context['parse_error'] = $exception->getMessage();

                continue;
            }

            $context['dependencies'] = $dependencies;
            $formulaDependencies[$code] = array_values(array_filter(
                $dependencies,
                static fn (string $dependency): bool => isset($contexts[$dependency])
                    && $contexts[$dependency]['input_type'] === UnitCostParameterEntry::INPUT_FORMULA
                    && $contexts[$dependency]['formula_text'] !== ''
            ));
        }

        unset($context);

        $cycleErrors = $this->detectCycles($formulaDependencies);
        $memo = [];
        $visiting = [];
        $affected = $onlyCodes === null ? null : array_fill_keys($onlyCodes, true);

        if ($affected === null) {
            foreach (array_keys($contexts) as $code) {
                $this->evaluateNode($code, $contexts, $memo, $visiting, $cycleErrors);
            }
        } else {
            foreach (array_keys($contexts) as $code) {
                if (! isset($affected[$code])) {
                    $this->hydrateContext($code, $contexts, $memo);
                }
            }

            foreach (array_keys($affected) as $code) {
                if (isset($contexts[$code])) {
                    $this->evaluateNode($code, $contexts, $memo, $visiting, $cycleErrors);
                }
            }
        }

        $rows = [];
        $values = [];
        $summary = [
            'total' => 0,
            'valid' => 0,
            'incomplete' => 0,
            'invalid' => 0,
        ];

        foreach ($contexts as $context) {
            $status = (string) $context['status'];
            $resultValue = $context['result'];
            $summary['total']++;
            $summary[$status] = ($summary[$status] ?? 0) + 1;

            if ($status === UnitCostParameterEntry::STATUS_VALID && $resultValue !== null) {
                $values[$context['parameter']->formulaKey()] = $resultValue;
            }

            $rows[] = [
                'parameter' => $context['parameter'],
                'entry' => $context['entry'],
                'input_type' => $context['input_type'],
                'input_value' => $context['input_value'],
                'formula_text' => $context['formula_text'],
                'formula_version_id' => $context['formula_version_id'],
                'formula_version' => $context['formula_version'],
                'dependencies' => $context['dependencies'],
                'result_value' => $resultValue,
                'status' => $status,
                'error' => $context['error'],
                'source' => $context['source'],
            ];
        }

        return new EvaluationResult($period, $unit, $rows, $values, $summary);
    }

    /**
     * Isi status/result node yang tidak termasuk affected dari entry tersimpan.
     * Formula yang tidak berubah tidak dihitung ulang; hasil resminya dipakai
     * kembali agar evaluasi benar-benar terbatas pada formula terdampak.
     *
     * @param  array<string, array<string, mixed>>  $contexts
     * @param  array<string, array{status: string, result: float|null, error: string|null}>  $memo
     */
    private function hydrateContext(string $code, array &$contexts, array &$memo): void
    {
        $context = $contexts[$code];
        $entry = $context['entry'];

        if ($entry !== null) {
            $status = (string) $entry->validation_status;
            $result = $entry->result_value !== null ? (float) $entry->result_value : null;
            $error = $entry->validation_error;

            $contexts[$code]['status'] = $status;
            $contexts[$code]['result'] = $result;
            $contexts[$code]['error'] = $error;

            if ($context['input_type'] === UnitCostParameterEntry::INPUT_FORMULA) {
                $memo[$code] = [
                    'status' => $status,
                    'result' => $result,
                    'error' => $error,
                ];
            }

            return;
        }

        $status = UnitCostParameterEntry::STATUS_INCOMPLETE;
        $result = null;
        $error = null;

        if ($context['input_type'] === UnitCostParameterEntry::INPUT_FORMULA) {
            $status = UnitCostParameterEntry::STATUS_INVALID;
            $error = 'Rumus belum diisi.';
        } elseif ($context['value_error'] !== null) {
            $status = UnitCostParameterEntry::STATUS_INVALID;
            $error = (string) $context['value_error'];
        } elseif ($context['input_value'] !== null) {
            $status = UnitCostParameterEntry::STATUS_VALID;
            $result = (float) $context['input_value'];
        } else {
            $error = $context['parameter']->is_required
                ? 'Nilai wajib diisi.'
                : 'Nilai belum diisi.';
        }

        $contexts[$code]['status'] = $status;
        $contexts[$code]['result'] = $result;
        $contexts[$code]['error'] = $error;

        if ($context['input_type'] === UnitCostParameterEntry::INPUT_FORMULA) {
            $memo[$code] = [
                'status' => $status,
                'result' => $result,
                'error' => $error,
            ];
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $contexts
     * @param  array<string, array{status: string, result: float|null, error: string|null}>  $memo
     * @param  array<string, bool>  $visiting
     * @param  array<string, string>  $cycleErrors
     * @return array{status: string, result: float|null, error: string|null}
     */
    private function evaluateNode(
        string $code,
        array &$contexts,
        array &$memo,
        array &$visiting,
        array $cycleErrors,
    ): array {
        if (array_key_exists($code, $memo)) {
            return $memo[$code];
        }

        if (array_key_exists($code, $cycleErrors)) {
            $contexts[$code]['status'] = UnitCostParameterEntry::STATUS_INVALID;
            $contexts[$code]['error'] = $cycleErrors[$code];
            $contexts[$code]['result'] = null;

            return $memo[$code] = [
                'status' => UnitCostParameterEntry::STATUS_INVALID,
                'result' => null,
                'error' => $cycleErrors[$code],
            ];
        }

        if (($visiting[$code] ?? false) === true) {
            $message = 'Siklus dependensi formula terdeteksi pada '.$code.'.';
            $contexts[$code]['status'] = UnitCostParameterEntry::STATUS_INVALID;
            $contexts[$code]['error'] = $message;
            $contexts[$code]['result'] = null;

            return $memo[$code] = [
                'status' => UnitCostParameterEntry::STATUS_INVALID,
                'result' => null,
                'error' => $message,
            ];
        }

        $visiting[$code] = true;
        $context = $contexts[$code];
        $status = UnitCostParameterEntry::STATUS_INCOMPLETE;
        $result = null;
        $error = null;

        if ($context['input_type'] === UnitCostParameterEntry::INPUT_FORMULA) {
            if ($context['parse_error'] !== null) {
                $status = UnitCostParameterEntry::STATUS_INVALID;
                $error = (string) $context['parse_error'];
            } elseif ($context['formula_text'] === '') {
                $status = UnitCostParameterEntry::STATUS_INVALID;
                $error = 'Rumus belum diisi.';
            } else {
                $values = [];
                $blocked = false;

                foreach ($context['dependencies'] as $dependency) {
                    if (! array_key_exists($dependency, $contexts)) {
                        $status = UnitCostParameterEntry::STATUS_INVALID;
                        $error = "Referensi parameter '{$dependency}' tidak ditemukan.";
                        $blocked = true;
                        break;
                    }

                    $dependencyResult = $this->evaluateNode($dependency, $contexts, $memo, $visiting, $cycleErrors);

                    if ($dependencyResult['status'] === UnitCostParameterEntry::STATUS_INVALID) {
                        $status = UnitCostParameterEntry::STATUS_INVALID;
                        $error = "Bergantung pada parameter invalid '{$dependency}': ".($dependencyResult['error'] ?? 'tidak diketahui');
                        $blocked = true;
                        break;
                    }

                    if ($dependencyResult['status'] === UnitCostParameterEntry::STATUS_INCOMPLETE) {
                        $status = UnitCostParameterEntry::STATUS_INCOMPLETE;
                        $error = "Input '{$dependency}' belum lengkap.";
                        $blocked = true;
                        break;
                    }

                    if ($dependencyResult['result'] === null) {
                        $status = UnitCostParameterEntry::STATUS_INCOMPLETE;
                        $error = "Nilai '{$dependency}' belum tersedia.";
                        $blocked = true;
                        break;
                    }

                    $values[$dependency] = $dependencyResult['result'];
                }

                if (! $blocked) {
                    try {
                        $result = $this->parser->evaluate((string) $context['formula_text'], $values);
                        $status = UnitCostParameterEntry::STATUS_VALID;
                    } catch (FormulaException $exception) {
                        $status = UnitCostParameterEntry::STATUS_INVALID;
                        $error = $exception->getMessage();
                    }
                }
            }
        } else {
            if ($context['value_error'] !== null) {
                $status = UnitCostParameterEntry::STATUS_INVALID;
                $error = (string) $context['value_error'];
            } elseif ($context['input_value'] !== null) {
                $status = UnitCostParameterEntry::STATUS_VALID;
                $result = (float) $context['input_value'];
            } else {
                $status = UnitCostParameterEntry::STATUS_INCOMPLETE;
                $error = $context['parameter']->is_required
                    ? 'Nilai wajib diisi.'
                    : 'Nilai belum diisi.';
            }
        }

        $contexts[$code]['status'] = $status;
        $contexts[$code]['result'] = $result;
        $contexts[$code]['error'] = $error;
        $visiting[$code] = false;

        return $memo[$code] = [
            'status' => $status,
            'result' => $result,
            'error' => $error,
        ];
    }

    /**
     * @param  array<string, array<int, string>>  $dependencies
     * @return array<string, string>
     */
    private function detectCycles(array $dependencies): array
    {
        $state = [];
        $path = [];
        $errors = [];

        $visit = function (string $code) use (&$visit, &$state, &$path, &$errors, $dependencies): void {
            $currentState = $state[$code] ?? 0;

            if ($currentState === 2) {
                return;
            }

            if ($currentState === 1) {
                $cycleStart = array_search($code, $path, true);
                $cycle = array_slice($path, $cycleStart === false ? 0 : $cycleStart);
                $cycle[] = $code;
                $message = 'Siklus dependensi formula terdeteksi: '.implode(' -> ', $cycle).'.';

                foreach (array_unique($cycle) as $cycleCode) {
                    $errors[$cycleCode] = $message;
                }

                return;
            }

            $state[$code] = 1;
            $path[] = $code;

            foreach ($dependencies[$code] ?? [] as $dependency) {
                $visit($dependency);
            }

            array_pop($path);
            $state[$code] = 2;
        };

        foreach (array_keys($dependencies) as $code) {
            $visit($code);
        }

        return $errors;
    }
}
