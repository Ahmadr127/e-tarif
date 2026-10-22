<?php

namespace App\Http\Controllers;

use App\Models\OrganizationUnit;
use App\Models\UnitCostComponent;
use App\Models\UnitCostPeriod;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;

class UnitCostUnitController extends Controller
{
    public function __construct(protected ActivityLogService $activityLogger) {}

    /**
     * Daftar unit aktif beserta komponen unit cost yang di-assign.
     */
    public function index(Request $request)
    {
        $filters = $request->only(['search', 'per_page', 'period_id']);

        $query = OrganizationUnit::query()
            ->active()
            ->with([
                'type',
                'unitCostComponents' => fn ($relation) => $relation
                    ->where('unit_cost_components.is_active', true)
                    ->orderBy('unit_cost_components.sort_order')
                    ->orderBy('unit_cost_components.name'),
            ])
            ->withCount([
                'unitCostComponents as active_components_count' => fn ($relation) => $relation
                    ->where('unit_cost_components.is_active', true),
            ]);

        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $like = '%'.addcslashes(mb_strtolower($search), '%_\\').'%';

            $query->where(function ($query) use ($like): void {
                $query->whereRaw('LOWER(name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(code) LIKE ?', [$like]);
            });
        }

        $perPage = in_array((int) ($filters['per_page'] ?? 25), [10, 25, 50, 100], true)
            ? (int) ($filters['per_page'] ?? 25)
            : 25;

        $units = $query
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();

        $components = UnitCostComponent::query()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        $periods = UnitCostPeriod::query()
            ->orderByDesc('period_year')
            ->orderByDesc('id')
            ->get(['id', 'name', 'period_year', 'status']);

        $currentPeriod = $periods->firstWhere('status', UnitCostPeriod::STATUS_ACTIVE)
            ?? $periods->first();

        $selectedPeriod = null;

        if (! empty($filters['period_id'])) {
            $selectedPeriod = $periods->firstWhere('id', (int) $filters['period_id']);
        }

        $selectedPeriod ??= $currentPeriod;

        return view('unit-cost.units.index', [
            'units' => $units,
            'components' => $components,
            'periods' => $periods,
            'selectedPeriod' => $selectedPeriod,
            'filters' => $filters,
            'perPage' => $perPage,
        ]);
    }

    /**
     * Pintasan operator unit: langsung buka tabel komponen unit sendiri pada
     * periode aktif (fallback ke periode terbaru).
     */
    public function myUnit(Request $request)
    {
        $unit = $request->user()?->organizationUnit;

        abort_if($unit === null, 403, 'Akun Anda belum terhubung ke unit organisasi.');

        $period = UnitCostPeriod::query()
            ->where('status', UnitCostPeriod::STATUS_ACTIVE)
            ->orderByDesc('period_year')
            ->orderByDesc('id')
            ->first()
            ?? UnitCostPeriod::query()
                ->orderByDesc('period_year')
                ->orderByDesc('id')
                ->first();

        if ($period === null) {
            return redirect()
                ->route('dashboard')
                ->with('error', 'Belum ada periode Unit Cost yang dapat dibuka.');
        }

        return redirect()->route('calculations.periods.show', [
            'period' => $period,
            'unit_id' => $unit->id,
        ]);
    }

    /**
     * Simpan komponen yang di-assign ke sebuah unit (global semua periode).
     */
    public function updateComponents(Request $request, OrganizationUnit $unit)
    {
        $validated = $request->validate([
            'component_ids' => ['nullable', 'array'],
            'component_ids.*' => ['integer', 'exists:unit_cost_components,id'],
        ]);

        $componentIds = array_map('intval', $validated['component_ids'] ?? []);
        $changes = $unit->unitCostComponents()->sync($componentIds);

        // Perubahan pivot tidak memicu event model, jadi dicatat eksplisit.
        $this->activityLogger->log(
            $unit,
            'components_assigned',
            [
                'component_ids' => $componentIds,
                'attached' => array_values($changes['attached'] ?? []),
                'detached' => array_values($changes['detached'] ?? []),
                'updated' => array_values($changes['updated'] ?? []),
            ],
            "Komponen Unit Cost unit '{$unit->name}' diperbarui.",
        );

        return redirect()
            ->back()
            ->with('success', "Komponen unit '{$unit->name}' berhasil diperbarui.");
    }
}
