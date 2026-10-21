<?php

namespace App\Http\Controllers;

use App\Http\Requests\Calculation\SaveVariable;
use App\Models\CalculationVariable;
use App\Models\CostComponent;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CalculationVariableController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->only(['search', 'source_type', 'scope', 'is_active']);

        $query = CalculationVariable::query()->with('component');

        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $like = '%'.addcslashes(mb_strtolower($search), '%_\\').'%';

            $query->where(function ($query) use ($like): void {
                $query->whereRaw('LOWER(code) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(name) LIKE ?', [$like]);
            });
        }

        if (! empty($filters['source_type'])) {
            $query->where('source_type', $filters['source_type']);
        }

        if (! empty($filters['scope'])) {
            $query->where('scope', $filters['scope']);
        }

        if ($filters['is_active'] !== null && $filters['is_active'] !== '') {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        return view('calculations.variables.index', [
            'variables' => $query->orderBy('code')->paginate(25)->withQueryString(),
            'filters' => $filters,
            'components' => CostComponent::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create()
    {
        return view('calculations.variables.create', [
            'variable' => new CalculationVariable([
                'value_type' => 'decimal',
                'scope' => CalculationVariable::SCOPE_GLOBAL,
                'source_type' => CalculationVariable::SOURCE_MANUAL,
                'is_required' => true,
                'is_active' => true,
            ]),
            'components' => CostComponent::query()->active()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(SaveVariable $request)
    {
        $data = $request->validated();
        $this->guardDerivedDefault($data);

        CalculationVariable::create([
            'code' => $data['code'],
            'name' => $data['name'],
            'component_id' => $data['component_id'] ?? null,
            'value_type' => $data['value_type'],
            'default_value' => $data['default_value'] ?? null,
            'unit' => $data['unit'] ?? null,
            'scope' => $data['scope'],
            'source_type' => $data['source_type'],
            'source_name' => $data['source_name'] ?? null,
            'description' => $data['description'] ?? null,
            'is_required' => (bool) ($data['is_required'] ?? false),
            'is_active' => (bool) ($data['is_active'] ?? false),
        ]);

        return redirect()
            ->route('calculations.variables.index')
            ->with('success', 'Global Variable berhasil ditambahkan.');
    }

    public function edit(CalculationVariable $variable)
    {
        return view('calculations.variables.edit', [
            'variable' => $variable,
            'components' => CostComponent::query()->active()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(SaveVariable $request, CalculationVariable $variable)
    {
        $data = $request->validated();
        $this->guardDerivedDefault($data);

        $variable->update([
            // Kode tidak diubah agar referensi formula tetap stabil.
            'name' => $data['name'],
            'component_id' => $data['component_id'] ?? null,
            'value_type' => $data['value_type'],
            'default_value' => $data['default_value'] ?? null,
            'unit' => $data['unit'] ?? null,
            'scope' => $data['scope'],
            'source_type' => $data['source_type'],
            'source_name' => $data['source_name'] ?? null,
            'description' => $data['description'] ?? null,
            'is_required' => (bool) ($data['is_required'] ?? false),
            'is_active' => (bool) ($data['is_active'] ?? false),
        ]);

        return redirect()
            ->route('calculations.variables.index')
            ->with('success', 'Global Variable berhasil diperbarui.');
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    private function guardDerivedDefault(array $data): void
    {
        if (($data['source_type'] ?? null) === CalculationVariable::SOURCE_DERIVED
            && isset($data['default_value'])
            && $data['default_value'] !== null) {
            throw ValidationException::withMessages([
                'default_value' => 'Variabel turunan tidak boleh memiliki nilai default karena nilainya berasal dari formula.',
            ]);
        }
    }
}
