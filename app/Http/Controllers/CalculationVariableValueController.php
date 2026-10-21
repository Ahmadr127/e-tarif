<?php

namespace App\Http\Controllers;

use App\Http\Requests\Calculation\SaveVariableValues;
use App\Http\Services\Calculation\CalculationException;
use App\Http\Services\Calculation\VariableResolver;
use App\Models\CalculationVariable;
use App\Models\CalculationVariableValue;
use App\Models\OrganizationUnit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CalculationVariableValueController extends Controller
{
    public function __construct(protected VariableResolver $variableResolver) {}

    public function index(Request $request)
    {
        $periodYear = (int) old('period_year', $request->integer('period_year') ?: now()->year);
        $unitCode = mb_strtoupper(trim((string) old('unit_code', $request->string('unit_code')->toString())));
        $unit = $unitCode !== ''
            ? OrganizationUnit::query()->where('code', $unitCode)->first()
            : null;

        $variables = CalculationVariable::query()
            ->active()
            ->with('component')
            ->get()
            ->sortBy(static fn (CalculationVariable $variable): string => sprintf(
                '%05d-%s',
                $variable->component?->sort_order ?? 9999,
                $variable->code
            ))
            ->values();

        $values = CalculationVariableValue::valuesForContext($variables, $unit?->id, $periodYear);

        $groups = $variables->groupBy(
            static fn (CalculationVariable $variable): string => $variable->component?->name ?? 'Parameter Global'
        );

        return view('calculations.values.index', [
            'groups' => $groups,
            'values' => $values,
            'units' => OrganizationUnit::query()->active()->orderBy('name')->get(['id', 'name', 'code']),
            'unit' => $unit,
            'unitCode' => $unitCode,
            'periodYear' => $periodYear,
        ]);
    }

    public function store(SaveVariableValues $request)
    {
        $data = $request->validated();
        $unitCode = $data['unit_code'] ?? null;
        $periodYear = (int) $data['period_year'];
        $unit = $unitCode
            ? OrganizationUnit::query()->where('code', $unitCode)->first()
            : null;

        $variables = CalculationVariable::query()
            ->active()
            ->get()
            ->keyBy('code');

        $submitted = $data['values'] ?? [];
        $notes = $data['notes'] ?? [];

        try {
            DB::transaction(function () use ($submitted, $notes, $variables, $unit, $periodYear): void {
                foreach ($submitted as $code => $rawValue) {
                    $variable = $variables->get((string) $code);

                    if ($variable === null) {
                        throw ValidationException::withMessages([
                            "values.{$code}" => "Variabel '{$code}' tidak ditemukan atau tidak aktif.",
                        ]);
                    }

                    if ($variable->isDerived()) {
                        throw ValidationException::withMessages([
                            "values.{$code}" => "Variabel turunan '{$code}' dihitung otomatis oleh formula.",
                        ]);
                    }

                    $scopeKey = CalculationVariableValue::scopeKeyFor($variable, $unit?->id, $periodYear);
                    $trimmed = is_string($rawValue) ? trim($rawValue) : $rawValue;

                    if ($trimmed === '' || $trimmed === null) {
                        CalculationVariableValue::query()->where('scope_key', $scopeKey)->delete();

                        continue;
                    }

                    if ($variable->requiresUnit() && $unit === null) {
                        throw ValidationException::withMessages([
                            'unit_code' => "Unit organisasi wajib dipilih untuk variabel '{$variable->name}'.",
                        ]);
                    }

                    if ($variable->requiresPeriod() && $periodYear <= 0) {
                        throw ValidationException::withMessages([
                            'period_year' => "Tahun periode wajib diisi untuk variabel '{$variable->name}'.",
                        ]);
                    }

                    try {
                        $value = $this->variableResolver->normalize($variable, $trimmed);
                    } catch (CalculationException $exception) {
                        throw ValidationException::withMessages([
                            "values.{$code}" => $exception->getMessage(),
                        ]);
                    }

                    CalculationVariableValue::query()->updateOrCreate(
                        ['scope_key' => $scopeKey],
                        [
                            'calculation_variable_id' => $variable->id,
                            'organization_unit_id' => $variable->requiresUnit() ? $unit?->id : null,
                            'period_year' => $variable->requiresPeriod() ? $periodYear : null,
                            'value' => $value,
                            'source_name' => $variable->source_name ?: 'Input manual',
                            'notes' => $notes[$code] ?? null,
                        ]
                    );
                }
            });
        } catch (ValidationException $exception) {
            throw $exception;
        }

        return redirect()
            ->route('calculations.values.index', array_filter([
                'unit_code' => $unitCode,
                'period_year' => $periodYear,
            ]))
            ->with('success', 'Nilai variabel berhasil disimpan untuk konteks yang dipilih.');
    }
}
