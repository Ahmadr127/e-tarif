<?php

namespace App\Http\Services\Calculation;

/**
 * Menentukan urutan evaluasi formula berdasarkan dependensi antar-formula.
 *
 * Dependensi ke variabel input bukan bagian dari graph ini; variabel tersebut
 * diselesaikan sebelum formula dievaluasi. Yang diurutkan hanya formula yang
 * expression-nya mereferensikan formula lain.
 */
class DependencyResolver
{
    public function __construct(protected FormulaParser $parser) {}

    /**
     * @param  iterable<int, object{code: string, expression: string}>  $formulas
     * @return array<int, string> kode formula dalam urutan topologis
     *
     * @throws CalculationException
     */
    public function resolve(iterable $formulas): array
    {
        $map = [];

        foreach ($formulas as $formula) {
            $code = (string) $formula->code;

            if (array_key_exists($code, $map)) {
                throw new CalculationException("Formula '{$code}' muncul lebih dari sekali.");
            }

            $map[$code] = $formula;
        }

        if ($map === []) {
            throw new CalculationException('Tidak ada formula aktif untuk dihitung.');
        }

        ksort($map, SORT_STRING);

        $dependencies = [];
        foreach ($map as $code => $formula) {
            $dependencies[$code] = [];

            foreach ($this->parser->dependencies((string) $formula->expression) as $dependency) {
                if (array_key_exists($dependency, $map)) {
                    $dependencies[$code][] = $dependency;
                }
            }
        }

        $state = [];
        $path = [];
        $ordered = [];

        /**
         * @param  string  $code
         */
        $visit = function (string $code) use (&$visit, &$state, &$path, &$ordered, $dependencies): void {
            $currentState = $state[$code] ?? 0;

            if ($currentState === 2) {
                return;
            }

            if ($currentState === 1) {
                $cycleStart = array_search($code, $path, true);
                $cycle = array_slice($path, $cycleStart === false ? 0 : $cycleStart);
                $cycle[] = $code;

                throw new CalculationException(
                    'Siklus dependensi formula terdeteksi: '.implode(' -> ', $cycle).'.'
                );
            }

            $state[$code] = 1;
            $path[] = $code;

            foreach ($dependencies[$code] as $dependency) {
                $visit($dependency);
            }

            array_pop($path);
            $state[$code] = 2;
            $ordered[] = $code;
        };

        foreach (array_keys($map) as $code) {
            $visit($code);
        }

        return $ordered;
    }
}
