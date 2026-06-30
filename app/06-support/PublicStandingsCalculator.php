<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Forenklet sammenlagt (total_score) for public API.
 */
final class PublicStandingsCalculator
{
    /**
     * @param array<string, mixed> $season
     * @param list<array<string, mixed>> $competitions
     * @param callable(int): list<array<string, mixed>> $resultsLoader
     * @return array{
     *   mode: string,
     *   cup_standings_count_best: int,
     *   class_groups: list<array{class_id: int, label: string, sort: int, rows: list<array<string, mixed>>}>
     * }
     */
    public static function computeTotalScore(array $season, array $competitions, callable $resultsLoader): array
    {
        $countBest = max(0, (int) ($season['cup_standings_count_best'] ?? CupStandings::DEFAULT_COUNT_BEST));
        $cupIds = $season['cup_standings_competition_ids'] ?? null;
        $filtered = self::filterCompetitions($competitions, $cupIds);

        /** @var array<int, array<string, mixed>> $byParticipant */
        $byParticipant = [];

        foreach ($filtered as $comp) {
            if (empty($comp['is_published'])) {
                continue;
            }
            $eventId = (int) ($comp['id'] ?? 0);
            if ($eventId <= 0) {
                continue;
            }
            foreach ($resultsLoader($eventId) as $row) {
                if (($row['public_list_mode'] ?? 'scoring') === 'roster') {
                    continue;
                }
                $score = $row['score'] ?? null;
                if ($score === null) {
                    continue;
                }
                $pid = (int) ($row['participant_id'] ?? 0);
                if ($pid <= 0) {
                    continue;
                }
                $classId = (int) ($row['class_id'] ?? 0);
                $key = $classId . ':' . $pid;
                if (!isset($byParticipant[$key])) {
                    $byParticipant[$key] = [
                        'participant_id' => $pid,
                        'class_id' => $classId,
                        'class' => (string) ($row['class'] ?? 'Uten klasse'),
                        'class_sort_order' => (int) ($row['class_sort_order'] ?? 999),
                        'name' => trim((string) ($row['first_name'] ?? '') . ' ' . (string) ($row['last_name'] ?? '')),
                        'scores' => [],
                        'competition_scores' => [],
                    ];
                }
                $byParticipant[$key]['scores'][] = (float) $score;
                $byParticipant[$key]['competition_scores'][(string) $eventId] = (float) $score;
            }
        }

        /** @var array<int, list<array<string, mixed>>> $byClass */
        $byClass = [];
        foreach ($byParticipant as $entry) {
            $scores = $entry['scores'];
            rsort($scores, SORT_NUMERIC);
            if ($countBest > 0 && count($scores) > $countBest) {
                $scores = array_slice($scores, 0, $countBest);
            }
            $entry['total_score'] = array_sum($scores);
            $entry['events_count'] = count($entry['scores']);
            unset($entry['scores']);
            $cid = (int) $entry['class_id'];
            $byClass[$cid][] = $entry;
        }

        $classGroups = [];
        foreach ($byClass as $classId => $rows) {
            usort($rows, static fn (array $a, array $b): int => ((float) ($b['total_score'] ?? 0)) <=> ((float) ($a['total_score'] ?? 0)));
            $place = 0;
            foreach ($rows as $i => $row) {
                $rows[$i]['place'] = ++$place;
            }
            $classGroups[] = [
                'class_id' => (int) $classId,
                'label' => (string) ($rows[0]['class'] ?? 'Uten klasse'),
                'sort' => (int) ($rows[0]['class_sort_order'] ?? 999),
                'rows' => $rows,
            ];
        }

        usort($classGroups, static fn (array $a, array $b): int => ($a['sort'] ?? 999) <=> ($b['sort'] ?? 999));

        return [
            'mode' => CupStandings::MODE_TOTAL_SCORE,
            'cup_standings_count_best' => $countBest,
            'class_groups' => $classGroups,
        ];
    }

    /**
     * @param list<array<string, mixed>> $competitions
     * @param list<int>|null $allowedIds
     * @return list<array<string, mixed>>
     */
    private static function filterCompetitions(array $competitions, ?array $allowedIds): array
    {
        if ($allowedIds === null) {
            return $competitions;
        }
        if ($allowedIds === []) {
            return [];
        }
        $allowed = array_flip(array_map('intval', $allowedIds));

        return array_values(array_filter(
            $competitions,
            static fn (array $c): bool => isset($allowed[(int) ($c['id'] ?? 0)])
        ));
    }
}
