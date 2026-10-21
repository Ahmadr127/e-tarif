<?php

namespace App\Services\UnitCost;

use RuntimeException;

class FormulaException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(string $message, public readonly array $context = [])
    {
        parent::__construct($message);
    }
}
