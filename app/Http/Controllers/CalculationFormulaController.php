<?php

namespace App\Http\Controllers;

use App\Http\Requests\Calculation\SaveFormula;
use App\Http\Services\Calculation\CalculationException;
use App\Http\Services\Calculation\FormulaEngine;
use App\Http\Services\Calculation\FormulaManagementService;
use App\Models\CalculationFormula;
use App\Models\CalculationVariable;
use App\Models\CostComponent;
use App\Models\OrganizationUnit;
use Illuminate\Http\Request;

class CalculationFormulaController extends Controller
{
    public function __construct(
        protected FormulaEngine $engine,
        protected FormulaManagementService $formulaService,
    ) {}

    public function index()
    {
        $formulas = CalculationFormula::query()
            ->with('component')
            ->orderBy('code')
            ->orderByDesc('version')
            ->get();

        return view('calculations.formulas.index', [
            'formulas' => $formulas,
            'latestByCode' => $formulas->groupBy('code')->map->first(),
        ]);
    }

    public function create()
    {
        return view('calculations.formulas.create', [
            'formula' => new CalculationFormula([
                'result_scope' => 'unit_period',
                'version' => 1,
                'is_active' => true,
            ]),
            'components' => CostComponent::query()->active()->orderBy('name')->get(['id', 'name']),
            'variables' => CalculationVariable::query()->active()->orderBy('code')->get(),
            'units' => OrganizationUnit::query()->active()->orderBy('name')->get(['id', 'name', 'code']),
            'defaultYear' => (int) now()->year,
        ]);
    }

    public function store(SaveFormula $request)
    {
        try {
            $formula = $this->formulaService->save($request->validated());
        } catch (CalculationException $exception) {
            return redirect()
                ->back()
                ->withInput()
                ->withErrors(['expression' => $exception->getMessage()]);
        }

        return redirect()
            ->route('calculations.formulas.index')
            ->with('success', "Formula '{$formula->code}' berhasil disimpan sebagai versi {$formula->version}.");
    }

    public function edit(CalculationFormula $formula)
    {
        return view('calculations.formulas.edit', [
            'formula' => $formula,
            'components' => CostComponent::query()->active()->orderBy('name')->get(['id', 'name']),
            'variables' => CalculationVariable::query()->active()->orderBy('code')->get(),
            'units' => OrganizationUnit::query()->active()->orderBy('name')->get(['id', 'name', 'code']),
            'defaultYear' => $formula->effective_from?->year ?? (int) now()->year,
        ]);
    }

    public function update(SaveFormula $request, CalculationFormula $formula)
    {
        try {
            $saved = $this->formulaService->save($request->validated(), $formula);
        } catch (CalculationException $exception) {
            return redirect()
                ->back()
                ->withInput()
                ->withErrors(['expression' => $exception->getMessage()]);
        }

        return redirect()
            ->route('calculations.formulas.index')
            ->with('success', "Formula '{$saved->code}' disimpan sebagai versi baru {$saved->version}.");
    }

    public function validateFormula(Request $request)
    {
        $data = $request->validate([
            'expression' => ['required', 'string', 'max:2000'],
            'code' => ['nullable', 'string', 'max:120', 'regex:/^[A-Za-z_][A-Za-z0-9_]*$/'],
            'period_year' => ['nullable', 'integer', 'between:2000,2100'],
            'component_id' => ['nullable', 'integer', 'exists:cost_components,id'],
        ]);

        try {
            $result = $this->engine->validateFormula(
                (string) $data['expression'],
                $data['code'] ?? null,
                isset($data['period_year']) ? (int) $data['period_year'] : null,
                isset($data['component_id']) ? (int) $data['component_id'] : null,
            );
        } catch (CalculationException $exception) {
            return response()->json([
                'valid' => false,
                'message' => $exception->getMessage(),
                'errors' => $exception->context['errors'] ?? [],
            ], 422);
        }

        return response()->json($result);
    }

    public function preview(Request $request)
    {
        $data = $request->validate([
            'expression' => ['required', 'string', 'max:2000'],
            'code' => ['nullable', 'string', 'max:120', 'regex:/^[A-Za-z_][A-Za-z0-9_]*$/'],
            'component_id' => ['nullable', 'integer', 'exists:cost_components,id'],
            'result_unit' => ['nullable', 'string', 'max:50'],
            'unit_code' => ['required', 'string', 'max:80', 'exists:organization_units,code'],
            'period_year' => ['required', 'integer', 'between:2000,2100'],
        ]);

        $targetCode = trim((string) ($data['code'] ?? '')) ?: '__preview__';
        $unitCode = mb_strtoupper(trim((string) $data['unit_code']));

        try {
            $result = $this->engine->previewFormula(
                unitCode: $unitCode,
                periodYear: (int) $data['period_year'],
                expression: (string) $data['expression'],
                code: $data['code'] ?? null,
                componentId: isset($data['component_id']) ? (int) $data['component_id'] : null,
                resultUnit: $data['result_unit'] ?? null,
            );

            $references = $this->engine->references((string) $data['expression']);
            $detailsByCode = collect($result->details)->keyBy('code');
            $referenceValues = [];

            foreach ($references as $reference) {
                $detail = $detailsByCode->get($reference);
                $referenceValues[$reference] = [
                    'value' => $result->values[$reference] ?? null,
                    'unit' => $detail['result_unit'] ?? null,
                    'type' => $detail['detail_type'] ?? null,
                    'name' => $detail['name'] ?? $reference,
                ];
            }
        } catch (CalculationException $exception) {
            return response()->json([
                'valid' => false,
                'message' => $exception->getMessage(),
                'errors' => $exception->context['errors'] ?? [],
            ], 422);
        }

        return response()->json([
            'valid' => true,
            'target_code' => $targetCode,
            'target_value' => $result->values[$targetCode] ?? null,
            'target_unit' => $data['result_unit'] ?? null,
            'formula_version' => $result->formulaVersion,
            'references' => $references,
            'reference_values' => $referenceValues,
            'ordered_formula_codes' => $result->formulaCodes(),
        ]);
    }
}
