<?php

namespace App\Http\Services\Calculation;

/**
 * Menyelesaikan nilai variabel dari input run, nilai tersimpan, dan default.
 *
 * Variabel turunan (source_type = derived) tidak boleh diisi manual; nilainya
 * dihasilkan oleh formula dengan kode yang sama.
 */
class VariableResolver
{
    /**
     * @param  iterable<int, object>  $variables
     * @param  array<string, mixed>  $inputs
     * @param  array<int, string>|null  $requiredCodes
     * @param  array<string, mixed>  $storedValues  format: code => float|['value'=>float,'source_name'=>?string]
     * @return array<string, float>
     *
     * @throws CalculationException
     */
    public function resolve(
        iterable $variables,
        array $inputs,
        ?array $requiredCodes = null,
        array $storedValues = [],
    ): array {
        return $this->resolveDetailed($variables, $inputs, $requiredCodes, $storedValues)['values'];
    }

    /**
     * @param  iterable<int, object>  $variables
     * @param  array<string, mixed>  $inputs
     * @param  array<int, string>|null  $requiredCodes
     * @param  array<string, mixed>  $storedValues
     * @return array{values: array<string, float>, sources: array<string, array{source: string, source_name: string|null}>}
     *
     * @throws CalculationException
     */
    public function resolveDetailed(
        iterable $variables,
        array $inputs,
        ?array $requiredCodes = null,
        array $storedValues = [],
    ): array {
        $active = [];

        foreach ($variables as $variable) {
            if (! (bool) ($variable->is_active ?? true)) {
                continue;
            }

            $active[(string) $variable->code] = $variable;
        }

        $unknown = array_values(array_diff(array_keys($inputs), array_keys($active)));
        if ($unknown !== []) {
            sort($unknown, SORT_STRING);

            throw new CalculationException(
                'Variabel input tidak dikenal: '.implode(', ', $unknown).'.'
            );
        }

        $required = $requiredCodes === null ? null : array_fill_keys($requiredCodes, true);
        $values = [];
        $sources = [];
        $errors = [];

        foreach ($active as $code => $variable) {
            $provided = array_key_exists($code, $inputs)
                && $inputs[$code] !== null
                && $inputs[$code] !== '';

            $isRequired = $required === null
                ? (bool) ($variable->is_required ?? false)
                : array_key_exists($code, $required);

            if (($variable->source_type ?? null) === 'derived') {
                if ($provided) {
                    $errors[] = "Variabel turunan '{$code}' tidak boleh diisi manual.";
                }

                continue;
            }

            if ($provided) {
                $value = $this->normalizeValue($variable, $inputs[$code], $code, $errors);

                if ($value !== null) {
                    $values[$code] = $value;
                    $sources[$code] = ['source' => 'input', 'source_name' => 'Input run'];
                }

                continue;
            }

            $stored = $storedValues[$code] ?? null;

            if ($stored !== null) {
                $rawValue = is_array($stored) ? ($stored['value'] ?? null) : $stored;
                $value = $this->normalizeValue($variable, $rawValue, $code, $errors);

                if ($value !== null) {
                    $values[$code] = $value;
                    $sources[$code] = [
                        'source' => 'stored',
                        'source_name' => is_array($stored)
                            ? (($stored['source_name'] ?? null) ?: 'Nilai tersimpan')
                            : 'Nilai tersimpan',
                    ];
                }

                continue;
            }

            if ($variable->default_value !== null) {
                $value = $this->normalizeValue($variable, $variable->default_value, $code, $errors);

                if ($value !== null) {
                    $values[$code] = $value;
                    $sources[$code] = ['source' => 'default', 'source_name' => 'Nilai default'];
                }

                continue;
            }

            if ($isRequired) {
                $errors[] = "Variabel '{$code}' wajib diisi tetapi belum memiliki nilai.";
            }
        }

        if ($errors !== []) {
            throw new CalculationException(implode(' ', $errors), ['errors' => $errors]);
        }

        return ['values' => $values, 'sources' => $sources];
    }

    /**
     * Validasi dan normalisasi satu nilai variabel.
     *
     * @throws CalculationException
     */
    public function normalize(object $variable, mixed $rawValue): float
    {
        $errors = [];
        $code = (string) $variable->code;
        $value = $this->normalizeValue($variable, $rawValue, $code, $errors);

        if ($errors !== [] || $value === null) {
            throw new CalculationException(implode(' ', $errors), ['errors' => $errors]);
        }

        return $value;
    }

    /**
     * @param  array<int, string>  $errors
     */
    private function normalizeValue(object $variable, mixed $rawValue, string $code, array &$errors): ?float
    {
        $valueType = (string) ($variable->value_type ?? 'decimal');

        if ($valueType === 'string') {
            $errors[] = "Variabel '{$code}' bertipe teks dan tidak dapat dipakai dalam formula aritmetika.";

            return null;
        }

        if ($valueType === 'boolean' && is_string($rawValue)) {
            $normalized = mb_strtolower(trim($rawValue));

            if (in_array($normalized, ['true', 'ya', '1'], true)) {
                $rawValue = 1;
            } elseif (in_array($normalized, ['false', 'tidak', '0'], true)) {
                $rawValue = 0;
            }
        }

        if (is_bool($rawValue)) {
            $value = $rawValue ? 1.0 : 0.0;
        } elseif (is_int($rawValue) || is_float($rawValue)) {
            $value = (float) $rawValue;
        } elseif (is_string($rawValue) && is_numeric(trim($rawValue))) {
            $value = (float) trim($rawValue);
        } else {
            $errors[] = "Variabel '{$code}' harus berupa angka.";

            return null;
        }

        if (! is_finite($value)) {
            $errors[] = "Variabel '{$code}' bukan angka yang valid.";

            return null;
        }

        if ($valueType === 'integer' && floor($value) !== $value) {
            $errors[] = "Variabel '{$code}' harus berupa bilangan bulat.";

            return null;
        }

        if ($valueType === 'boolean' && ! in_array($value, [0.0, 1.0], true)) {
            $errors[] = "Variabel '{$code}' harus bernilai 0 atau 1.";

            return null;
        }

        if ($this->isRatioVariable($variable) && ($value < 0.0 || $value > 1.0)) {
            $errors[] = "Variabel '{$code}' harus berada pada rentang 0 sampai 1.";
        }

        if ($this->isPercentageVariable($variable) && ($value < 0.0 || $value > 100.0)) {
            $errors[] = "Variabel '{$code}' harus berada pada rentang 0 sampai 100.";
        }

        return $value;
    }

    private function isRatioVariable(object $variable): bool
    {
        $unit = mb_strtolower(trim((string) ($variable->unit ?? '')));

        return str_starts_with($unit, 'rasio');
    }

    private function isPercentageVariable(object $variable): bool
    {
        $unit = mb_strtolower(trim((string) ($variable->unit ?? '')));

        return str_starts_with($unit, 'persen') || $unit === '%';
    }
}
