<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesUnitCostScope;
use App\Models\OrganizationUnit;
use App\Models\UnitCostPeriod;
use App\Services\UnitCost\UnitCostOfficialReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class UnitCostReportController extends Controller
{
    use ResolvesUnitCostScope;

    public function __construct(protected UnitCostOfficialReportService $reportService) {}

    /**
     * Surat/laporan resmi Unit Cost untuk periode + unit dalam format PDF.
     *
     * Memakai UnitCostOfficialReportService dan komponen
     * `<x-unit-cost.official-report-table>` agar isi yang sama dapat dipakai
     * ulang di halaman HTML atau ekspor lain.
     */
    public function pdf(Request $request, UnitCostPeriod $period): \Illuminate\Http\Response
    {
        $units = OrganizationUnit::query()->active()->orderBy('name')->get(['id', 'name', 'code']);
        $unit = $this->resolveUnit($request, $units);

        $componentIds = $this->reportService->componentIdsForUnit($unit);
        $report = $this->reportService->build($period, $unit, $componentIds);

        return Pdf::loadView('unit-cost.reports.official', $report)
            ->setPaper('a4', 'portrait')
            ->setOption('isPhpEnabled', true)
            ->download($this->reportService->filename($period, $unit));
    }
}
