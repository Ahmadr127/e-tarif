<?php

namespace App\Http\Controllers;

use App\Http\Requests\Calculation\Store as StoreCalculationRequest;
use App\Http\Services\Calculation\CalculationException;
use App\Http\Services\Calculation\CalculationService;
use App\Models\CalculationFormula;
use App\Models\CalculationRun;
use App\Models\CalculationVariable;
use App\Models\CalculationVariableValue;
use App\Models\CostComponent;
use App\Models\OrganizationUnit;
use Illuminate\Http\Request;

class CalculationController extends Controller
{
    public function __construct(protected CalculationService $calculationService) {}

    public function index(Request $request)
    {
        $filters = $request->only(['search', 'unit_code', 'period_year', 'status', 'per_page']);

        $query = CalculationRun::query()->with(['organizationUnit', 'creator']);

        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $like = '%'.addcslashes(mb_strtolower($search), '%_\\').'%';

            $query->where(function ($query) use ($like): void {
                $query->whereRaw('LOWER(run_code) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(unit_code) LIKE ?', [$like]);
            });
        }

        if (! empty($filters['unit_code'])) {
            $query->where('unit_code', mb_strtoupper(trim((string) $filters['unit_code'])));
        }

        if (! empty($filters['period_year'])) {
            $query->where('period_year', (int) $filters['period_year']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $perPage = in_array((int) ($filters['per_page'] ?? 10), [10, 25, 50, 100], true)
            ? (int) ($filters['per_page'] ?? 10)
            : 10;

        $runs = $query->latest('id')->paginate($perPage)->withQueryString();

        $summary = CalculationRun::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('calculations.index', [
            'runs' => $runs,
            'filters' => $filters,
            'units' => OrganizationUnit::query()->orderBy('name')->get(['id', 'name', 'code']),
            'summary' => $summary,
        ]);
    }

    public function create(Request $request)
    {
        $periodYear = (int) old('period_year', $request->integer('period_year') ?: now()->year);
        $requiredCodes = $this->calculationService->requiredVariableCodes($periodYear);
        $unitCode = mb_strtoupper(trim((string) old('unit_code', $request->string('unit_code')->toString())));
        $unit = $unitCode !== ''
            ? OrganizationUnit::query()->where('code', $unitCode)->first()
            : null;

        $variables = CalculationVariable::query()->active()->get();
        $persistedValues = CalculationVariableValue::valuesForContext($variables, $unit?->id, $periodYear);

        return view('calculations.create', [
            'units' => OrganizationUnit::query()->active()->orderBy('name')->get(['id', 'name', 'code']),
            'groups' => $this->variableGroups(),
            'derivedVariables' => $variables
                ->filter(static fn (CalculationVariable $variable): bool => $variable->isDerived())
                ->sortBy('code')
                ->values(),
            'requiredCodes' => $requiredCodes,
            'periodYear' => $periodYear,
            'unitCode' => $unitCode,
            'persistedValues' => $persistedValues,
            'activeFormulaCount' => $this->calculationService->activeFormulas($periodYear)->count(),
        ]);
    }

    public function store(StoreCalculationRequest $request)
    {
        try {
            $run = $this->calculationService->calculateAndStore(
                unitCode: $request->string('unit_code')->toString(),
                periodYear: (int) $request->input('period_year'),
                inputs: $request->input('inputs', []),
                createdBy: auth()->id(),
                notes: $request->input('notes'),
            );
        } catch (CalculationException $exception) {
            $message = $exception->getMessage();
            $runId = $exception->context['run_id'] ?? null;

            if ($runId !== null) {
                return redirect()
                    ->route('calculations.show', $runId)
                    ->with('error', $message);
            }

            return redirect()
                ->back()
                ->withInput()
                ->with('error', $message);
        }

        return redirect()
            ->route('calculations.show', $run)
            ->with('success', 'Kalkulasi berhasil dihitung dan snapshot hasil sudah disimpan.');
    }

    public function show(CalculationRun $run)
    {
        $run->load([
            'organizationUnit',
            'creator',
            'details.formula.component',
        ]);

        $inputDetails = $run->details
            ->filter(static fn ($detail): bool => $detail->formula_id === null)
            ->values();

        $formulaDetails = $run->details
            ->filter(static fn ($detail): bool => $detail->formula_id !== null)
            ->values();

        $formulaGroups = $formulaDetails->groupBy(
            static fn ($detail): string => $detail->formula?->component?->name ?? 'Lainnya'
        );

        return view('calculations.show', [
            'run' => $run,
            'inputDetails' => $inputDetails,
            'formulaGroups' => $formulaGroups,
        ]);
    }

    public function approve(CalculationRun $run)
    {
        try {
            $this->calculationService->approve(
                $run,
                'Disetujui oleh '.auth()->user()->name.'.'
            );
        } catch (CalculationException $exception) {
            return redirect()
                ->route('calculations.show', $run)
                ->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('calculations.show', $run)
            ->with('success', 'Hasil kalkulasi berhasil disetujui.');
    }

    public function master()
    {
        $components = CostComponent::query()
            ->with([
                'variables' => fn ($query) => $query->orderBy('code'),
                'formulas' => fn ($query) => $query->orderBy('code')->orderByDesc('version'),
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('calculations.master', [
            'components' => $components,
            'globalVariables' => CalculationVariable::query()
                ->whereNull('component_id')
                ->orderBy('code')
                ->get(),
            'activeFormulaCount' => CalculationFormula::query()->active()->count(),
        ]);
    }

    /**
     * @return array<int, array{name: string, variables: \Illuminate\Support\Collection<int, CalculationVariable>}>
     */
    private function variableGroups(): array
    {
        $components = CostComponent::query()
            ->active()
            ->with([
                'variables' => fn ($query) => $query
                    ->active()
                    ->where('source_type', '!=', CalculationVariable::SOURCE_DERIVED)
                    ->orderBy('code'),
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $groups = [];

        foreach ($components as $component) {
            if ($component->variables->isEmpty()) {
                continue;
            }

            $groups[] = [
                'name' => $component->name,
                'variables' => $component->variables,
            ];
        }

        $globalVariables = CalculationVariable::query()
            ->active()
            ->whereNull('component_id')
            ->where('source_type', '!=', CalculationVariable::SOURCE_DERIVED)
            ->orderBy('code')
            ->get();

        if ($globalVariables->isNotEmpty()) {
            array_unshift($groups, [
                'name' => 'Parameter Global',
                'variables' => $globalVariables,
            ]);
        }

        return $groups;
    }
}
