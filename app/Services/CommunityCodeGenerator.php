<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CommunityCodeGenerator
{
    public function next(): string
    {
        $sequence = DB::table('community_sequences')
            ->where('id', 1)
            ->lockForUpdate()
            ->first();

        if ($sequence === null) {
            throw new RuntimeException('The community sequence has not been initialized.');
        }

        $number = (int) $sequence->next_number;
        DB::table('community_sequences')
            ->where('id', 1)
            ->update([
                'next_number' => $number + 1,
                'updated_at' => now(),
            ]);

        $prefix = strtoupper(trim((string) Setting::getValue('community_code_prefix', 'JUM')));

        return $prefix.'-'.str_pad((string) $number, 3, '0', STR_PAD_LEFT);
    }
}
