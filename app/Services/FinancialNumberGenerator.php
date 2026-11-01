<?php

namespace App\Services;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FinancialNumberGenerator
{
    public function next(string $sequenceName, string $date, string $prefix): string
    {
        $year = (int) substr($date, 0, 4);
        $now = now();

        DB::table('financial_number_sequences')->insertOrIgnore([
            'sequence_name' => $sequenceName,
            'year' => $year,
            'next_value' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $sequence = DB::table('financial_number_sequences')
            ->where('sequence_name', $sequenceName)
            ->where('year', $year)
            ->lockForUpdate()
            ->first();

        if ($sequence === null) {
            throw (new ModelNotFoundException)->setModel('financial_number_sequences');
        }

        $number = (int) $sequence->next_value;

        DB::table('financial_number_sequences')
            ->where('id', $sequence->id)
            ->update([
                'next_value' => $number + 1,
                'updated_at' => $now,
            ]);

        return sprintf('%s-%d-%06d', Str::upper($prefix), $year, $number);
    }
}
