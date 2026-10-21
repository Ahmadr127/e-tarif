<?php

namespace App\Http\Services\Calculation;

use App\Models\CalculationRun;
use App\Models\OrganizationUnit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Orkestrator kalkulasi unit cost.
 *
 * Perhitungan dilakukan FormulaEngine; service ini bertanggung jawab pada
 * workflow run, penyimpanan snapshot, dan approval.
 */
class CalculationService
{
    public function __construct(protected FormulaEngine $engine) {}

    /**
     * Hitung tanpa menyimpan run.
     *
     * @param  array<string, mixed>  $inputs
     *
     * @throws CalculationException
     */
    public function preview(string $unitCode, int $periodYear, array $inputs = []): CalculationResult
    {
        return $this->engine->preview($unitCode, $periodYear, $inputs);
    }

    /**
     * Hitung lalu simpan run beserta snapshot detail.
     *
     * Run dibuat lebih dulu dengan status draft. Jika kalkulasi gagal, run
     * tetap tersimpan sebagai `failed` agar penyebabnya dapat diaudit.
     *
     * @param  array<string, mixed>  $inputs
     *
     * @throws CalculationException
     */
    public function calculateAndStore(
        string $unitCode,
        int $periodYear,
        array $inputs = [],
        ?int $createdBy = null,
        ?string $notes = null,
    ): CalculationRun {
        $unitCode = $this->normalizeUnitCode($unitCode);
        $this->validatePeriod($periodYear);

        $unit = OrganizationUnit::query()->where('code', $unitCode)->first();

        $snapshot = [
            'unit_code' => $unitCode,
            'period_year' => $periodYear,
            'inputs' => $inputs,
        ];

        $run = CalculationRun::create([
            'run_code' => $this->generateRunCode($unitCode, $periodYear),
            'organization_unit_id' => $unit?->id,
            'unit_code' => $unitCode,
            'period_year' => $periodYear,
            'status' => CalculationRun::STATUS_DRAFT,
            'formula_version' => 1,
            'parameter_snapshot' => $snapshot,
            'created_by' => $createdBy,
            'notes' => $notes,
        ]);

        try {
            $result = $this->preview($unitCode, $periodYear, $inputs);

            DB::transaction(function () use ($run, $result, $snapshot): void {
                foreach ($result->details as $detail) {
                    $run->details()->create($detail);
                }

                $run->update([
                    'status' => CalculationRun::STATUS_CALCULATED,
                    'formula_version' => $result->formulaVersion,
                    'parameter_snapshot' => array_merge($snapshot, [
                        'formula_version' => $result->formulaVersion,
                        'resolved_values' => $result->values,
                    ]),
                    'total_unit_cost' => $result->totalUnitCost,
                    'calculated_at' => now(),
                ]);
            });
        } catch (CalculationException $exception) {
            $run->update([
                'status' => CalculationRun::STATUS_FAILED,
                'notes' => $this->appendNote($notes, $exception->getMessage()),
            ]);

            throw new CalculationException(
                $exception->getMessage(),
                array_merge($exception->context, ['run_id' => $run->id])
            );
        } catch (\Throwable $exception) {
            $run->update([
                'status' => CalculationRun::STATUS_FAILED,
                'notes' => $this->appendNote($notes, 'Kesalahan internal saat menyimpan hasil kalkulasi.'),
            ]);

            throw $exception;
        }

        return $run->fresh(['details', 'organizationUnit', 'creator']);
    }

    /**
     * @throws CalculationException
     */
    public function approve(CalculationRun $run, ?string $notes = null): CalculationRun
    {
        if ($run->status !== CalculationRun::STATUS_CALCULATED) {
            throw new CalculationException(
                'Hanya run berstatus calculated yang dapat disetujui.'
            );
        }

        $run->update([
            'status' => CalculationRun::STATUS_APPROVED,
            'notes' => $this->appendNote($run->notes, $notes),
        ]);

        return $run->refresh();
    }

    /**
     * @return \Illuminate\Support\Collection<int, \App\Models\CalculationFormula>
     */
    public function activeFormulas(int $periodYear): \Illuminate\Support\Collection
    {
        return $this->engine->activeFormulas($periodYear);
    }

    /**
     * Kode variabel input yang direferensikan formula aktif pada tahun tertentu.
     *
     * @return array<int, string>
     */
    public function requiredVariableCodes(int $periodYear): array
    {
        return $this->engine->requiredVariableCodes($periodYear);
    }

    private function normalizeUnitCode(string $unitCode): string
    {
        $unitCode = mb_strtoupper(trim($unitCode));

        if ($unitCode === '') {
            throw new CalculationException('Kode unit wajib diisi.');
        }

        if (mb_strlen($unitCode) > 80) {
            throw new CalculationException('Kode unit maksimal 80 karakter.');
        }

        return $unitCode;
    }

    private function validatePeriod(int $periodYear): void
    {
        if ($periodYear < 2000 || $periodYear > 2100) {
            throw new CalculationException('Tahun periode harus berada pada rentang 2000-2100.');
        }
    }

    private function generateRunCode(string $unitCode, int $periodYear): string
    {
        $unitPart = preg_replace('/[^A-Z0-9]/', '', $unitCode) ?: 'UNIT';
        $unitPart = substr($unitPart, 0, 20);

        return sprintf(
            'UC-%s-%d-%s',
            $unitPart,
            $periodYear,
            Str::upper((string) Str::ulid())
        );
    }

    private function appendNote(?string $existing, ?string $addition): ?string
    {
        $pieces = array_values(array_filter([
            trim((string) $existing),
            trim((string) $addition),
        ], static fn (string $value): bool => $value !== ''));

        return $pieces === [] ? null : implode(' | ', $pieces);
    }
}
