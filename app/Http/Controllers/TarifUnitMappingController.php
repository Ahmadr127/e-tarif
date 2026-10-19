<?php

namespace App\Http\Controllers;

use App\Http\Requests\TarifUnitMapping\Store;
use App\Http\Requests\TarifUnitMapping\Update;
use App\Http\Services\TarifUnitMappingService;
use App\Models\OrganizationType;
use App\Models\OrganizationUnit;
use App\Models\TarifUnitMapping;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TarifUnitMappingController extends Controller
{
    public function __construct(protected TarifUnitMappingService $mappingService) {}

    /*
    |--------------------------------------------------------------------------
    | Mode admin (permission: manage_tarif_units) — pilih unit dari daftar
    |--------------------------------------------------------------------------
    */

    /**
     * Buku Tarif: daftar unit organisasi — titik masuk proses input tarif per unit.
     */
    public function index(Request $request)
    {
        $units = $this->mappingService->getUnits($request->only(['search', 'type_id', 'status', 'per_page']));
        $types = OrganizationType::orderBy('level')->orderBy('name')->get();

        return view('tarif-units.index', compact('units', 'types'));
    }

    /**
     * Daftar tarif milik satu unit organisasi.
     */
    public function unit(Request $request, OrganizationUnit $organization_unit)
    {
        return view('tarif-units.unit', [
            'unit' => $this->loadUnit($organization_unit),
            'mappings' => $this->mappingService->getUnitMappings(
                $organization_unit,
                $request->only(['search', 'status', 'per_page'])
            ),
            'ownUnit' => false,
        ]);
    }

    /**
     * Form input: unit dikunci sesuai unit yang diklik (?unit_id=...).
     */
    public function create(Request $request)
    {
        if (! $request->filled('unit_id')) {
            return redirect()->route('tarif-units.index')
                ->with('error', 'Pilih unit terlebih dahulu untuk input buku tarif.');
        }

        $unit = $this->findActiveUnit((int) $request->query('unit_id'));

        return view('tarif-units.create', [
            'unit' => $unit,
            'selectedTarif' => null,
            'existing' => $this->mappingService->getUnitMappings($unit, ['per_page' => 5]),
            'ownUnit' => false,
        ]);
    }

    public function store(Store $request)
    {
        $data = $request->validated();
        $unitId = (int) $data['organization_unit_id'];

        $this->guardBulkItems($data['items'], $unitId);
        $created = $this->createItems($data['items'], $unitId);

        return redirect()->route('tarif-units.unit', $unitId)
            ->with('success', $created.' tarif berhasil ditambahkan ke buku tarif unit.');
    }

    public function show(TarifUnitMapping $tarif_unit)
    {
        $tarif_unit->load([
            'tarif.jenisTarif', 'tarif.provider', 'tarif.service', 'tarif.serviceClass',
            'organizationUnit.type', 'organizationUnit.parent',
        ]);

        return view('tarif-units.show', ['mapping' => $tarif_unit]);
    }

    public function edit(TarifUnitMapping $tarif_unit)
    {
        $tarif_unit->load([
            'tarif.service', 'tarif.serviceClass', 'tarif.provider',
            'organizationUnit.type', 'organizationUnit.parent',
        ]);

        return view('tarif-units.edit', [
            'mapping' => $tarif_unit,
            'unit' => $tarif_unit->organizationUnit,
            'selectedTarif' => $tarif_unit->tarif,
            'ownUnit' => false,
        ]);
    }

    public function update(Update $request, TarifUnitMapping $tarif_unit)
    {
        $mapping = $this->mappingService->updateMapping($tarif_unit, $request->validated());

        return redirect()->route('tarif-units.unit', $mapping->organization_unit_id)
            ->with('success', 'Buku tarif unit berhasil diperbarui.');
    }

    public function destroy(TarifUnitMapping $tarif_unit)
    {
        $unitId = $tarif_unit->organization_unit_id;
        $this->mappingService->deleteMapping($tarif_unit);

        return redirect()->route('tarif-units.unit', $unitId)
            ->with('success', 'Tarif berhasil dihapus dari buku tarif unit.');
    }

    /**
     * Endpoint AJAX untuk picker tarif (dipakai form input/edit).
     */
    public function searchTarifs(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'q' => 'nullable|string|max:100',
            'id' => 'nullable|integer',
        ]);

        return response()->json([
            'data' => $this->mappingService->searchTarifs(
                $request->query('q'),
                $request->filled('id') ? (int) $request->query('id') : null,
            ),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Mode unit sendiri (permission: manage_my_tarif_unit) — unit dari profil pengguna
    |--------------------------------------------------------------------------
    */

    /**
     * Buku tarif unit milik pengguna sendiri.
     */
    public function myUnit(Request $request)
    {
        $unit = auth()->user()->organizationUnit;

        if ($unit === null) {
            return view('tarif-units.no-unit');
        }

        return view('tarif-units.unit', [
            'unit' => $this->loadUnit($unit),
            'mappings' => $this->mappingService->getUnitMappings(
                $unit,
                $request->only(['search', 'status', 'per_page'])
            ),
            'ownUnit' => true,
        ]);
    }

    /**
     * Form input untuk unit sendiri.
     */
    public function myCreate()
    {
        $unit = $this->currentUnit();

        return view('tarif-units.create', [
            'unit' => $this->loadUnit($unit),
            'selectedTarif' => null,
            'existing' => $this->mappingService->getUnitMappings($unit, ['per_page' => 5]),
            'ownUnit' => true,
        ]);
    }

    public function myStore(Store $request)
    {
        $unit = $this->currentUnit();

        // Unit selalu dipaksa milik pengguna, apa pun yang dikirim form.
        $items = $request->validated()['items'];

        $this->guardBulkItems($items, $unit->id);
        $created = $this->createItems($items, $unit->id);

        return redirect()->route('buku-tarif-saya.index')
            ->with('success', $created.' tarif berhasil ditambahkan ke buku tarif unit Anda.');
    }

    public function myEdit(TarifUnitMapping $tarif_unit)
    {
        $unit = $this->authorizeOwnMapping($tarif_unit);
        $tarif_unit->load(['tarif.service', 'tarif.serviceClass', 'tarif.provider']);

        return view('tarif-units.edit', [
            'mapping' => $tarif_unit,
            'unit' => $this->loadUnit($unit),
            'selectedTarif' => $tarif_unit->tarif,
            'ownUnit' => true,
        ]);
    }

    public function myUpdate(Update $request, TarifUnitMapping $tarif_unit)
    {
        $unit = $this->authorizeOwnMapping($tarif_unit);

        $data = $request->validated();
        $data['organization_unit_id'] = $unit->id;

        $this->guardDuplicatePair($unit->id, (int) $data['tarif_id'], $tarif_unit->id);

        $this->mappingService->updateMapping($tarif_unit, $data);

        return redirect()->route('buku-tarif-saya.index')
            ->with('success', 'Buku tarif unit Anda berhasil diperbarui.');
    }

    public function myDestroy(TarifUnitMapping $tarif_unit)
    {
        $this->authorizeOwnMapping($tarif_unit);

        $this->mappingService->deleteMapping($tarif_unit);

        return redirect()->route('buku-tarif-saya.index')
            ->with('success', 'Tarif berhasil dihapus dari buku tarif unit Anda.');
    }

    /*
    |--------------------------------------------------------------------------
    | Helper
    |--------------------------------------------------------------------------
    */

    protected function loadUnit(OrganizationUnit $unit): OrganizationUnit
    {
        return $unit->load(['type', 'parent']);
    }

    /**
     * Unit organisasi milik pengguna yang sedang login.
     */
    protected function currentUnit(): OrganizationUnit
    {
        $unit = auth()->user()->organizationUnit;

        abort_if($unit === null, 403, 'Akun Anda belum terhubung ke unit organisasi.');

        return $unit;
    }

    /**
     * Pastikan mapping yang diakses memang milik unit pengguna.
     */
    protected function authorizeOwnMapping(TarifUnitMapping $mapping): OrganizationUnit
    {
        $unit = $this->currentUnit();

        abort_if(
            (int) $mapping->organization_unit_id !== (int) $unit->id,
            403,
            'Anda hanya dapat mengelola buku tarif unit Anda sendiri.'
        );

        return $unit;
    }

    /**
     * Cegah pasangan tarif–unit ganda (validasi request memakai unit dari form,
     * sedangkan unit di sini dipaksa milik pengguna).
     */
    protected function guardDuplicatePair(int $unitId, int $tarifId, ?int $ignoreMappingId = null): void
    {
        $exists = TarifUnitMapping::query()
            ->where('organization_unit_id', $unitId)
            ->where('tarif_id', $tarifId)
            ->when($ignoreMappingId, fn ($q) => $q->where('id', '!=', $ignoreMappingId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'tarif_id' => 'Tarif ini sudah ada di buku tarif unit Anda.',
            ]);
        }
    }

    /**
     * Simpan beberapa baris input buku tarif sekaligus.
     *
     * @param  array<int|string, array<string, mixed>>  $items
     */
    protected function createItems(array $items, int $unitId): int
    {
        return DB::transaction(function () use ($items, $unitId) {
            $count = 0;

            foreach ($items as $item) {
                $this->mappingService->createMapping([
                    'tarif_id' => (int) $item['tarif_id'],
                    'organization_unit_id' => $unitId,
                    'status' => $item['status'] ?? 'active',
                    'notes' => $item['notes'] ?? null,
                ]);

                $count++;
            }

            return $count;
        });
    }

    /**
     * Pastikan tidak ada tarif ganda: baik di dalam satu kiriman form maupun
     * yang sudah ada di buku tarif unit. Error diarahkan ke baris terkait.
     *
     * @param  array<int|string, array<string, mixed>>  $items
     */
    protected function guardBulkItems(array $items, int $unitId): void
    {
        $seen = [];

        foreach ($items as $key => $item) {
            $tarifId = (int) ($item['tarif_id'] ?? 0);

            if (isset($seen[$tarifId])) {
                throw ValidationException::withMessages([
                    "items.{$key}.tarif_id" => 'Tarif ini dipilih lebih dari sekali.',
                ]);
            }

            $exists = TarifUnitMapping::query()
                ->where('organization_unit_id', $unitId)
                ->where('tarif_id', $tarifId)
                ->exists();

            if ($exists) {
                throw ValidationException::withMessages([
                    "items.{$key}.tarif_id" => 'Tarif ini sudah ada di buku tarif unit ini.',
                ]);
            }

            $seen[$tarifId] = true;
        }
    }

    /**
     * Unit pada form input admin wajib ada dan aktif.
     */
    protected function findActiveUnit(int $id): OrganizationUnit
    {
        $unit = OrganizationUnit::with('type')->whereKey($id)->first();

        abort_if($unit === null, 404, 'Unit organisasi tidak ditemukan.');
        abort_if(! $unit->is_active, 404, 'Unit organisasi tidak aktif.');

        return $unit;
    }
}
