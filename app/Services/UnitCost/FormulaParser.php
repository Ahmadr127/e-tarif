<?php

namespace App\Services\UnitCost;

/**
 * Parser formula free-text yang aman.
 *
 * Formula adalah data, bukan kode PHP. Hanya angka, referensi {kode_variabel},
 * operator + - * /, tanda kurung, koma, dan fungsi matematika yang diizinkan.
 * Tidak ada eval(), include, akses properti, atau pemanggilan method.
 */
class FormulaParser
{
    public const MAX_EXPRESSION_LENGTH = 2000;

    public const MAX_DEPTH = 50;

    /**
     * @var array<string, array{0: int, 1: int}>
     */
    private const FUNCTIONS = [
        'abs' => [1, 1],
        'ceil' => [1, 1],
        'floor' => [1, 1],
        'sqrt' => [1, 1],
        'round' => [1, 2],
        'min' => [1, PHP_INT_MAX],
        'max' => [1, PHP_INT_MAX],
    ];

    /**
     * @var array<int, array{type: string, value: string, position: int}>
     */
    private array $tokens = [];

    private int $position = 0;

    private int $depth = 0;

    /**
     * @var array<string, array<string, mixed>>
     */
    private array $astCache = [];

    /**
     * @param  array<string, float|int>  $variables
     *
     * @throws FormulaException
     */
    public function evaluate(string $expression, array $variables): float
    {
        return $this->evaluateNode($this->parse($expression), $variables);
    }

    /**
     * @return array<int, string>
     *
     * @throws FormulaException
     */
    public function dependencies(string $expression): array
    {
        $names = [];
        $this->collectVariables($this->parse($expression), $names);
        $names = array_values(array_unique($names));
        sort($names, SORT_STRING);

        return $names;
    }

    /**
     * @return array<string, mixed>
     *
     * @throws FormulaException
     */
    public function parse(string $expression): array
    {
        $expression = trim($expression);

        if ($expression === '') {
            throw new FormulaException('Ekspresi formula tidak boleh kosong.');
        }

        if (mb_strlen($expression) > self::MAX_EXPRESSION_LENGTH) {
            throw new FormulaException(
                'Ekspresi formula terlalu panjang (maksimal '.self::MAX_EXPRESSION_LENGTH.' karakter).'
            );
        }

        if (array_key_exists($expression, $this->astCache)) {
            return $this->astCache[$expression];
        }

        $this->tokens = $this->tokenize($expression);
        $this->position = 0;
        $this->depth = 0;

        $node = $this->parseExpression();

        if ($this->current() !== null) {
            $token = $this->current();
            throw new FormulaException(
                "Token tidak terduga '{$token['value']}' pada posisi {$token['position']}."
            );
        }

        return $this->astCache[$expression] = $node;
    }

    /**
     * @return array<int, array{type: string, value: string, position: int}>
     *
     * @throws FormulaException
     */
    private function tokenize(string $expression): array
    {
        $tokens = [];
        $length = strlen($expression);
        $index = 0;

        while ($index < $length) {
            $char = $expression[$index];

            if (ctype_space($char)) {
                $index++;

                continue;
            }

            if ($char === '{') {
                $closing = strpos($expression, '}', $index + 1);

                if ($closing === false) {
                    throw new FormulaException(
                        "Tanda kurung kurawal tutup '}' tidak ditemukan pada posisi {$index}."
                    );
                }

                $code = trim(substr($expression, $index + 1, $closing - $index - 1));

                if ($code === '') {
                    throw new FormulaException(
                        "Referensi variabel pada posisi {$index} tidak boleh kosong."
                    );
                }

                if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $code) !== 1) {
                    throw new FormulaException(
                        "Referensi variabel '{$code}' tidak valid. Gunakan huruf, angka, dan garis bawah."
                    );
                }

                $tokens[] = ['type' => 'variable_ref', 'value' => $code, 'position' => $index];
                $index = $closing + 1;

                continue;
            }

            if ($char === '}') {
                throw new FormulaException(
                    "Tanda kurung kurawal tutup '}' tidak memiliki pembuka pada posisi {$index}."
                );
            }

            if (ctype_digit($char) || ($char === '.' && $index + 1 < $length && ctype_digit($expression[$index + 1]))) {
                if (preg_match('/\G(?:\d+(?:\.\d*)?|\.\d+)/', $expression, $matches, 0, $index) !== 1) {
                    throw new FormulaException("Angka tidak valid pada posisi {$index}.");
                }

                $tokens[] = ['type' => 'number', 'value' => $matches[0], 'position' => $index];
                $index += strlen($matches[0]);

                continue;
            }

