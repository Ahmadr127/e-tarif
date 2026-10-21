<?php

namespace App\Services\UnitCost;

use App\Models\OrganizationUnit;
use App\Models\UnitCostParameterEntry;
use App\Models\UnitCostPeriod;

class EvaluationResult
{
    /**
     * @param  array<int, array<string, mixed>>  $parameters
     * @param  array<string, float>  $values
     * @param  array{total: int, valid: int, incomplete: int, invalid: int}  $summary
     */
    public function __construct(
        public readonly UnitCostPeriod $period,
        public readonly ?OrganizationUnit $unit,
        public readonly array $parameters,
        public readonly array $values,
        public readonly array $summary,
    ) {}

    /**
     * @return array<string, mixed>|null
     */
    public function byVariable(string $variable): ?array
    {
        foreach ($this->parameters as $parameter) {
            if (($parameter['parameter']->formulaKey() ?? null) === $variable) {
                return $parameter;
            }
        }

        return null;
    }

    /**
     * Alias lama: kode internal dan variable biasanya sama.
     *
     * @return array<string, mixed>|null
     */
    public function byCode(string $code): ?array
    {
        return $this->byVariable($code);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function forComponent(?int $componentId): array
    {
        return array_values(array_filter(
            $this->parameters,
            static fn (array $row): bool => ($row['parameter']->component_id ?? null) === $componentId
        ));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function invalidOrIncomplete(): array
    {
        return array_values(array_filter(
            $this->parameters,
            static fn (array $row): bool => ($row['status'] ?? null) !== UnitCostParameterEntry::STATUS_VALID
        ));
    }
}
