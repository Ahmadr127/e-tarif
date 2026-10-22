<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesUnitCostScope;
use App\Http\Requests\UnitCost\SaveUnitCostParameters;
use App\Models\OrganizationUnit;
use App\Models\UnitCostComponent;
use App\Models\UnitCostParameterEntry;
use App\Models\UnitCostPeriod;
use App\Services\UnitCost\ParameterEvaluationService;
use Illuminate\Http\Request;

class UnitCostParameterController extends Controller
{
    use ResolvesUnitCostScope;

    public function __construct(protected ParameterEvaluationService $service) {}

    public function edit(Request $request, UnitCostPeriod $period, string $component)
    {
        $componentId = $this->resolveComponentId($component);
        $this->assertComponentAccessible($request, $componentId);

        $units = OrganizationUnit::query()->active()->orderBy('name')->get(['id', 'name', 'code']);
        $unit = $this->resolveUnit($request, $units);

        $parameters = $this->service->parametersForComponent($componentId);
        $evaluation = $this->service->evaluate($period, $unit);
        $rows = $evaluation->forComponent($componentId);
        $latestVersions = $this->service->latestFormulaVersions($period, $parameters->pluck('id')->all());

        return view('unit-cost.parameters.edit', [
            'period' => $period,
            'component' => $componentId,
            'componentModel' => $componentId === null
                ? null
                : UnitCostComponent::query()->find($componentId),
            'componentSlug' => $component,
            'parameters' => $parameters,
            'rows' => $rows,
            'latestVersions' => $latestVersions,
            'allParameters' => $this->service->allActiveParameters(),
            'units' => $units,
            'unit' => $unit,
            'summary' => $evaluation->summary,
            'isAdmin' => $this->isUnitCostAdmin($request),
            'isScopedUser' => $this->isScopedUnitCostUser($request),
        ]);
    }

    public function update(SaveUnitCostParameters $request, UnitCostPeriod $period, string $component)
    {
        $componentId = $this->resolveComponentId($component);
        $this->assertComponentAccessible($request, $componentId);

        $unit = $this->resolveUnit($request);

        $entries = $request->input('entries', []);
        $entries = is_array($entries) ? $entries : [];

        if ($this->isScopedUnitCostUser($request)) {
            $entries = $this->sanitizeScopedEntries($period, $unit, $componentId, $entries);
        }

        $result = $this->service->save(
            $period,
            $unit,
            $componentId,
            $entries,
            auth()->id(),
        );

        $componentRows = $result->forComponent($componentId);
        $valid = count(array_filter($componentRows, static fn (array $row): bool => $row['status'] === 'valid'));

        return redirect()
            ->route('calculations.parameters.edit', [
                'period' => $period,
                'component' => $component,
                'unit_id' => $unit?->id ?? '0',
            ])
            ->with('success', "Parameter berhasil disimpan. {$valid} dari ".count($componentRows).' parameter valid.');
    }

    public function preview(Request $request)
    {
        $data = $request->validate([
            'period_id' => ['required', 'integer', 'exists:unit_cost_periods,id'],
            'unit_id' => ['nullable', 'integer', 'exists:organization_units,id'],
            'component' => ['nullable', 'string'],
            'entries' => ['nullable', 'array'],
            'entries.*.input_type' => ['nullable', 'in:value,formula'],
            'entries.*.input_value' => ['nullable'],
            'entries.*.formula_text' => ['nullable', 'string', 'max:2000'],
        ]);

        $period = UnitCostPeriod::query()->findOrFail((int) $data['period_id']);
        $unit = isset($data['unit_id']) && $data['unit_id'] !== null
            ? OrganizationUnit::query()->find((int) $data['unit_id'])
            : null;
        $componentId = $this->resolveComponentId((string) ($data['component'] ?? 'global'));
        $this->assertComponentAccessible($request, $componentId);

        $entries = $data['entries'] ?? [];

        if ($this->isScopedUnitCostUser($request)) {
            $unit = $this->resolveOwnUnit($request);
            $entries = $this->sanitizeScopedEntries($period, $unit, $componentId, $entries);
        }

        $result = $this->service->evaluate($period, $unit, $entries);
        $rows = [];
        $componentSummary = [
            'total' => 0,
            'valid' => 0,
            'incomplete' => 0,
            'invalid' => 0,
        ];

        foreach ($result->forComponent($componentId) as $row) {
            $componentSummary['total']++;
            $componentSummary[$row['status']] = ($componentSummary[$row['status']] ?? 0) + 1;

            $rows[(string) $row['parameter']->id] = [
                'parameter_id' => $row['parameter']->id,
                'status' => $row['status'],
                'result_value' => $row['result_value'],
                'error' => $row['error'],
                'formula_version' => $row['formula_version'],
                'source' => $row['source'],
            ];
        }

        return response()->json([
            'valid' => $componentSummary['invalid'] === 0 && $componentSummary['incomplete'] === 0,
            'summary' => $result->summary,
            'component_summary' => $componentSummary,
            'rows' => $rows,
            'values' => $result->values,
        ]);
    }

    private function resolveComponentId(string $component): ?int
    {
        if ($component === 'global' || $component === '') {
            return null;
        }

        if (! ctype_digit($component)) {
            abort(404);
        }

        $id = (int) $component;

        if (! UnitCostComponent::query()->whereKey($id)->exists()) {
            abort(404);
        }

        return $id;
    }

    /**
     * Operator unit hanya boleh mengirim nilai untuk parameter berscope unit
     * yang efektif memakai input nilai. Parameter global dan formula tetap
     * memakai definisi admin.
     *
     * @param  array<int|string, mixed>  $entries
     * @return array<int|string, array<string, mixed>>
     */
    private function sanitizeScopedEntries(
        UnitCostPeriod $period,
        ?OrganizationUnit $unit,
        ?int $componentId,
        array $entries,
    ): array {
        $rows = $this->service->evaluate($period, $unit)->forComponent($componentId);
        $allowed = [];

        foreach ($rows as $row) {
            if ($row['input_type'] === UnitCostParameterEntry::INPUT_VALUE
                && $row['parameter']->requiresUnit()) {
                $allowed[(string) $row['parameter']->id] = true;
            }
        }

        $clean = [];

        foreach ($entries as $parameterId => $entry) {
            if (! isset($allowed[(string) $parameterId]) || ! is_array($entry)) {
                continue;
            }

            $clean[$parameterId] = [
                'input_type' => UnitCostParameterEntry::INPUT_VALUE,
                'input_value' => $entry['input_value'] ?? null,
            ];
        }

        return $clean;
    }
}
