<?php

namespace App\Http\Controllers;

use App\Http\Requests\TarifUnitServicePrefix\Store;
use App\Http\Requests\TarifUnitServicePrefix\Update;
use App\Http\Services\TarifUnitBookService;
use App\Http\Services\TarifUnitServicePrefixService;
use App\Models\OrganizationUnit;
use App\Models\TarifUnitServicePrefix;

/**
 * Konfigurasi prefix kode service per unit.
 *
 * Prefix hanya menentukan cakupan buku tarif; halaman ini tidak pernah
 * mengubah nominal tarif master.
 */
class TarifUnitServicePrefixController extends Controller
{
    public function __construct(
        protected TarifUnitServicePrefixService $prefixService,
        protected TarifUnitBookService $bookService,
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Mode admin (permission: manage_tarif_units / edit / delete)
    |--------------------------------------------------------------------------
    */

    public function index(OrganizationUnit $organization_unit)
    {
        return $this->renderIndex($organization_unit, false);
    }

    public function create(OrganizationUnit $organization_unit)
    {
        return $this->renderForm($organization_unit, null, false);
    }

    public function store(Store $request, OrganizationUnit $organization_unit)
    {
        $prefix = $this->prefixService->create($organization_unit, $request->validated());

        return redirect()
            ->route('tarif-units.prefixes.index', $organization_unit)
            ->with('success', 'Prefix '.$prefix->prefix.' berhasil ditambahkan.');
    }

    public function edit(OrganizationUnit $organization_unit, TarifUnitServicePrefix $prefix)
    {
        $this->ensureBelongsToUnit($prefix, $organization_unit);

        return $this->renderForm($organization_unit, $prefix, false);
    }

    public function update(Update $request, OrganizationUnit $organization_unit, TarifUnitServicePrefix $prefix)
    {
        $this->ensureBelongsToUnit($prefix, $organization_unit);

        $this->prefixService->update($prefix, $request->validated());

        return redirect()
            ->route('tarif-units.prefixes.index', $organization_unit)
            ->with('success', 'Prefix berhasil diperbarui.');
    }

    public function destroy(OrganizationUnit $organization_unit, TarifUnitServicePrefix $prefix)
    {
        $this->ensureBelongsToUnit($prefix, $organization_unit);

        $this->prefixService->delete($prefix);

        return redirect()
            ->route('tarif-units.prefixes.index', $organization_unit)
            ->with('success', 'Prefix berhasil dihapus.');
    }

    /*
    |--------------------------------------------------------------------------
    | Mode unit sendiri (permission: manage_my_tarif_unit / edit / delete)
    |--------------------------------------------------------------------------
    */

    public function myIndex()
    {
        return $this->renderIndex($this->currentUnit(), true);
    }

    public function myCreate()
    {
        return $this->renderForm($this->currentUnit(), null, true);
    }

    public function myStore(Store $request)
    {
        $unit = $this->currentUnit();
        $prefix = $this->prefixService->create($unit, $request->validated());

        return redirect()
            ->route('buku-tarif-saya.prefixes.index')
            ->with('success', 'Prefix '.$prefix->prefix.' berhasil ditambahkan.');
    }

    public function myEdit(TarifUnitServicePrefix $prefix)
    {
        $unit = $this->currentUnit();
        $this->ensureBelongsToUnit($prefix, $unit);

        return $this->renderForm($unit, $prefix, true);
    }

    public function myUpdate(Update $request, TarifUnitServicePrefix $prefix)
    {
        $unit = $this->currentUnit();
        $this->ensureBelongsToUnit($prefix, $unit);

        $this->prefixService->update($prefix, $request->validated());

        return redirect()
            ->route('buku-tarif-saya.prefixes.index')
            ->with('success', 'Prefix berhasil diperbarui.');
    }

    public function myDestroy(TarifUnitServicePrefix $prefix)
    {
        $unit = $this->currentUnit();
        $this->ensureBelongsToUnit($prefix, $unit);

        $this->prefixService->delete($prefix);

        return redirect()
            ->route('buku-tarif-saya.prefixes.index')
            ->with('success', 'Prefix berhasil dihapus.');
    }

    /*
    |--------------------------------------------------------------------------
    | Helper
    |--------------------------------------------------------------------------
    */

    protected function renderIndex(OrganizationUnit $unit, bool $ownUnit)
    {
        return view('tarif-units.prefixes.index', [
            'unit' => $unit->load(['type', 'parent']),
            'prefixes' => $this->bookService->prefixes($unit),
            'ownUnit' => $ownUnit,
        ]);
    }

    protected function renderForm(OrganizationUnit $unit, ?TarifUnitServicePrefix $prefix, bool $ownUnit)
    {
        return view('tarif-units.prefixes.form', [
            'unit' => $unit->load(['type', 'parent']),
            'prefix' => $prefix,
            'ownUnit' => $ownUnit,
        ]);
    }

    /**
     * Prefix hanya boleh diakses lewat unit pemiliknya. 404 (bukan 403)
     * agar keberadaan prefix unit lain tidak bocor.
     */
    protected function ensureBelongsToUnit(TarifUnitServicePrefix $prefix, OrganizationUnit $unit): void
    {
        abort_if((int) $prefix->organization_unit_id !== (int) $unit->id, 404);
    }

    protected function currentUnit(): OrganizationUnit
    {
        $unit = auth()->user()->organizationUnit;

        abort_if($unit === null, 403, 'Akun Anda belum terhubung ke unit organisasi.');

        return $unit;
    }
}
