<?php

namespace App\Rules;

use App\Services\MobilePhoneNumber;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;
use RuntimeException;

class MobilePhone implements ValidationRule
{
    public function __construct(private readonly ?string $unchangedPhone = null) {}

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === $this->unchangedPhone) {
            return;
        }

        try {
            app(MobilePhoneNumber::class)->normalize($value);
        } catch (RuntimeException) {
            $fail(__('members.phone_invalid'));
        }
    }
}
