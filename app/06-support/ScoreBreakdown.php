<?php

declare(strict_types=1);

namespace App\Support;

/** Hjelper for å lese poeng fra jaktfelt score_breakdown JSON. */
final class ScoreBreakdown
{
    /**
     * @return array{score: ?float, hits: ?int, inner_hits: ?int}
     */
    public static function totals(mixed $scoreBreakdown): array
    {
        $decoded = self::decode($scoreBreakdown);
        if ($decoded === null) {
            return ['score' => null, 'hits' => null, 'inner_hits' => null];
        }

        $holds = $decoded['holds_normalized'] ?? $decoded['reported_holds'] ?? null;
        if (!is_array($holds) || $holds === []) {
            if (isset($decoded['total_score'])) {
                return [
                    'score' => (float) $decoded['total_score'],
                    'hits' => isset($decoded['total_hits']) ? (int) $decoded['total_hits'] : null,
                    'inner_hits' => isset($decoded['total_inner_hits']) ? (int) $decoded['total_inner_hits'] : null,
                ];
            }

            return ['score' => null, 'hits' => null, 'inner_hits' => null];
        }

        $score = 0.0;
        $hits = 0;
        $inner = 0;
        foreach (array_slice($holds, 0, 6) as $hold) {
            if (!is_array($hold)) {
                continue;
            }
            $score += (float) ($hold['poeng'] ?? 0);
            $hits += (int) ($hold['treff'] ?? 0);
            $inner += (int) ($hold['innertreff'] ?? 0);
        }

        return ['score' => $score, 'hits' => $hits, 'inner_hits' => $inner];
    }

    /** @return array<string, mixed>|null */
    private static function decode(mixed $scoreBreakdown): ?array
    {
        if ($scoreBreakdown === null || $scoreBreakdown === '') {
            return null;
        }
        if (is_array($scoreBreakdown)) {
            return $scoreBreakdown;
        }
        if (!is_string($scoreBreakdown)) {
            return null;
        }
        $decoded = json_decode($scoreBreakdown, true);

        return is_array($decoded) ? $decoded : null;
    }
}
