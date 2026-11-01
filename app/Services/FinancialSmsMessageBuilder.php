<?php

namespace App\Services;

use App\Models\FinancialSetting;
use App\Models\FinancialTransaction;
use App\Models\Setting;

class FinancialSmsMessageBuilder
{
    /**
     * @var array<string, string>
     */
    private const array PLACEHOLDERS = [
        'amount' => '1,250.00',
        'receipt_number' => 'KKKT-RCPT-2026-000001',
        'church_name' => 'KKKT',
        'currency' => 'TZS',
    ];

    public function forTransaction(FinancialTransaction $transaction): string
    {
        $language = FinancialSetting::getValue(
            'beem_language',
            (string) Setting::getValue('system_language', config('app.locale', 'sw')),
        );
        $isContribution = $transaction->type === 'contribution';
        $template = FinancialSetting::getValue(
            'beem_template_'.($isContribution ? 'contribution' : 'offering').'_'.$language,
            __('finance.sms_template_'.($isContribution ? 'contribution' : 'offering'), [], $language),
        );

        return $this->render($template, [
            'amount' => $this->formatAmount((string) $transaction->amount),
            'receipt_number' => $transaction->receipt_number,
            'church_name' => (string) Setting::getValue('church_name', config('app.name', 'KKKT CMS')),
            'currency' => $transaction->currency,
        ]);
    }

    public function preview(string $type, string $language): string
    {
        $isContribution = $type === 'contribution';
        $template = FinancialSetting::getValue(
            'beem_template_'.($isContribution ? 'contribution' : 'offering').'_'.$language,
            __('finance.sms_template_'.($isContribution ? 'contribution' : 'offering'), [], $language),
        );

        return $this->render($template, self::PLACEHOLDERS);
    }

    /**
     * @param  array<string, string>  $values
     */
    private function render(string $template, array $values): string
    {
        $substitutions = [];

        foreach ($values as $placeholder => $value) {
            $safeValue = strip_tags($value);
            $safeValue = preg_replace('/[\r\n\t]+/', ' ', $safeValue) ?? '';
            $substitutions['{'.$placeholder.'}'] = $safeValue;
        }

        return trim(strtr($template, $substitutions));
    }

    private function formatAmount(string $amount): string
    {
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '00');
        $whole = preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', $whole) ?? $whole;

        return $whole.'.'.str_pad($fraction, 2, '0');
    }
}
