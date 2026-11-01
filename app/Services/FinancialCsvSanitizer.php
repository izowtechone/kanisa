<?php

namespace App\Services;

class FinancialCsvSanitizer
{
    public function escape(?string $value): string
    {
        $value ??= '';

        return preg_match('/^[=+\-@\t\r]/', $value) === 1
            ? "'".$value
            : $value;
    }
}
