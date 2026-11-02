<?php

namespace App\Services;

class SmsSegmentEstimator
{
    public function count(string $message): int
    {
        if ($message === '') {
            return 0;
        }

        $characters = mb_str_split($message);
        $gsmUnits = 0;
        $gsmBasic = '@£$¥èéùìòÇ'."\n".'Øø'."\r".'ÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ!"#¤%&\'()*+,-./0123456789:;<=>?¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà';
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

            $unicodeLength = count($characters);

            return $unicodeLength <= 70 ? 1 : (int) ceil($unicodeLength / 67);
        }

        return $gsmUnits <= 160 ? 1 : (int) ceil($gsmUnits / 153);
    }
}
