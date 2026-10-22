<?php

namespace App\Http\Controllers;

use App\Http\Requests\UnitCost\StoreUnitCostComponent;
use App\Http\Requests\UnitCost\StoreUnitCostParameter;
use App\Http\Requests\UnitCost\UpdateUnitCostComponent;
use App\Http\Requests\UnitCost\UpdateUnitCostParameter;
use App\Models\UnitCostComponent;
use App\Models\UnitCostFormulaVersion;
use App\Models\UnitCostParameter;
use App\Models\UnitCostPeriod;
use Illuminate\Http\Request;

class UnitCostMasterController extends Controller
{
    public function index(Request $request)
    {
        $section = (string) $request->input('section', 'components');

        if (! in_array($section, ['components', 'parameters', 'formula-history'], true)) {
            $section = 'components';
        }

        $perPage = (int) $request->input('per_page', 25);

        if (! in_array($perPage, [5, 10, 25, 50, 100], true)) {
            $perPage = 25;
        }

        $filters = [
            'search' => trim((string) $request->input('search', '')),
            'category' => trim((string) $request->input('category', '')),
            'status' => (string) $request->input('status', ''),
            'component' => (string) $request->input('component', ''),
            'scope' => (string) $request->input('scope', ''),
            'period' => (string) $request->input('period', ''),
            'date_from' => (string) $request->input('date_from', ''),
            'date_to' => (string) $request->input('date_to', ''),
        ];

        $components = null;
        $parameters = null;
        $formulaVersions = null;
        $categories = collect();
        $filterComponents = collect();
        $periodOptions = collect();

        if ($section === 'components') {
            $categories = UnitCostComponent::query()
                ->whereNotNull('category')
                ->where('category', '<>', '')
                ->distinct()
                ->orderBy('category')
                ->pluck('category');

            $components = UnitCostComponent::query()
                ->withCount([
                    'parameters' => fn ($query) => $query->active(),
                ])
                ->when($filters['search'] !== '', function ($query) use ($filters): void {
                    $term = '%'.$filters['search'].'%';
                    $query->where(function ($query) use ($term): void {
                        $query->where('code', 'like', $term)
                            ->orWhere('name', 'like', $term)
                            ->orWhere('description', 'like', $term);
                    });
                })
                ->when($filters['category'] !== '', fn ($query) => $query->where('category', $filters['category']))
                ->when($filters['status'] !== '', fn ($query) => $query->where('is_active', $filters['status'] === 'active'))
                ->orderBy('sort_order')
                ->orderBy('name')
                ->paginate($perPage)
                ->withQueryString();
        } elseif ($section === 'parameters') {
            $filterComponents = $this->componentsForForm();

            $parameters = UnitCostParameter::query()
                ->with('component')
                ->withCount(['entries', 'formulaVersions'])
                ->when($filters['search'] !== '', function ($query) use ($filters): void {
                    $term = '%'.$filters['search'].'%';
                    $query->where(function ($query) use ($term): void {
                        $query->where('code', 'like', $term)
                            ->orWhere('name', 'like', $term);
                    });
                })
                ->when($filters['component'] !== '', function ($query) use ($filters): void {
                    if ($filters['component'] === 'global') {
                        $query->whereNull('component_id');
                    } elseif (ctype_digit($filters['component'])) {
                        $query->where('component_id', (int) $filters['component']);
                    }
                })
                ->when($filters['scope'] !== '', fn ($query) => $query->where('scope', $filters['scope']))
                ->when($filters['status'] !== '', fn ($query) => $query->where('is_active', $filters['status'] === 'active'))
                ->orderBy('component_id')
                ->orderBy('sort_order')
                ->orderBy('code')
                ->paginate($perPage)
                ->withQueryString();
        } else {
            $periodOptions = UnitCostPeriod::query()
                ->whereIn('id', UnitCostFormulaVersion::query()->select('period_id')->whereNotNull('period_id'))
                ->orderByDesc('period_year')
                ->orderBy('name')
                ->get(['id', 'name', 'code', 'period_year']);

            $formulaVersions = UnitCostFormulaVersion::query()
                ->with(['parameter', 'period'])
                ->when($filters['search'] !== '', function ($query) use ($filters): void {
                    $term = '%'.$filters['search'].'%';
                    $query->where(function ($query) use ($term): void {
                        $query->where('expression', 'like', $term)
                            ->orWhereHas('parameter', function ($query) use ($term): void {
                                $query->where('name', 'like', $term)
                                    ->orWhere('code', 'like', $term);
                            });
                    });
                })
                ->when($filters['period'] !== '', function ($query) use ($filters): void {
                    if (ctype_digit($filters['period'])) {
                        $query->where('period_id', (int) $filters['period']);
                    }
                })
                ->when($filters['date_from'] !== '' && strtotime($filters['date_from']) !== false, fn ($query) => $query->whereDate('created_at', '>=', $filters['date_from']))
                ->when($filters['date_to'] !== '' && strtotime($filters['date_to']) !== false, fn ($query) => $query->whereDate('created_at', '<=', $filters['date_to']))
                ->latest('id')
                ->paginate($perPage)
                ->withQueryString();
        }

        return view('unit-cost.master', [
            'section' => $section,
            'perPage' => $perPage,
            'filters' => $filters,
            'components' => $components,
            'parameters' => $parameters,
            'formulaVersions' => $formulaVersions,
            'categories' => $categories,
            'filterComponents' => $filterComponents,
            'periodOptions' => $periodOptions,
        ]);
    }

