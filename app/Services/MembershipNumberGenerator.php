<?php

namespace App\Services;

use App\Models\Member;
use App\Models\MembershipSequence;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MembershipNumberGenerator
{
    public function preview(): ?string
    {
        $prefix = $this->configuredPrefix();

        if ($prefix === null) {
            return null;
        }

        $nextValue = MembershipSequence::query()
            ->where('prefix', $prefix)
            ->value('next_value') ?? 1;

        $nextAvailableValue = $this->nextAvailableValue($prefix, (int) $nextValue);

        return $this->formatNumber($prefix, $nextAvailableValue);
    }

    public function generate(): string
    {
        $prefix = $this->configuredPrefix();

        if ($prefix === null) {
            throw ValidationException::withMessages([
                'church_short_name' => __('members.configure_church_short_name'),
            ]);
        }

        return DB::transaction(function () use ($prefix): string {
            DB::table('membership_sequences')->insertOrIgnore([
                'prefix' => $prefix,
                'next_value' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $sequence = MembershipSequence::query()
                ->where('prefix', $prefix)
                ->lockForUpdate()
                ->firstOrFail();

            $issuedValue = $this->nextAvailableValue($prefix, (int) $sequence->next_value);

            $sequence->next_value = $issuedValue + 1;
            $sequence->save();

            return $this->formatNumber($prefix, $issuedValue);
        });
    }

    private function configuredPrefix(): ?string
    {
        $prefix = Str::upper(trim((string) Setting::getValue('church_short_name', '')));

        if ($prefix === '') {
            return null;
        }

        if (! preg_match('/^[A-Z0-9]+(?:-[A-Z0-9]+)*$/', $prefix)) {
            throw ValidationException::withMessages([
                'church_short_name' => __('members.configure_church_short_name'),
            ]);
        }

        return $prefix;
    }

    private function nextAvailableValue(string $prefix, int $nextValue): int
    {
        do {
            if (! Member::withTrashed()->where('membership_number', $this->formatNumber($prefix, $nextValue))->exists()) {
                return $nextValue;
            }

            $nextValue++;
        } while (true);
    }

    private function formatNumber(string $prefix, int $sequence): string
    {
        return sprintf('KKKT-%s-%04d', $prefix, $sequence);
    }
}
