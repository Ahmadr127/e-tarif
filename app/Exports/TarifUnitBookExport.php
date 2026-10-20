<?php

namespace App\Exports;

use App\Http\Services\TarifUnitBookExportService;
use App\Http\Services\TarifUnitBookService;
use App\Models\OrganizationUnit;
use App\Models\Tarif;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * Export XLSX buku tarif unit. Memakai query prefix yang sama dengan
 * halaman buku tarif, dibaca per chunk agar dataset besar tidak dimuat
 * seluruhnya ke memori.
 */
class TarifUnitBookExport implements FromQuery, WithChunkReading, WithHeadings, WithMapping
{
    use Exportable;

    /**
     * @param  array<string, mixed>  $filters
     * @param  array<int, string>|null  $prefixes
     */
    public function __construct(
        protected OrganizationUnit $unit,
        protected array $filters = [],
        protected ?array $prefixes = null,
    ) {}

    public function query(): \Illuminate\Database\Eloquent\Builder
    {
        return app(TarifUnitBookService::class)
            ->exportQuery($this->unit, $this->filters, $this->prefixes);
    }

    /**
     * @param  Tarif  $tarif
     */
    public function map($tarif): array
    {
        return TarifUnitBookExportService::toRow($tarif);
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return TarifUnitBookExportService::headings();
    }

    public function chunkSize(): int
    {
        return 1000;
    }
}
