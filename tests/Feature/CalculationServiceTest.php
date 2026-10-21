<?php

namespace Tests\Feature;

use App\Http\Services\Calculation\CalculationException;
use App\Http\Services\Calculation\CalculationService;
use App\Models\CalculationDetail;
use App\Models\CalculationFormula;
use App\Models\CalculationRun;
use Tests\Feature\Concerns\CalculationTestSchema;
use Tests\TestCase;

class CalculationServiceTest extends TestCase
{
    use CalculationTestSchema;

    private CalculationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpCalculation();
        $this->service = app(CalculationService::class);
    }

    public function test_preview_menghitung_formula_dan_default(): void
    {
        $this->activateOnly(['average_patients_per_day']);

        $result = $this->service->preview('kpr', 2026, [
            'annual_patient_count' => 730,
        ]);

        $this->assertSame('KPR', $result->unitCode);
        $this->assertSame(2026, $result->periodYear);
        $this->assertSame(2.0, $result->values['average_patients_per_day']);
        $this->assertSame(365.0, $result->values['days_per_year']);

        $formulaDetail = collect($result->details)->firstWhere('code', 'average_patients_per_day');
        $this->assertSame(CalculationDetail::TYPE_FORMULA, $formulaDetail['detail_type']);
        $this->assertSame(
            ['annual_patient_count' => 730.0, 'days_per_year' => 365.0],
            $formulaDetail['input_snapshot']
        );
    }

    public function test_preview_menghitung_rasio_alokasi(): void
    {
        $this->activateOnly(['building_allocation_ratio']);

        $result = $this->service->preview('KPR', 2026, [
            'building_area_allocated' => 250,
            'building_area_total' => 1000,
        ]);

        $this->assertSame(0.25, $result->values['building_allocation_ratio']);

        $detail = collect($result->details)->firstWhere('code', 'building_allocation_ratio');
        $this->assertSame(CalculationDetail::TYPE_ALLOCATION, $detail['detail_type']);
    }

    public function test_calculate_and_store_menyimpan_snapshot(): void
    {
        $this->activateOnly(['average_patients_per_day']);

        $run = $this->service->calculateAndStore(
            'kpr',
            2026,
            ['annual_patient_count' => 730],
            $this->calculationUser->id,
            'Uji kalkulasi'
        );

        $this->assertSame(CalculationRun::STATUS_CALCULATED, $run->status);
        $this->assertSame('KPR', $run->unit_code);
        $this->assertSame($this->calculationUnit->id, $run->organization_unit_id);
        $this->assertSame($this->calculationUser->id, $run->created_by);
        $this->assertSame(2026, $run->period_year);
        $this->assertSame(1, $run->formula_version);
        $this->assertNotNull($run->calculated_at);
        $this->assertSame(3, $run->details->count());

        $this->assertSame('kpr', strtolower((string) $run->parameter_snapshot['unit_code']));
        $this->assertSame(730, $run->parameter_snapshot['inputs']['annual_patient_count']);

        $detail = $run->details->firstWhere('code', 'average_patients_per_day');
        $this->assertNotNull($detail);
        $this->assertSame(2.0, (float) $detail->result_value);
        $this->assertSame('annual_patient_count / days_per_year', $detail->expression);
    }

    public function test_input_wajib_kosong_membuat_run_failed(): void
    {
        $this->activateOnly(['average_patients_per_day']);

        try {
            $this->service->calculateAndStore('KPR', 2026, [], $this->calculationUser->id);
            $this->fail('Kalkulasi seharusnya gagal karena input wajib kosong.');
        } catch (CalculationException $exception) {
            $this->assertStringContainsString('annual_patient_count', $exception->getMessage());
        }

        $run = CalculationRun::query()->latest('id')->firstOrFail();

        $this->assertSame(CalculationRun::STATUS_FAILED, $run->status);
        $this->assertStringContainsString('annual_patient_count', (string) $run->notes);
        $this->assertSame(0, $run->details()->count());
    }

    public function test_pembagian_dengan_nol_membuat_run_failed(): void
    {
        $this->activateOnly(['average_patients_per_day']);

        CalculationFormula::query()
            ->where('code', 'average_patients_per_day')
            ->update(['expression' => 'annual_patient_count / (days_per_year - 365)']);

        try {
            $this->service->calculateAndStore(
                'KPR',
                2026,
                ['annual_patient_count' => 730],
                $this->calculationUser->id
            );
            $this->fail('Kalkulasi seharusnya gagal karena pembagian dengan nol.');
        } catch (CalculationException $exception) {
            $this->assertStringContainsString('Pembagian dengan nol', $exception->getMessage());
        }

        $run = CalculationRun::query()->latest('id')->firstOrFail();

        $this->assertSame(CalculationRun::STATUS_FAILED, $run->status);
        $this->assertStringContainsString('Pembagian dengan nol', (string) $run->notes);
    }

    public function test_siklus_formula_ditolak(): void
    {
        $this->activateOnly(['average_patients_per_day', 'ciu_per_day']);

        CalculationFormula::query()
            ->where('code', 'average_patients_per_day')
            ->update(['expression' => 'ciu_per_day + 1']);

        CalculationFormula::query()
            ->where('code', 'ciu_per_day')
            ->update(['expression' => 'average_patients_per_day + 1']);

        $this->expectException(CalculationException::class);
        $this->expectExceptionMessage('Siklus dependensi formula terdeteksi');

        $this->service->preview('KPR', 2026);
    }

    public function test_referensi_tidak_dikenal_ditolak(): void
    {
        $this->activateOnly(['average_patients_per_day']);

        CalculationFormula::query()
            ->where('code', 'average_patients_per_day')
            ->update(['expression' => 'variabel_tidak_ada + 1']);

        $this->expectException(CalculationException::class);
        $this->expectExceptionMessage('tidak dikenal');

        $this->service->preview('KPR', 2026);
    }

    public function test_versi_formula_terbaru_dipakai_dan_snapshot_tidak_berubah(): void
    {
        $this->activateOnly(['average_patients_per_day']);

        $versionOne = CalculationFormula::query()
            ->where('code', 'average_patients_per_day')
            ->where('version', 1)
            ->firstOrFail();

        CalculationFormula::create([
            'code' => 'average_patients_per_day',
            'name' => 'Rata-rata pasien per hari (versi 2)',
            'component_id' => $versionOne->component_id,
            'expression' => 'annual_patient_count / 100',
            'result_unit' => 'pasien/hari',
            'result_scope' => 'unit_period',
            'version' => 2,
            'is_active' => true,
        ]);

        $active = $this->service->activeFormulas(2026);
        $this->assertSame(2, $active->firstWhere('code', 'average_patients_per_day')->version);

        $run = $this->service->calculateAndStore(
            'KPR',
            2026,
            ['annual_patient_count' => 730],
            $this->calculationUser->id
        );

        $this->assertSame(2, $run->formula_version);

        $detail = $run->details->firstWhere('code', 'average_patients_per_day');
        $this->assertSame(2, $detail->formula_version);
        $this->assertSame('annual_patient_count / 100', $detail->expression);
        $this->assertSame(7.3, (float) $detail->result_value);

        CalculationFormula::query()
            ->where('code', 'average_patients_per_day')
            ->where('version', 2)
            ->update(['expression' => 'annual_patient_count / 50']);

        $detail->refresh();

        $this->assertSame('annual_patient_count / 100', $detail->expression);
        $this->assertSame(7.3, (float) $detail->result_value);
    }

    public function test_approve_hanya_untuk_run_calculated(): void
    {
        $this->activateOnly(['average_patients_per_day']);

        $run = $this->service->calculateAndStore(
            'KPR',
            2026,
            ['annual_patient_count' => 730],
            $this->calculationUser->id
        );

        $approved = $this->service->approve($run);

        $this->assertSame(CalculationRun::STATUS_APPROVED, $approved->status);

        $this->expectException(CalculationException::class);
        $this->expectExceptionMessage('berstatus calculated');

        $this->service->approve($approved);
    }

    /**
     * @param  array<int, string>  $codes
     */
    private function activateOnly(array $codes): void
    {
        CalculationFormula::query()->update(['is_active' => false]);
        CalculationFormula::query()->whereIn('code', $codes)->update(['is_active' => true]);
    }
}
