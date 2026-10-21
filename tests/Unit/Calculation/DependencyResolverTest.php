<?php

namespace Tests\Unit\Calculation;

use App\Http\Services\Calculation\CalculationException;
use App\Http\Services\Calculation\DependencyResolver;
use App\Http\Services\Calculation\FormulaParser;
use Tests\TestCase;

class DependencyResolverTest extends TestCase
{
    private DependencyResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = new DependencyResolver(new FormulaParser);
    }

    public function test_formula_diurutkan_berdasarkan_dependensi(): void
    {
        $formulas = [
            (object) ['code' => 'a', 'expression' => 'b + 1'],
            (object) ['code' => 'b', 'expression' => 'c * 2'],
            (object) ['code' => 'c', 'expression' => 'x + 1'],
            (object) ['code' => 'd', 'expression' => 'x + 2'],
        ];

        $this->assertSame(['c', 'b', 'a', 'd'], $this->resolver->resolve($formulas));
    }

    public function test_formula_tanpa_dependensi_diurutkan_stabil(): void
    {
        $formulas = [
            (object) ['code' => 'b', 'expression' => 'x + 1'],
            (object) ['code' => 'a', 'expression' => 'x + 2'],
        ];

        $this->assertSame(['a', 'b'], $this->resolver->resolve($formulas));
    }

    public function test_siklus_dependensi_ditolak(): void
    {
        $formulas = [
            (object) ['code' => 'a', 'expression' => 'b + 1'],
            (object) ['code' => 'b', 'expression' => 'a + 1'],
        ];

        $this->expectException(CalculationException::class);
        $this->expectExceptionMessage('Siklus dependensi formula terdeteksi');

        $this->resolver->resolve($formulas);
    }

    public function test_kode_formula_duplikat_ditolak(): void
    {
        $formulas = [
            (object) ['code' => 'a', 'expression' => 'x + 1'],
            (object) ['code' => 'a', 'expression' => 'x + 2'],
        ];

        $this->expectException(CalculationException::class);
        $this->expectExceptionMessage("Formula 'a' muncul lebih dari sekali");

        $this->resolver->resolve($formulas);
    }

    public function test_daftar_formula_kosong_ditolak(): void
    {
        $this->expectException(CalculationException::class);
        $this->expectExceptionMessage('Tidak ada formula aktif');

        $this->resolver->resolve([]);
    }

    public function test_referensi_variabel_bukan_bagian_dari_graph(): void
    {
        $formulas = [
            (object) ['code' => 'a', 'expression' => 'x + y'],
        ];

        $this->assertSame(['a'], $this->resolver->resolve($formulas));
    }
}
