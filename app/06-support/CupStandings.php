<?php

declare(strict_types=1);

namespace App\Support;

final class CupStandings
{
    public const MODE_TOTAL_SCORE = 'total_score';

    public const MODE_PLACEMENT_POINTS = 'placement_points';

    public const MAX_PLACEMENT_PLACE = 25;

    public const DEFAULT_COUNT_BEST = 6;

    /** @return array<int, float> */
    public static function defaultPlacementPoints(): array
    {
        return [
            1 => 25.0, 2 => 18.0, 3 => 15.0, 4 => 12.0, 5 => 10.0,
            6 => 8.0, 7 => 6.0, 8 => 4.0, 9 => 2.0, 10 => 1.0,
        ];
    }

    public static function normalizeMode(string $mode): string
    {
        $mode = strtolower(trim($mode));

        return $mode === self::MODE_PLACEMENT_POINTS
            ? self::MODE_PLACEMENT_POINTS
            : self::MODE_TOTAL_SCORE;
    }
}
