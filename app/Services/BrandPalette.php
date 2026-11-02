<?php

namespace App\Services;

class BrandPalette
{
    public const DEFAULT_PRIMARY = '#3b82f6';

    public const DEFAULT_SECONDARY = '#64748b';

    /** @var array<int, float> Positive values mix towards white, negative values towards black. */
    private const SHADES = [
        50 => 0.92,
        100 => 0.84,
        200 => 0.68,
        300 => 0.5,
        400 => 0.25,
        500 => 0.0,
        600 => -0.14,
        700 => -0.3,
        800 => -0.46,
        900 => -0.6,
        950 => -0.78,
    ];

    public static function isValidHex(?string $color): bool
    {
        return is_string($color) && preg_match('/^#[0-9a-fA-F]{6}$/', $color) === 1;
    }

    /**
     * @return array<int, string> Space-separated RGB triplets keyed by Tailwind shade.
     */
    public function shades(?string $hex): array
    {
        $hex = self::isValidHex($hex) ? $hex : self::DEFAULT_PRIMARY;
        [$red, $green, $blue] = sscanf($hex, '#%02x%02x%02x');
        $shades = [];

        foreach (self::SHADES as $shade => $mix) {
            $target = $mix >= 0 ? 255 : 0;
            $weight = abs($mix);
            $shades[$shade] = implode(' ', array_map(
                fn (int $channel): int => (int) round($channel + ($target - $channel) * $weight),
                [$red, $green, $blue],
            ));
        }

        return $shades;
    }

    public function css(?string $primary): string
    {
        $declarations = '';

        foreach ($this->shades($primary) as $shade => $triplet) {
            $declarations .= "--brand-{$shade}: {$triplet};";
        }

        return ':root{'.$declarations.'}';
    }
}
