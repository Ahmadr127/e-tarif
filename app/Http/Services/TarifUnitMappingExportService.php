<?php

namespace App\Http\Services;

use App\Models\OrganizationUnit;
use App\Models\TarifUnitMapping;

/**
 * Export buku tarif satu unit organisasi. Kolom tarif memakai definisi
 * TarifExportService (satu sumber), ditambah kolom khusus buku tarif:
 * status pemetaan + catatan.
 *
 * Format: XLSX (App\Exports\TarifUnitMappingExport) dan PDF
 * (view tarif-units.pdf).
 */
class TarifUnitMappingExportService
{
    public function __construct(protected TarifUnitMappingService $mappingService) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function filename(OrganizationUnit $unit, array $filters, string $extension): string
    {
        $code = $unit->code ?: 'unit';

        return 'buku-tarif-'.$code.'-'.now()->format('Ymd-His').'.'.$extension;
    }

    public static function headings(): array
    {
        return array_merge(TarifExportService::headings(), ['Status Buku Tarif', 'Catatan']);
    }

    /**
     * Satu baris buku tarif: kolom tarif + status pemetaan & catatan.
     *
     * @return array<int, mixed>
     */
    public static function toRow(TarifUnitMapping $mapping): array
    {
        $tarif = $mapping->tarif;

        $tarifRow = $tarif
            ? TarifExportService::toRow($tarif)
            : array_fill(0, count(TarifExportService::headings()), '-');

        return array_merge($tarifRow, [
            $mapping->status === 'active' ? 'Aktif' : 'Non-Aktif',
            $mapping->notes ?? '',
        ]);
    }
}