            if (ctype_alpha($char) || $char === '_') {
                if (preg_match('/\G[A-Za-z_][A-Za-z0-9_]*/', $expression, $matches, 0, $index) !== 1) {
                    throw new FormulaException("Nama variabel tidak valid pada posisi {$index}.");
                }

                $tokens[] = ['type' => 'identifier', 'value' => $matches[0], 'position' => $index];
                $index += strlen($matches[0]);

                continue;
            }

            if (str_contains('+-*/', $char)) {
                $tokens[] = ['type' => 'operator', 'value' => $char, 'position' => $index];
                $index++;

                continue;
            }

            if ($char === '(' || $char === ')' || $char === ',') {
                $type = match ($char) {
                    '(' => 'lparen',
                    ')' => 'rparen',
                    ',' => 'comma',
                };

                $tokens[] = ['type' => $type, 'value' => $char, 'position' => $index];
                $index++;

                continue;
            }

            throw new FormulaException(
                "Karakter '{$char}' tidak diizinkan dalam formula pada posisi {$index}."
            );
        }

        return $tokens;
    }

    /**
     * @return array<string, mixed>
     */
    private function parseExpression(): array
    {
        $node = $this->parseTerm();

        while (($token = $this->current()) !== null
            && $token['type'] === 'operator'
            && ($token['value'] === '+' || $token['value'] === '-')) {
            $this->position++;
            $node = [
                'type' => 'binary',
                'operator' => $token['value'],
                'left' => $node,
                'right' => $this->parseTerm(),
            ];
        }

        return $node;
    }

    /**
     * @return array<string, mixed>
     */
    private function parseTerm(): array
    {
        $node = $this->parseUnary();

        while (($token = $this->current()) !== null
            && $token['type'] === 'operator'
            && ($token['value'] === '*' || $token['value'] === '/')) {
            $this->position++;
            $node = [
                'type' => 'binary',
                'operator' => $token['value'],
                'left' => $node,
                'right' => $this->parseUnary(),
            ];
        }

        return $node;
    }

    /**
     * @return array<string, mixed>
     */
    private function parseUnary(): array
    {
        $token = $this->current();

        if ($token !== null
            && $token['type'] === 'operator'
            && ($token['value'] === '+' || $token['value'] === '-')) {
            $this->position++;

            return [
                'type' => 'unary',
                'operator' => $token['value'],
                'operand' => $this->parseUnary(),
            ];
        }

        return $this->parsePrimary();
    }

    /**
     * @return array<string, mixed>
     */
    private function parsePrimary(): array
    {
        $token = $this->current();

        if ($token === null) {
            throw new FormulaException('Formula berakhir sebelum ekspresi selesai.');
        }

        if ($token['type'] === 'number') {
            $this->position++;

            return ['type' => 'number', 'value' => (float) $token['value']];
        }

        if ($token['type'] === 'variable_ref') {
            $this->position++;

            return ['type' => 'variable', 'name' => $token['value']];
        }

        if ($token['type'] === 'identifier') {
            $this->position++;

            $next = $this->current();
            if ($next !== null && $next['type'] === 'lparen') {
                return $this->parseFunctionCall($token['value']);
            }

            return ['type' => 'variable', 'name' => $token['value']];
        }

        if ($token['type'] === 'lparen') {
            $this->position++;
            $this->enterGroup();

            $node = $this->parseExpression();

            $closing = $this->current();
            if ($closing === null || $closing['type'] !== 'rparen') {
                throw new FormulaException('Tanda kurung tutup ")" tidak ditemukan.');
            }

            $this->position++;
            $this->depth--;

            return $node;
        }

        throw new FormulaException(
            "Token '{$token['value']}' tidak dapat dipakai sebagai awal ekspresi."
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function parseFunctionCall(string $name): array
    {
        if (! array_key_exists($name, self::FUNCTIONS)) {
            throw new FormulaException("Fungsi '{$name}' tidak diizinkan.");
        }

        $this->position++;
        $this->enterGroup();

        $arguments = [];

        if (($token = $this->current()) !== null && $token['type'] !== 'rparen') {
            do {
                $arguments[] = $this->parseExpression();

                $token = $this->current();
                if ($token !== null && $token['type'] === 'comma') {
                    $this->position++;

                    continue;
                }

                break;
            } while (true);
        }

        $closing = $this->current();
        if ($closing === null || $closing['type'] !== 'rparen') {
            throw new FormulaException("Tanda kurung tutup untuk fungsi '{$name}' tidak ditemukan.");
        }

        $this->position++;
        $this->depth--;

        [$minimum, $maximum] = self::FUNCTIONS[$name];
        $count = count($arguments);

        if ($count < $minimum || $count > $maximum) {
            $expected = $minimum === $maximum
                ? (string) $minimum
                : ($maximum === PHP_INT_MAX ? "minimal {$minimum}" : "{$minimum}-{$maximum}");

            throw new FormulaException(
                "Fungsi '{$name}' membutuhkan {$expected} argumen, diberikan {$count}."
            );
        }

        return ['type' => 'function', 'name' => $name, 'arguments' => $arguments];
    }

    private function enterGroup(): void
    {
        $this->depth++;

        if ($this->depth > self::MAX_DEPTH) {
            throw new FormulaException(
                'Formula terlalu dalam (maksimal '.self::MAX_DEPTH.' tingkat tanda kurung).'
            );
        }
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, float|int>  $variables
     */
    private function evaluateNode(array $node, array $variables): float
    {
        return match ($node['type']) {
            'number' => (float) $node['value'],
            'variable' => $this->resolveVariable($node['name'], $variables),
            'unary' => $this->evaluateUnary($node, $variables),
            'binary' => $this->evaluateBinary($node, $variables),
            'function' => $this->evaluateFunction($node, $variables),
            default => throw new FormulaException('Node formula tidak dikenal.'),
        };
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, float|int>  $variables
     */
    private function evaluateUnary(array $node, array $variables): float
    {
        $value = $this->evaluateNode($node['operand'], $variables);

        return $node['operator'] === '-' ? -$value : $value;
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, float|int>  $variables
     */
    private function evaluateBinary(array $node, array $variables): float
    {
        $left = $this->evaluateNode($node['left'], $variables);
        $right = $this->evaluateNode($node['right'], $variables);

        $value = match ($node['operator']) {
            '+' => $left + $right,
            '-' => $left - $right,
            '*' => $left * $right,
            '/' => $this->divide($left, $right),
            default => throw new FormulaException('Operator formula tidak dikenal.'),
        };

        return $this->ensureFinite($value);
    }

    private function divide(float $left, float $right): float
    {
        if ($right == 0.0) {
            throw new FormulaException('Pembagian dengan nol tidak diizinkan dalam formula.');
        }

        return $left / $right;
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, float|int>  $variables
     */
    private function evaluateFunction(array $node, array $variables): float
    {
        $arguments = [];

        foreach ($node['arguments'] as $argument) {
            $arguments[] = $this->evaluateNode($argument, $variables);
        }

        $value = match ($node['name']) {
            'abs' => abs($arguments[0]),
            'ceil' => ceil($arguments[0]),
            'floor' => floor($arguments[0]),
            'sqrt' => sqrt($arguments[0]),
            'round' => round($arguments[0], (int) ($arguments[1] ?? 0)),
            'min' => min($arguments),
            'max' => max($arguments),
            default => throw new FormulaException("Fungsi '{$node['name']}' tidak diizinkan."),
        };

        return $this->ensureFinite((float) $value);
    }

    /**
     * @param  array<string, float|int>  $variables
     */
    private function resolveVariable(string $name, array $variables): float
    {
        if (! array_key_exists($name, $variables)) {
            throw new FormulaException("Variabel '{$name}' belum memiliki nilai.");
        }

        $value = $variables[$name];

        if (is_bool($value) || ! is_numeric($value)) {
            throw new FormulaException("Variabel '{$name}' bukan angka yang valid.");
        }

        return $this->ensureFinite((float) $value);
    }

    private function ensureFinite(float $value): float
    {
        if (! is_finite($value)) {
            throw new FormulaException('Hasil formula bukan angka yang valid (NaN/Infinity).');
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<int, string>  $names
     */
    private function collectVariables(array $node, array &$names): void
    {
        if ($node['type'] === 'variable') {
            $names[] = $node['name'];

            return;
        }

        if ($node['type'] === 'unary') {
            $this->collectVariables($node['operand'], $names);

            return;
        }

        if ($node['type'] === 'binary') {
            $this->collectVariables($node['left'], $names);
            $this->collectVariables($node['right'], $names);

            return;
        }

        if ($node['type'] === 'function') {
            foreach ($node['arguments'] as $argument) {
                $this->collectVariables($argument, $names);
            }
        }
    }

    /**
     * @return array{type: string, value: string, position: int}|null
     */
    private function current(): ?array
    {
        return $this->tokens[$this->position] ?? null;
    }
}
