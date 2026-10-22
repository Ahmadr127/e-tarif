<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesUnitCostScope;
use App\Http\Requests\UnitCost\StoreUnitCostPeriod;
use App\Http\Requests\UnitCost\UpdateUnitCostPeriod;
use App\Models\OrganizationUnit;
use App\Models\UnitCostComponent;
use App\Models\UnitCostParameter;
use App\Models\UnitCostPeriod;
use App\Models\UnitCostResultSnapshot;
use App\Services\UnitCost\FormulaException;
use App\Services\UnitCost\ParameterEvaluationService;
use Illuminate\Http\Request;

class UnitCostPeriodController extends Controller
{
    use ResolvesUnitCostScope;

    public function __construct(protected ParameterEvaluationService $service) {}

    public function index(Request $request)
    {
        $filters = $request->only(['search', 'period_year', 'status', 'per_page']);
        $query = UnitCostPeriod::query()->with('creator');

        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $like = '%'.addcslashes(mb_strtolower($search), '%_\\').'%';

            $query->where(function ($query) use ($like): void {
                $query->whereRaw('LOWER(code) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(name) LIKE ?', [$like]);
            });
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

        $periods = $query
            ->orderByDesc('period_year')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        $stats = [];

        foreach ($periods as $period) {
            $stats[$period->id] = $this->service->periodStats($period);
        }

        return view('unit-cost.periods.index', [
            'periods' => $periods,
            'filters' => $filters,
            'stats' => $stats,
        ]);
    }

    public function create()
    {
        return view('unit-cost.periods.create', [
            'period' => new UnitCostPeriod([
                'period_year' => (int) now()->year,
                'status' => UnitCostPeriod::STATUS_DRAFT,
            ]),
        ]);
    }

    public function store(StoreUnitCostPeriod $request)
    {
        $period = UnitCostPeriod::create([
            'code' => $request->string('code')->toString(),
            'name' => $request->string('name')->toString(),
            'period_year' => (int) $request->input('period_year'),
            'status' => (string) ($request->input('status') ?: UnitCostPeriod::STATUS_DRAFT),
            'created_by' => auth()->id(),
        ]);

        return redirect()
            ->route('calculations.periods.show', $period)
            ->with('success', "Periode '{$period->name}' berhasil dibuat.");
    }

    public function edit(UnitCostPeriod $period)
    {
        return view('unit-cost.periods.edit', [
            'period' => $period,
        ]);
    }

    public function update(UpdateUnitCostPeriod $request, UnitCostPeriod $period)
    {
        if ($period->isClosed() && $request->input('status') !== UnitCostPeriod::STATUS_CLOSED) {
            return redirect()
                ->back()
                ->with('error', 'Periode yang sudah ditutup tidak dapat diubah.');
        }

        $period->update([
            'code' => $request->string('code')->toString(),
            'name' => $request->string('name')->toString(),
            'period_year' => (int) $request->input('period_year'),
            'status' => (string) ($request->input('status') ?: $period->status),
        ]);

        return redirect()
            ->route('calculations.periods.show', $period)
            ->with('success', 'Periode berhasil diperbarui.');
    }

    public function destroy(UnitCostPeriod $period)
    {
        if (! $period->isDraft()) {
            return redirect()
                ->back()
                ->with('error', 'Hanya periode berstatus draf yang dapat dihapus.');
        }

        $name = $period->name;
        $period->delete();

        return redirect()
            ->route('calculations.index')
            ->with('success', "Periode '{$name}' berhasil dihapus.");
    }

    public function activate(UnitCostPeriod $period)
    {
        if ($period->isClosed()) {
            return redirect()
                ->back()
                ->with('error', 'Periode yang sudah ditutup tidak dapat diaktifkan kembali.');
        }

        $period->update(['status' => UnitCostPeriod::STATUS_ACTIVE]);

        return redirect()
            ->back()
            ->with('success', 'Periode berhasil diaktifkan.');
    }

    public function close(UnitCostPeriod $period)
    {
        $period->update(['status' => UnitCostPeriod::STATUS_CLOSED]);

        return redirect()
            ->back()
            ->with('success', 'Periode berhasil ditutup.');
    }

    public function show(Request $request, UnitCostPeriod $period)
    {
        $isAdmin = $this->isUnitCostAdmin($request);
        $isScopedUser = $this->isScopedUnitCostUser($request);

        $units = OrganizationUnit::query()->active()->orderBy('name')->get(['id', 'name', 'code']);
        $unit = $this->resolveUnit($request, $units);

        $components = UnitCostComponent::query()
            ->active()
            // Admin melihat semua komponen aktif. Operator unit hanya melihat
            // komponen yang di-assign ke unitnya.
            ->when($unit !== null && ! $isAdmin, function ($query) use ($unit): void {
                $query->whereHas('units', fn ($relation) => $relation->whereKey($unit->id));
            })
            ->withCount([
                'parameters' => fn ($query) => $query->active(),
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $globalCount = UnitCostParameter::query()
            ->active()
            ->whereNull('component_id')
            ->count();

        $summaries = $this->service->componentSummaries($period, $unit);
        $periodStats = $this->service->periodStats($period);

        $periods = UnitCostPeriod::query()
            ->orderByDesc('period_year')
            ->orderByDesc('id')
            ->get(['id', 'name', 'period_year']);

        return view('unit-cost.periods.show', [
            'period' => $period,
            'periods' => $periods,
            'unit' => $unit,
            'components' => $components,
            'globalCount' => $globalCount,
            'summaries' => $summaries,
            'periodStats' => $periodStats,
            'isAdmin' => $isAdmin,
            'isScopedUser' => $isScopedUser,
            'canApprove' => $request->user()->hasPermission('approve_unit_cost_results')
                || $request->user()->hasPermission('approve_calculations')
                || $request->user()->hasPermission('manage_unit_cost')
                || $request->user()->hasPermission('manage_calculations'),
        ]);
    }

    public function approve(Request $request, UnitCostPeriod $period)
    {
        $unit = $this->resolveUnit($request);

        try {
            $snapshot = $this->service->createSnapshot($period, $unit, auth()->id());
        } catch (FormulaException $exception) {
            return redirect()
                ->back()
                ->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('calculations.history.show', $snapshot)
            ->with('success', 'Hasil unit cost berhasil disetujui dan snapshot resmi telah dibuat.');
    }

    public function history(Request $request)
    {
        $snapshots = UnitCostResultSnapshot::query()
            ->with(['period', 'unit', 'approver'])
            ->latest('approved_at')
            ->latest('id')
            ->paginate(20);

        return view('unit-cost.history.index', [
            'snapshots' => $snapshots,
        ]);
    }

    public function historyShow(UnitCostResultSnapshot $snapshot)
    {
        $snapshot->load(['period', 'unit', 'creator', 'approver']);

        return view('unit-cost.history.show', [
            'snapshot' => $snapshot,
            'payload' => $snapshot->payload ?? [],
        ]);
    }
}
