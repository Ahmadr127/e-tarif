<?php

namespace Tests\Unit\UnitCost;

use App\Services\UnitCost\FormulaException;
use App\Services\UnitCost\FormulaParser;
use PHPUnit\Framework\TestCase;

class FormulaParserTest extends TestCase
{
    private FormulaParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new FormulaParser;
    }

    public function test_it_evaluates_arithmetic_and_references(): void
    {
        $result = $this->parser->evaluate(
            '({luas_tindakan} / {luas_total}) * 100 + 2',
            ['luas_tindakan' => 25, 'luas_total' => 100]
        );

        $this->assertSame(27.0, $result);
    }

    public function test_it_supports_allowlisted_functions(): void
    {
        $this->assertSame(5.0, $this->parser->evaluate('abs(-5)', []));
        $this->assertSame(3.0, $this->parser->evaluate('ceil(2.1)', []));
        $this->assertSame(2.0, $this->parser->evaluate('floor(2.9)', []));
        $this->assertSame(4.0, $this->parser->evaluate('sqrt(16)', []));
        $this->assertSame(1.23, $this->parser->evaluate('round(1.234, 2)', []));
        $this->assertSame(1.0, $this->parser->evaluate('min(3, 1, 2)', []));
        $this->assertSame(3.0, $this->parser->evaluate('max(3, 1, 2)', []));
    }

    public function test_it_returns_sorted_unique_dependencies(): void
    {
        $dependencies = $this->parser->dependencies('{b} + {a} * {b} + max({c}, 1)');

        $this->assertSame(['a', 'b', 'c'], $dependencies);
    }

    public function test_it_rejects_unknown_function(): void
    {
        $this->expectException(FormulaException::class);
        $this->expectExceptionMessage("Fungsi 'system' tidak diizinkan.");

        $this->parser->evaluate('system(1)', []);
    }

    public function test_it_rejects_unsupported_characters(): void
    {
        $this->expectException(FormulaException::class);

        $this->parser->evaluate('{a} & {b}', ['a' => 1, 'b' => 2]);
    }

    public function test_it_rejects_division_by_zero(): void
    {
        $this->expectException(FormulaException::class);
        $this->expectExceptionMessage('Pembagian dengan nol');

        $this->parser->evaluate('{a} / 0', ['a' => 10]);
    }

    public function test_it_rejects_non_finite_results(): void
    {
        $this->expectException(FormulaException::class);

        $this->parser->evaluate('sqrt(-1)', []);
    }

    public function test_it_rejects_empty_expression(): void
    {
        $this->expectException(FormulaException::class);
        $this->expectExceptionMessage('tidak boleh kosong');

        $this->parser->evaluate('   ', []);
    }

    public function test_it_rejects_missing_variable_value(): void
    {
        $this->expectException(FormulaException::class);
        $this->expectExceptionMessage("Variabel 'a' belum memiliki nilai.");

        $this->parser->evaluate('{a} + 1', []);
    }
}
