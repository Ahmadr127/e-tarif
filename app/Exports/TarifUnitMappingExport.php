<?php

namespace App\Exports;

use App\Http\Services\TarifUnitMappingExportService;
use App\Http\Services\TarifUnitMappingService;
use App\Models\OrganizationUnit;
use App\Models\TarifUnitMapping;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * Export XLSX buku tarif satu unit organisasi, memakai filter yang sama
 * dengan halaman unit (query logic di TarifUnitMappingService).
 */
class TarifUnitMappingExport implements FromQuery, WithChunkReading, WithHeadings, WithMapping
{
    use Exportable;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function __construct(
        protected OrganizationUnit $unit,
        protected array $filters = [],
    ) {}

    public function query(): \Illuminate\Database\Eloquent\Builder
    {
        return app(TarifUnitMappingService::class)
            ->unitMappingsQuery($this->unit, $this->filters)
            ->orderBy('created_at', 'desc');
    }

    /**
     * @param  TarifUnitMapping  $mapping
     */
    public function map($mapping): array
    {
        return TarifUnitMappingExportService::toRow($mapping);
    }

    public function headings(): array
    {
        return TarifUnitMappingExportService::headings();
    }

    public function chunkSize(): int
    {
        return 1000;
    }
}
