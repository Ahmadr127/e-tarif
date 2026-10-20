<?php

namespace App\Http\Services;

use App\Models\OrganizationUnit;
use App\Models\Tarif;

/**
 * Definisi kolom export buku tarif unit. Kolom tarif memakai satu sumber
 * (TarifExportService) agar XLSX/PDF/CSV konsisten.
 */
class TarifUnitBookExportService
{
    public function filename(OrganizationUnit $unit, string $extension): string
    {
        $code = $unit->code ?: 'unit';

        return 'buku-tarif-'.$code.'-'.now()->format('Ymd-His').'.'.$extension;
    }

    /**
     * @return array<int, string>
     */
    public static function headings(): array
    {
        return TarifExportService::headings();
    }

    /**
     * @return array<int, mixed>
     */
    public static function toRow(Tarif $tarif): array
    {
        return TarifExportService::toRow($tarif);
    }
}
