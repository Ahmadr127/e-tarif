<?php

namespace App\Http\Services\Calculation;

use RuntimeException;

/**
 * Error yang aman ditampilkan ke pengguna saat kalkulasi tidak dapat
 * diselesaikan. Pesan tidak boleh memuat stack trace atau detail internal.
 */
class CalculationException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(string $message, public readonly array $context = [])
    {
        parent::__construct($message);
    }
}
