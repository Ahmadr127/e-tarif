<?php

namespace Tests\Unit\Calculation;

use App\Http\Services\Calculation\CalculationException;
use App\Http\Services\Calculation\FormulaParser;
use Tests\TestCase;

class FormulaParserTest extends TestCase
{
    private FormulaParser $parser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->parser = new FormulaParser;
    }

    public function test_aritmetika_dan_precedence(): void
    {
        $this->assertSame(14.0, $this->parser->evaluate('2 + 3 * 4', []));
        $this->assertSame(20.0, $this->parser->evaluate('(2 + 3) * 4', []));
        $this->assertSame(2.5, $this->parser->evaluate('10 / 4', []));
        $this->assertSame(-6.0, $this->parser->evaluate('-2 * 3', []));
        $this->assertSame(5.0, $this->parser->evaluate('+5', []));
        $this->assertSame(1.5, $this->parser->evaluate('3 / 2', []));
    }

    public function test_operator_pada_kurung_bersarang(): void
    {
        $this->assertSame(7.0, $this->parser->evaluate('1 + (2 * (3 - 1)) - 2 + 4', []));
    }

    public function test_fungsi_matematika_yang_diizinkan(): void
    {
        $this->assertEqualsWithDelta(2.35, $this->parser->evaluate('round(2.345, 2)', []), 0.000001);
        $this->assertSame(3.0, $this->parser->evaluate('round(2.5)', []));
        $this->assertSame(2.0, $this->parser->evaluate('min(3, 7, 2)', []));
        $this->assertSame(7.0, $this->parser->evaluate('max(3, 7, 2)', []));
        $this->assertSame(4.0, $this->parser->evaluate('abs(-4)', []));
        $this->assertSame(3.0, $this->parser->evaluate('sqrt(9)', []));
        $this->assertSame(2.0, $this->parser->evaluate('ceil(1.2)', []));
        $this->assertSame(1.0, $this->parser->evaluate('floor(1.8)', []));
    }

    public function test_variabel_dari_input(): void
    {
        $this->assertSame(0.25, $this->parser->evaluate('luas_unit / luas_total', [
            'luas_unit' => 250,
            'luas_total' => 1000,
        ]));
    }

    public function test_dependencies_hanya_mengembalikan_variabel(): void
    {
        $dependencies = $this->parser->dependencies(
            '(a + b) * round(c, 2) + min(d, e)'
        );

        $this->assertSame(['a', 'b', 'c', 'd', 'e'], $dependencies);
    }

    public function test_variabel_tanpa_nilai_ditolak(): void
    {
        $this->expectException(CalculationException::class);
        $this->expectExceptionMessage("Variabel 'a' belum memiliki nilai");

        $this->parser->evaluate('a + 1', []);
    }

    public function test_pembagian_dengan_nol_ditolak(): void
    {
        $this->expectException(CalculationException::class);
        $this->expectExceptionMessage('Pembagian dengan nol');

        $this->parser->evaluate('1 / (2 - 2)', []);
    }

    public function test_sintaks_tidak_valid_ditolak(): void
    {
        foreach (['2 +', '(1 + 2', '1 + * 2', '()', '1,2'] as $expression) {
            try {
                $this->parser->evaluate($expression, []);
                $this->fail("Ekspresi '{$expression}' seharusnya ditolak.");
            } catch (CalculationException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_fungsi_tidak_dikenal_ditolak(): void
    {
        $this->expectException(CalculationException::class);
        $this->expectExceptionMessage("Fungsi 'system' tidak diizinkan");

        $this->parser->evaluate('system(1)', []);
    }

    public function test_karakter_injeksi_ditolak(): void
    {
        $this->expectException(CalculationException::class);
        $this->expectExceptionMessage('tidak diizinkan');

        $this->parser->evaluate('1; phpinfo()', []);
    }

    public function test_akses_properti_ditolak(): void
    {
        $this->expectException(CalculationException::class);

        $this->parser->evaluate('user.password', []);
    }

    public function test_jumlah_argumen_fungsi_divalidasi(): void
    {
        $this->expectException(CalculationException::class);
        $this->expectExceptionMessage("Fungsi 'round' membutuhkan");

        $this->parser->evaluate('round(1, 2, 3)', []);
    }

    public function test_ekspresi_kosong_ditolak(): void
    {
        $this->expectException(CalculationException::class);
        $this->expectExceptionMessage('tidak boleh kosong');

        $this->parser->parse('   ');
    }
}
