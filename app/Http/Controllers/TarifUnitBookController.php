<?php

namespace App\Http\Controllers;

use App\Exports\TarifUnitBookExport;
use App\Http\Services\TarifUnitBookExportService;
use App\Http\Services\TarifUnitBookService;
use App\Models\OrganizationType;
use App\Models\OrganizationUnit;
use App\Models\Tarif;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Buku tarif unit: daftar tarif dihitung dinamis dari prefix kode service
 * unit (tidak ada assignment tarif individual).
 */
class TarifUnitBookController extends Controller
{
    public function __construct(
        protected TarifUnitBookService $bookService,
        protected TarifUnitBookExportService $exportService,
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Mode admin (permission: manage_tarif_units)
    |--------------------------------------------------------------------------
    */

    public function index(Request $request)
    {
        $units = $this->bookService->getUnits(
            $request->only(['search', 'type_id', 'status', 'per_page'])
        );
        $types = OrganizationType::orderBy('level')->orderBy('name')->get(['id', 'name']);

        return view('tarif-units.index', compact('units', 'types'));
    }

    public function unit(Request $request, OrganizationUnit $organization_unit)
    {
        return $this->renderBook($request, $organization_unit, false);
    }

    public function show(OrganizationUnit $organization_unit, Tarif $tarif)
    {
        return $this->renderTarif($organization_unit, $tarif, false);
    }

    public function exportUnitXlsx(Request $request, OrganizationUnit $organization_unit): BinaryFileResponse
    {
        $prefixes = $this->bookService->activePrefixes($organization_unit);

        return Excel::download(
            new TarifUnitBookExport($organization_unit, $this->filters($request), $prefixes),
            $this->exportService->filename($organization_unit, 'xlsx')
        );
    }

    public function exportUnitPdf(Request $request, OrganizationUnit $organization_unit): \Illuminate\Http\Response
    {
        return $this->pdfResponse(
            $organization_unit,
            $this->filters($request),
            $this->bookService->activePrefixes($organization_unit)
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Mode unit sendiri (permission: manage_my_tarif_unit)
    |--------------------------------------------------------------------------
    */

    public function myUnit(Request $request)
    {
        $unit = auth()->user()->organizationUnit;

        if ($unit === null) {
            return view('tarif-units.no-unit');
        }

        return $this->renderBook($request, $unit, true);
    }

    public function myShow(Tarif $tarif)
    {
        return $this->renderTarif($this->currentUnit(), $tarif, true);
    }

    public function exportMyUnitXlsx(Request $request): BinaryFileResponse
    {
        $unit = $this->currentUnit();
        $prefixes = $this->bookService->activePrefixes($unit);

        return Excel::download(
            new TarifUnitBookExport($unit, $this->filters($request), $prefixes),
            $this->exportService->filename($unit, 'xlsx')
        );
    }

    public function exportMyUnitPdf(Request $request): \Illuminate\Http\Response
    {
        $unit = $this->currentUnit();

        return $this->pdfResponse(
            $unit,
            $this->filters($request),
            $this->bookService->activePrefixes($unit)
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Helper
    |--------------------------------------------------------------------------
    */

    protected function renderBook(Request $request, OrganizationUnit $unit, bool $ownUnit)
    {
        $unit = $unit->load(['type', 'parent']);

        // Prefix diambil sekali per request, lalu dipakai untuk query tarif
        // dan daftar konfigurasi di halaman (tidak ada query per baris).
        $prefixes = $this->bookService->prefixes($unit);
        $activePrefixes = $this->bookService->normalizePrefixes(
            $prefixes->where('is_active', true)->pluck('prefix')
        );

        $filters = $this->filters($request);

        return view('tarif-units.unit', [
            'unit' => $unit,
            'tarifs' => $this->bookService->paginate($unit, $filters, $activePrefixes),
            'prefixes' => $prefixes,
            'activePrefixes' => $activePrefixes,
            'filters' => $filters,
            'ownUnit' => $ownUnit,
        ]);
    }

    protected function renderTarif(OrganizationUnit $unit, Tarif $tarif, bool $ownUnit)
    {
        $unit = $unit->load(['type', 'parent']);
        $activePrefixes = $this->bookService->activePrefixes($unit);

        abort_unless(
            $this->bookService->contains($unit, $tarif, $activePrefixes),
            404,
            'Tarif tidak termasuk cakupan buku tarif unit ini.'
        );

        $tarif->load(['jenisTarif', 'provider', 'service', 'serviceClass']);

        return view('tarif-units.show', [
            'unit' => $unit,
            'tarif' => $tarif,
            'matchedPrefixes' => $this->bookService->matchingPrefixes(
                $unit,
                $tarif->service?->code,
                $activePrefixes
            ),
            'ownUnit' => $ownUnit,
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  array<int, string>  $prefixes
     */
    protected function pdfResponse(OrganizationUnit $unit, array $filters, array $prefixes): \Illuminate\Http\Response
    {
        // PDF harus merender seluruh baris ke view (bukan per chunk).
        // Untuk dataset sangat besar gunakan filter agar hasil tetap wajar.
        $tarifs = $this->bookService->exportQuery($unit, $filters, $prefixes)->get();

        return Pdf::loadView('tarif-units.pdf', [
            'unit' => $unit,
            'tarifs' => $tarifs,
            'filters' => $filters,
            'prefixes' => $prefixes,
        ])
            ->setPaper('a4', 'landscape')
            ->setOption('isPhpEnabled', true)
            ->download($this->exportService->filename($unit, 'pdf'));
    }

    /**
     * Filter yang dipakai halaman, export XLSX, dan PDF.
     *
     * @return array<string, mixed>
     */
    protected function filters(Request $request): array
    {
        return $request->only(['search', 'status', 'jenis_tarif_id', 'class_id', 'date_from', 'date_to', 'per_page']);
    }

    protected function currentUnit(): OrganizationUnit
    {
        $unit = auth()->user()->organizationUnit;

        abort_if($unit === null, 403, 'Akun Anda belum terhubung ke unit organisasi.');

        return $unit;
    }
}
