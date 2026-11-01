<?php

namespace App\Services;

class FinancialAmountFormatter
{
    public function format(string $amount, string $currency): string
    {
        return $currency.' '.$this->formatNumber($amount);
    }

    public function formatNumber(string $amount): string
    {
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '00');
        $whole = preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', $whole);

        return $whole.'.'.str_pad($fraction, 2, '0');
    }
}
