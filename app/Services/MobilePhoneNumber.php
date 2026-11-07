<?php

namespace App\Services;

use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberType;
use libphonenumber\PhoneNumberUtil;
use RuntimeException;

class MobilePhoneNumber
{
    /**
     * @return list<string>
     */
    public function searchAlternates(string $term): array
    {
        if (preg_match('/^0([67][0-9]*)$/D', $term, $match)) {
            return ['255'.$match[1]];
        }

        if (preg_match('/^(?:\+|00)?255([67][0-9]*)$/D', $term, $match)) {
            return ['0'.$match[1]];
        }

        return [];
    }

    public function normalize(string $input): string
    {
        $input = trim($input);

        if (! preg_match('/^\+?[0-9][0-9\s().-]*$/D', $input)) {
            throw new RuntimeException('The mobile phone number is invalid.');
        }

        $number = preg_replace('/[\s().-]/', '', $input);

        if (str_starts_with($number, '00')) {
            $number = '+'.substr($number, 2);
        } elseif ($number[0] !== '+' && strlen($number) > 10) {
            $number = '+'.$number;
        }

        $util = PhoneNumberUtil::getInstance();

        try {
            $parsed = $util->parse($number, 'TZ');
        } catch (NumberParseException) {
            throw new RuntimeException('The mobile phone number is invalid.');
        }

        if (! $util->isValidNumber($parsed) || ! in_array($util->getNumberType($parsed), [
            PhoneNumberType::MOBILE,
            PhoneNumberType::FIXED_LINE_OR_MOBILE,
        ], true)) {
            throw new RuntimeException('The mobile phone number is invalid.');
        }

        return $util->format($parsed, PhoneNumberFormat::E164);
    }
}
