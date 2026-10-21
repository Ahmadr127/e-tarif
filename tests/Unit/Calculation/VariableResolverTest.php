<?php

namespace Tests\Unit\Calculation;

use App\Http\Services\Calculation\CalculationException;
use App\Http\Services\Calculation\VariableResolver;
use Tests\TestCase;

class VariableResolverTest extends TestCase
{
    private VariableResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = new VariableResolver;
    }

    public function test_input_dan_default_diselesaikan(): void
    {
        $variables = [
            $this->variable('annual_patient_count', 'pasien/tahun'),
            $this->variable('days_per_year', 'hari', default: 365),
        ];

        $values = $this->resolver->resolve($variables, ['annual_patient_count' => '730']);

        $this->assertSame(730.0, $values['annual_patient_count']);
        $this->assertSame(365.0, $values['days_per_year']);
    }

    public function test_variabel_wajib_yang_kosong_ditolak(): void
    {
        $variables = [
            $this->variable('annual_patient_count', 'pasien/tahun'),
        ];

        $this->expectException(CalculationException::class);
        $this->expectExceptionMessage("Variabel 'annual_patient_count' wajib diisi");

        $this->resolver->resolve($variables, []);
    }

    public function test_required_codes_membatasi_kewajiban(): void
    {
        $variables = [
            $this->variable('annual_patient_count', 'pasien/tahun'),
            $this->variable('bed_count', 'tempat tidur'),
        ];

        $values = $this->resolver->resolve(
            $variables,
            ['annual_patient_count' => 10],
            ['annual_patient_count']
        );

        $this->assertArrayNotHasKey('bed_count', $values);
    }

    public function test_rasio_di_luar_nol_sampai_satu_ditolak(): void
    {
        $variables = [
            $this->variable('electricity_allocation_ratio', 'rasio 0-1'),
        ];

        $this->expectException(CalculationException::class);
        $this->expectExceptionMessage('rentang 0 sampai 1');

        $this->resolver->resolve($variables, ['electricity_allocation_ratio' => 3]);
    }

    public function test_input_tidak_dikenal_ditolak(): void
    {
        $variables = [
            $this->variable('annual_patient_count', 'pasien/tahun'),
        ];

        $this->expectException(CalculationException::class);
        $this->expectExceptionMessage('Variabel input tidak dikenal: typo_variable');

        $this->resolver->resolve($variables, ['typo_variable' => 10]);
    }

    public function test_variabel_turunan_tidak_boleh_diisi_manual(): void
    {
        $variables = [
            $this->variable('building_allocation_ratio', 'rasio 0-1', sourceType: 'derived'),
        ];

        $this->expectException(CalculationException::class);
        $this->expectExceptionMessage('tidak boleh diisi manual');

        $this->resolver->resolve($variables, ['building_allocation_ratio' => 0.5]);
    }

    public function test_nilai_bukan_angka_ditolak(): void
    {
        $variables = [
            $this->variable('annual_patient_count', 'pasien/tahun'),
        ];

        $this->expectException(CalculationException::class);
        $this->expectExceptionMessage('harus berupa angka');

        $this->resolver->resolve($variables, ['annual_patient_count' => 'sepuluh']);
    }

    public function test_bilangan_bulat_tidak_boleh_pecahan(): void
    {
        $variables = [
            $this->variable('bed_count', 'tempat tidur', valueType: 'integer'),
        ];

        $this->expectException(CalculationException::class);
        $this->expectExceptionMessage('harus berupa bilangan bulat');

        $this->resolver->resolve($variables, ['bed_count' => 1.5]);
    }

    public function test_boolean_dan_string_tidak_dipakai_untuk_aritmetika(): void
    {
        $variables = [
            $this->variable('flag', null, valueType: 'string'),
        ];

        $this->expectException(CalculationException::class);
        $this->expectExceptionMessage('bertipe teks');

        $this->resolver->resolve($variables, ['flag' => 'ya']);
    }

    private function variable(
        string $code,
        ?string $unit,
        mixed $default = null,
        string $sourceType = 'manual',
        string $valueType = 'decimal',
        bool $required = true,
    ): object {
        return (object) [
            'code' => $code,
            'name' => $code,
            'unit' => $unit,
            'default_value' => $default,
            'source_type' => $sourceType,
            'value_type' => $valueType,
            'is_required' => $required,
            'is_active' => true,
            'source_name' => null,
        ];
    }
}
