<?php

namespace App\Http\Controllers;

use App\Http\Requests\UnitCost\StoreUnitCostComponent;
use App\Http\Requests\UnitCost\StoreUnitCostParameter;
use App\Http\Requests\UnitCost\UpdateUnitCostComponent;
use App\Http\Requests\UnitCost\UpdateUnitCostParameter;
use App\Models\UnitCostComponent;
use App\Models\UnitCostFormulaVersion;
use App\Models\UnitCostParameter;
use Illuminate\Http\Request;

class UnitCostMasterController extends Controller
{
    public function index()
    {
        $components = UnitCostComponent::query()
            ->withCount('parameters')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $parameters = UnitCostParameter::query()
            ->with('component')
            ->withCount(['entries', 'formulaVersions'])
            ->orderBy('component_id')
            ->orderBy('sort_order')
            ->orderBy('variable')
            ->get();

        $globalVariables = $parameters
            ->where('is_active', true)
            ->sortBy('variable')
            ->values();

        $formulaVersions = UnitCostFormulaVersion::query()
            ->with(['parameter', 'period'])
            ->latest('id')
            ->paginate(50);

        return view('unit-cost.master', [
            'components' => $components,
            'parameters' => $parameters,
            'globalVariables' => $globalVariables,
            'formulaVersions' => $formulaVersions,
        ]);
    }

    public function createComponent()
    {
        return view('unit-cost.master.component-form', [
            'component' => new UnitCostComponent([
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
            ->route('calculations.master')
            ->with('success', "Komponen '{$component->name}' berhasil dibuat.");
    }

    public function editComponent(UnitCostComponent $component)
    {
        return view('unit-cost.master.component-form', [
            'component' => $component,
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
            ->route('calculations.master')
            ->with('success', "Komponen '{$component->name}' berhasil diperbarui.");
    }

    public function destroyComponent(UnitCostComponent $component)
    {
        if ($component->parameters()->exists()) {
            return redirect()
                ->route('calculations.master')
                ->with('error', "Komponen '{$component->name}' tidak dapat dihapus karena masih memiliki parameter.");
        }

        $name = $component->name;
        $component->delete();

        return redirect()
            ->route('calculations.master')
            ->with('success', "Komponen '{$name}' berhasil dihapus.");
    }

    public function createParameter(Request $request)
    {
        return view('unit-cost.master.parameter-form', [
            'parameter' => new UnitCostParameter([
                'component_id' => $request->filled('component_id') ? (int) $request->input('component_id') : null,
                'value_type' => UnitCostParameter::VALUE_DECIMAL,
                'scope' => UnitCostParameter::SCOPE_UNIT_PERIOD,
                'source_type' => UnitCostParameter::SOURCE_MANUAL,
                'is_required' => true,
                'is_active' => true,
                'sort_order' => 0,
            ]),
            'components' => $this->componentsForForm(),
        ]);
    }

    public function storeParameter(StoreUnitCostParameter $request)
    {
        $data = $request->validated();
        $data['is_active'] = $request->has('is_active') ? $request->boolean('is_active') : true;
        $data['is_required'] = $request->has('is_required') ? $request->boolean('is_required') : false;
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

        $parameter = UnitCostParameter::create($data);

        return redirect()
            ->route('calculations.master')
            ->with('success', "Parameter '{$parameter->name}' dengan variable {".$parameter->formulaKey().'} berhasil dibuat.');
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
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

        $parameter->update($data);

        return redirect()
            ->route('calculations.master')
            ->with('success', "Parameter '{$parameter->name}' berhasil diperbarui.");
    }

    public function destroyParameter(UnitCostParameter $parameter)
    {
        if ($parameter->entries()->exists() || $parameter->formulaVersions()->exists()) {
            return redirect()
                ->route('calculations.master')
                ->with('error', "Parameter '{$parameter->name}' tidak dapat dihapus karena sudah memiliki nilai atau riwayat formula.");
        }

        $name = $parameter->name;
        $variable = $parameter->formulaKey();
        $parameter->delete();

        return redirect()
            ->route('calculations.master')
            ->with('success', "Parameter '{$name}' (variable {".$variable.'}) berhasil dihapus.');
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
