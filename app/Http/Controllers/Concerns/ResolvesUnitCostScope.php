<?php

namespace App\Http\Controllers\Concerns;

use App\Models\OrganizationUnit;
use Illuminate\Http\Request;

/**
 * Membatasi konteks unit pada modul Unit Cost.
 *
 * Admin bebas memilih unit. Operator unit dengan permission `input_unit_cost`
 * selalu dikunci ke unit organisasi pada profilnya, termasuk saat unit_id di
 * URL dimanipulasi.
 */
trait ResolvesUnitCostScope
{
    protected function isUnitCostAdmin(Request $request): bool
    {
        return (bool) $request->user()?->isUnitCostAdmin();
    }

    protected function isScopedUnitCostUser(Request $request): bool
    {
        return (bool) $request->user()?->isScopedUnitCostUser();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, OrganizationUnit>|null  $units
     */
    protected function resolveUnit(Request $request, $units = null): ?OrganizationUnit
    {
        if ($this->isScopedUnitCostUser($request)) {
            return $this->resolveOwnUnit($request);
        }

        $unitId = $request->input('unit_id');

        if ($unitId === '0' || $unitId === 0) {
            return null;
        }

        if ($unitId !== null && $unitId !== '') {
            return OrganizationUnit::query()->find((int) $unitId);
        }

        if ($units !== null) {
            return $units->first();
        }

        return OrganizationUnit::query()->active()->orderBy('name')->first();
    }

    protected function resolveOwnUnit(Request $request): OrganizationUnit
    {
        $unit = $request->user()?->organizationUnit;

        abort_if($unit === null, 403, 'Akun Anda belum terhubung ke unit organisasi.');

        $requested = $request->input('unit_id');

        if ($requested !== null && $requested !== '' && (string) $requested !== (string) $unit->id) {
            abort(403, 'Anda hanya dapat mengakses unit organisasi sendiri.');
        }

        return $unit;
    }

    protected function assertComponentAccessible(Request $request, ?int $componentId): void
    {
        if (! $this->isScopedUnitCostUser($request)) {
            return;
        }

        abort_if($componentId === null, 403, 'Parameter global hanya dapat diakses admin.');

        $unit = $this->resolveOwnUnit($request);

        $assigned = $unit->unitCostComponents()
            ->whereKey($componentId)
            ->where('unit_cost_components.is_active', true)
            ->exists();

        abort_unless($assigned, 403, 'Komponen ini tidak di-assign ke unit Anda.');
    }
}
