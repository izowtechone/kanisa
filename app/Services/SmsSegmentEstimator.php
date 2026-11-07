<?php

namespace App\Services;

class SmsSegmentEstimator
{
    public function count(string $message): int
    {
        return $this->estimate($message)['segments'];
    }

    /**
     * @return array{encoding: string, characters: int, units: int, segments: int}
     */
    public function estimate(string $message): array
    {
        $characters = mb_str_split($message);
        $characterCount = count($characters);

        if ($characterCount === 0) {
            return ['encoding' => 'GSM-7', 'characters' => 0, 'units' => 0, 'segments' => 0];
        }

        $gsmUnits = 0;
        $gsmBasic = ' @£$¥èéùìòÇ'."\n".'Øø'."\r".'ÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ!"#¤%&\'()*+,-./0123456789:;<=>?¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà';
        $gsmExtended = '^{}\\[~]|€'."\f";

        foreach ($characters as $character) {
            if (str_contains($gsmBasic, $character)) {
                $gsmUnits++;

                continue;
            }

            if (str_contains($gsmExtended, $character)) {
                $gsmUnits += 2;

                continue;
            }

            return [
                'encoding' => 'Unicode',
                'characters' => $characterCount,
                'units' => $characterCount,
                'segments' => $characterCount <= 70 ? 1 : (int) ceil($characterCount / 67),
            ];
        }

        return [
            'encoding' => 'GSM-7',
            'characters' => $characterCount,
            'units' => $gsmUnits,
            'segments' => $gsmUnits <= 160 ? 1 : (int) ceil($gsmUnits / 153),
        ];
    }
}