    public function createComponent()
    {
        return view('unit-cost.master.component-form', [
            'componentModel' => new UnitCostComponent([
                'is_active' => true,
                'sort_order' => 0,
            ]),
        ]);
    }

    public function storeComponent(StoreUnitCostComponent $request)
    {
        $data = $request->validated();
        $data['is_active'] = $request->has('is_active') ? $request->boolean('is_active') : true;
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

        $component = UnitCostComponent::create($data);

        return redirect()
            ->route('calculations.master', ['section' => 'components'])
            ->with('success', "Komponen '{$component->name}' berhasil dibuat.");
    }

    public function editComponent(UnitCostComponent $component)
    {
        return view('unit-cost.master.component-form', [
            'componentModel' => $component,
        ]);
    }

    public function updateComponent(UpdateUnitCostComponent $request, UnitCostComponent $component)
    {
        $data = $request->validated();
        $data['is_active'] = $request->has('is_active')
            ? $request->boolean('is_active')
            : $component->is_active;
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

        $component->update($data);

        return redirect()
            ->route('calculations.master', ['section' => 'components'])
            ->with('success', "Komponen '{$component->name}' berhasil diperbarui.");
    }

    public function destroyComponent(UnitCostComponent $component)
    {
        if ($component->parameters()->exists()) {
            return redirect()
                ->route('calculations.master', ['section' => 'components'])
                ->with('error', "Komponen '{$component->name}' tidak dapat dihapus karena masih memiliki parameter.");
        }

        $name = $component->name;
        $component->delete();

        return redirect()
            ->route('calculations.master', ['section' => 'components'])
            ->with('success', "Komponen '{$name}' berhasil dihapus.");
    }

    public function createParameter(Request $request)
    {
        return view('unit-cost.master.parameter-form', [
            'parameter' => new UnitCostParameter([
                'component_id' => $request->filled('component_id') ? (int) $request->input('component_id') : null,
                'value_type' => UnitCostParameter::VALUE_DECIMAL,
                'scope' => UnitCostParameter::SCOPE_UNIT_PERIOD,
                'default_input_type' => 'value',
                'source_type' => UnitCostParameter::SOURCE_MANUAL,
                'is_required' => true,
                'is_active' => true,
                'sort_order' => 0,
            ]),
            'components' => $this->componentsForForm(),
            'returnPeriodId' => $request->filled('period_id') ? (int) $request->input('period_id') : null,
            'returnUnitId' => $request->filled('unit_id') ? (string) $request->input('unit_id') : null,
        ]);
    }

    public function storeParameter(StoreUnitCostParameter $request)
    {
        $data = $request->validated();
        $data['is_active'] = $request->has('is_active') ? $request->boolean('is_active') : true;
        $data['is_required'] = $request->has('is_required') ? $request->boolean('is_required') : false;
        $data['sort_order'] = $this->nextParameterSortOrder($data['component_id'] ?? null);

        $parameter = UnitCostParameter::create($data);
        $message = sprintf(
            "Parameter '%s' dengan kode variabel {%s} berhasil dibuat.",
            $parameter->name,
            $parameter->formulaKey(),
        );

        $period = $request->filled('period_id')
            ? UnitCostPeriod::query()->find((int) $request->input('period_id'))
            : null;

        if ($period !== null) {
            return redirect()
                ->route('calculations.parameters.edit', [
                    'period' => $period,
                    'component' => $parameter->component_id ?? 'global',
                    'unit_id' => $request->input('unit_id', '0'),
                ])
                ->with('success', $message);
        }

        return redirect()
            ->route('calculations.master', ['section' => 'parameters'])
            ->with('success', $message);
    }

    public function editParameter(UnitCostParameter $parameter)
    {
        return view('unit-cost.master.parameter-form', [
            'parameter' => $parameter,
            'components' => $this->componentsForForm(),
        ]);
    }

    public function updateParameter(UpdateUnitCostParameter $request, UnitCostParameter $parameter)
    {
        $data = $request->validated();
        $data['is_active'] = $request->has('is_active')
            ? $request->boolean('is_active')
            : $parameter->is_active;
        $data['is_required'] = $request->has('is_required')
            ? $request->boolean('is_required')
            : $parameter->is_required;

        $parameter->update($data);

        return redirect()
            ->route('calculations.master', ['section' => 'parameters'])
            ->with('success', "Parameter '{$parameter->name}' berhasil diperbarui.");
    }

    public function destroyParameter(UnitCostParameter $parameter)
    {
        if ($parameter->entries()->exists() || $parameter->formulaVersions()->exists()) {
            return redirect()
                ->route('calculations.master', ['section' => 'parameters'])
                ->with('error', "Parameter '{$parameter->name}' tidak dapat dihapus karena sudah memiliki nilai atau riwayat formula.");
        }

        $name = $parameter->name;
        $variable = $parameter->formulaKey();
        $parameter->delete();

        $message = sprintf("Parameter '%s' (kode variabel {%s}) berhasil dihapus.", $name, $variable);

        return redirect()
            ->route('calculations.master', ['section' => 'parameters'])
            ->with('success', $message);
    }

    private function nextParameterSortOrder(?int $componentId): int
    {
        $max = UnitCostParameter::query()
            ->when(
                $componentId === null,
                static fn ($query) => $query->whereNull('component_id'),
                static fn ($query) => $query->where('component_id', $componentId),
            )
            ->max('sort_order');

        return ((int) $max) + 10;
    }

    /**
     * @return \Illuminate\Support\Collection<int, UnitCostComponent>
     */
    private function componentsForForm()
    {
        return UnitCostComponent::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'is_active']);
    }
}
