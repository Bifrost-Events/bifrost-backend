<?php

declare(strict_types=1);

namespace App\Repositories\Pdo;

use App\Contracts\Repositories\PublicReadRepositoryPort;
use App\Support\CupStandings;
use App\Support\ScoreBreakdown;

final class PdoPublicReadRepository implements PublicReadRepositoryPort
{
    private readonly bool $legacyJaktfelt;

    private readonly string $seasonsTable;

    private readonly ?string $competitionsTable;

    private readonly ?string $organizerJoinSql;

    public function __construct(private readonly \PDO $pdo)
    {
        $this->legacyJaktfelt = $this->tableExists('jaktfelt_seasons');
        $this->seasonsTable = $this->legacyJaktfelt ? 'jaktfelt_seasons' : 'seasons';
        $this->competitionsTable = $this->tableExists('jaktfelt_competitions')
            ? 'jaktfelt_competitions'
            : ($this->tableExists('competitions') ? 'competitions' : null);
        $this->organizerJoinSql = $this->resolveOrganizerJoinSql();
    }

    public function findActiveSeasonForTenant(int $tenantId): ?array
    {
        if ($this->competitionsTable === null) {
            return null;
        }

        if ($this->legacyJaktfelt && $this->tableExists('tenant_legacy_bindings')) {
            $stmt = $this->pdo->prepare(
                'SELECT s.id, s.name, s.year, s.is_active, s.start_date, s.end_date,
                        s.cup_standings_mode, s.cup_placement_points_json,
                        s.cup_standings_competition_ids_json, s.cup_standings_count_best
                 FROM jaktfelt_seasons s
                 INNER JOIN tenant_legacy_bindings tlb
                    ON tlb.legacy_table = \'jaktfelt_seasons\' AND tlb.legacy_id = s.id
                 WHERE tlb.tenant_id = ? AND s.is_active = 1
                 ORDER BY s.year DESC, s.id DESC
                 LIMIT 1'
            );
            $stmt->execute([$tenantId]);
            $row = $stmt->fetch();

            return $row === false ? null : $this->hydrateSeason($row);
        }

        if (!$this->tableExists($this->seasonsTable)) {
            return null;
        }

        $stmt = $this->pdo->prepare(
            'SELECT * FROM ' . $this->seasonsTable . ' WHERE tenant_id = ? AND is_active = 1 ORDER BY year DESC, id DESC LIMIT 1'
        );
        $stmt->execute([$tenantId]);
        $row = $stmt->fetch();

        return $row === false ? null : $this->hydrateSeason($row);
    }

    public function listUpcomingCompetitions(int $tenantId): array
    {
        return $this->listCompetitions($tenantId, true);
    }

    public function listCompetitionsWithResults(int $tenantId): array
    {
        if ($this->competitionsTable === null || !$this->tableExists('jaktfelt_competition_results')) {
            return [];
        }

        $tenantFilter = $this->tenantSeasonJoinSql('c.season_id');
        if ($tenantFilter === null) {
            return [];
        }

        $sql = 'SELECT DISTINCT c.id, c.season_id, c.name, c.location, c.competition_date, c.organizer_id,
                       c.is_published, c.round_id';
        if ($this->organizerJoinSql !== null) {
            $sql .= ', o.name AS organizer_name';
        }
        $sql .= ' FROM ' . $this->competitionsTable . ' c ' . $tenantFilter['join'];
        if ($this->organizerJoinSql !== null) {
            $sql .= ' ' . $this->organizerJoinSql;
        }
        $sql .= ' WHERE ' . $tenantFilter['where'] . ' c.is_published = 1
                  AND EXISTS (SELECT 1 FROM jaktfelt_competition_results r WHERE r.competition_id = c.id)
                  ORDER BY c.competition_date DESC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$tenantId]);

        return array_map($this->hydrateCompetition(...), $stmt->fetchAll());
    }

    public function findCompetitionForTenant(int $tenantId, int $competitionId): ?array
    {
        $rows = $this->listCompetitions($tenantId, false, $competitionId);

        return $rows[0] ?? null;
    }

    public function listCompetitionResults(int $competitionId): array
    {
        if (!$this->tableExists('jaktfelt_competition_results') || !$this->tableExists('jaktfelt_participants')) {
            return [];
        }

        $sql = 'SELECT cr.participant_id, cr.class_id, cr.score_breakdown, cr.figure_number,
                       cl.name AS class_name, cl.code AS class_code,
                       COALESCE(cl.sort_order, 999) AS class_sort_order,
                       COALESCE(cl.public_list_mode, \'scoring\') AS public_list_mode,
                       p.first_name, p.last_name,
                       s.slot_number, TIME_FORMAT(s.start_time, \'%H:%i\') AS start_time
                FROM jaktfelt_competition_results cr
                INNER JOIN jaktfelt_participants p ON p.id = cr.participant_id
                LEFT JOIN jaktfelt_classes cl ON cl.id = cr.class_id
                LEFT JOIN jaktfelt_competition_signup_slots s ON s.id = cr.slot_id AND s.competition_id = cr.competition_id
                WHERE cr.competition_id = ? AND cr.participant_id IS NOT NULL
                ORDER BY class_sort_order ASC, p.last_name ASC, p.first_name ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$competitionId]);

        $rows = [];
        foreach ($stmt->fetchAll() as $row) {
            $totals = ScoreBreakdown::totals($row['score_breakdown'] ?? null);
            $rows[] = [
                'participant_id' => (int) ($row['participant_id'] ?? 0),
                'class_id' => isset($row['class_id']) ? (int) $row['class_id'] : 0,
                'class' => (string) ($row['class_name'] ?? ''),
                'class_code' => (string) ($row['class_code'] ?? ''),
                'class_sort_order' => (int) ($row['class_sort_order'] ?? 999),
                'public_list_mode' => ($row['public_list_mode'] ?? 'scoring') === 'roster' ? 'roster' : 'scoring',
                'first_name' => (string) ($row['first_name'] ?? ''),
                'last_name' => (string) ($row['last_name'] ?? ''),
                'slot_number' => isset($row['slot_number']) ? (int) $row['slot_number'] : null,
                'start_time' => $row['start_time'] ?? null,
                'figure_number' => isset($row['figure_number']) ? (int) $row['figure_number'] : null,
                'score' => $totals['score'],
                'total_hits' => $totals['hits'],
                'total_inner_hits' => $totals['inner_hits'],
                'place' => null,
            ];
        }

        return $this->assignPlacesPerClass($rows);
    }

    public function listSeasonCompetitions(int $seasonId): array
    {
        if ($this->competitionsTable === null) {
            return [];
        }

        $sql = 'SELECT c.id, c.season_id, c.name, c.location, c.competition_date, c.organizer_id, c.is_published, c.round_id';
        if ($this->organizerJoinSql !== null) {
            $sql .= ', o.name AS organizer_name';
        }
        $sql .= ' FROM ' . $this->competitionsTable . ' c';
        if ($this->organizerJoinSql !== null) {
            $sql .= ' ' . $this->organizerJoinSql;
        }
        $sql .= ' WHERE c.season_id = ? ORDER BY c.competition_date ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$seasonId]);

        return array_map($this->hydrateCompetition(...), $stmt->fetchAll());
    }

    /** @return list<array<string, mixed>> */
    private function listCompetitions(int $tenantId, bool $upcomingOnly, ?int $competitionId = null): array
    {
        if ($this->competitionsTable === null) {
            return [];
        }

        $tenantFilter = $this->tenantSeasonJoinSql('c.season_id');
        if ($tenantFilter === null) {
            return [];
        }

        $sql = 'SELECT c.id, c.season_id, c.name, c.location, c.competition_date, c.organizer_id,
                       c.is_published, c.round_id, c.registration_start, c.registration_end,
                       c.invitation_text, c.description, c.scoring_mode,
                       c.advance_registration_enabled, c.antall_skyttere_per_lag, c.antall_lag,
                       c.minutter_mellom_lag';
        if ($this->organizerJoinSql !== null) {
            $sql .= ', o.name AS organizer_name';
        }
        $sql .= ' FROM ' . $this->competitionsTable . ' c ' . $tenantFilter['join'];
        if ($this->organizerJoinSql !== null) {
            $sql .= ' ' . $this->organizerJoinSql;
        }
        $sql .= ' WHERE ' . $tenantFilter['where'] . ' c.is_published = 1';
        $params = array_merge([$tenantId], $tenantFilter['params']);
        if ($upcomingOnly) {
            $sql .= ' AND c.competition_date >= CURDATE()';
        }
        if ($competitionId !== null) {
            $sql .= ' AND c.id = ?';
            $params[] = $competitionId;
        }
        $sql .= ' ORDER BY c.competition_date ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return array_map($this->hydrateCompetition(...), $stmt->fetchAll());
    }

    /**
     * @return array{join: string, where: string, params: list<int>}|null
     */
    private function tenantSeasonJoinSql(string $seasonColumn): ?array
    {
        if ($this->legacyJaktfelt && $this->tableExists('tenant_legacy_bindings')) {
            return [
                'join' => 'INNER JOIN tenant_legacy_bindings tlb ON tlb.legacy_table = \'jaktfelt_seasons\' AND tlb.legacy_id = ' . $seasonColumn,
                'where' => 'tlb.tenant_id = ? AND ',
                'params' => [],
            ];
        }

        if ($this->tableExists('seasons')) {
            return [
                'join' => 'INNER JOIN seasons s ON s.id = ' . $seasonColumn,
                'where' => 's.tenant_id = ? AND ',
                'params' => [],
            ];
        }

        return null;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function assignPlacesPerClass(array $rows): array
    {
        /** @var array<int, list<int>> $indicesByClass */
        $indicesByClass = [];
        foreach ($rows as $idx => $row) {
            $classId = (int) ($row['class_id'] ?? 0);
            $indicesByClass[$classId][] = $idx;
        }

        foreach ($indicesByClass as $indices) {
            usort($indices, function (int $ia, int $ib) use ($rows): int {
                $a = $rows[$ia];
                $b = $rows[$ib];
                if (($a['public_list_mode'] ?? 'scoring') === 'roster' || ($b['public_list_mode'] ?? 'scoring') === 'roster') {
                    return strcmp((string) ($a['last_name'] ?? ''), (string) ($b['last_name'] ?? ''));
                }
                $sa = $a['score'] ?? null;
                $sb = $b['score'] ?? null;
                if ($sa !== null && $sb !== null && (float) $sa !== (float) $sb) {
                    return (float) $sb <=> (float) $sa;
                }

                return strcmp((string) ($a['last_name'] ?? ''), (string) ($b['last_name'] ?? ''));
            });

            $place = 0;
            foreach ($indices as $i) {
                if (($rows[$i]['public_list_mode'] ?? 'scoring') === 'roster') {
                    $rows[$i]['place'] = null;
                    continue;
                }
                $rows[$i]['place'] = ++$place;
            }
        }

        return $rows;
    }

  /** @param array<string, mixed> $row @return array<string, mixed> */
    private function hydrateSeason(array $row): array
    {
        $cupCompIds = null;
        $rawIds = $row['cup_standings_competition_ids_json'] ?? null;
        if (is_string($rawIds) && $rawIds !== '') {
            $decoded = json_decode($rawIds, true);
            if (is_array($decoded)) {
                $cupCompIds = array_values(array_filter(array_map('intval', $decoded), static fn (int $x): bool => $x > 0));
            }
        }

        return [
            'id' => (int) ($row['id'] ?? 0),
            'name' => (string) ($row['name'] ?? ''),
            'year' => (int) ($row['year'] ?? 0),
            'is_active' => (int) ($row['is_active'] ?? 0) === 1,
            'cup_standings_mode' => (string) ($row['cup_standings_mode'] ?? CupStandings::MODE_TOTAL_SCORE),
            'cup_standings_competition_ids' => $cupCompIds,
            'cup_standings_count_best' => max(0, (int) ($row['cup_standings_count_best'] ?? CupStandings::DEFAULT_COUNT_BEST)),
        ];
    }

  /** @param array<string, mixed> $row @return array<string, mixed> */
    private function hydrateCompetition(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'season_id' => (int) ($row['season_id'] ?? 0),
            'round_id' => isset($row['round_id']) ? (int) $row['round_id'] : null,
            'name' => (string) ($row['name'] ?? ''),
            'location' => (string) ($row['location'] ?? ''),
            'competition_date' => (string) ($row['competition_date'] ?? ''),
            'organizer_id' => isset($row['organizer_id']) ? (int) $row['organizer_id'] : null,
            'organizer_name' => isset($row['organizer_name']) ? (string) $row['organizer_name'] : null,
            'is_published' => (int) ($row['is_published'] ?? 0) === 1,
            'registration_start' => $row['registration_start'] ?? null,
            'registration_end' => $row['registration_end'] ?? null,
            'invitation_text' => isset($row['invitation_text']) ? (string) $row['invitation_text'] : null,
            'description' => isset($row['description']) ? (string) $row['description'] : null,
            'scoring_mode' => isset($row['scoring_mode']) ? (string) $row['scoring_mode'] : 'njff',
            'advance_registration_enabled' => (int) ($row['advance_registration_enabled'] ?? 1) === 1,
            'antall_skyttere_per_lag' => isset($row['antall_skyttere_per_lag']) ? (int) $row['antall_skyttere_per_lag'] : 0,
            'antall_lag' => isset($row['antall_lag']) ? (int) $row['antall_lag'] : 0,
            'minutter_mellom_lag' => isset($row['minutter_mellom_lag']) ? (int) $row['minutter_mellom_lag'] : 60,
        ];
    }

    private function resolveOrganizerJoinSql(): ?string
    {
        if ($this->competitionsTable === null) {
            return null;
        }
        if ($this->tableExists('jaktfelt_organizers_v2')) {
            return 'LEFT JOIN jaktfelt_organizers_v2 o ON o.id = c.organizer_id';
        }
        if ($this->tableExists('organizations')) {
            return 'LEFT JOIN organizations o ON o.legacy_jaktfelt_organizer_id = c.organizer_id';
        }

        return null;
    }

    private function tableExists(string $table): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
        );
        $stmt->execute([$table]);

        return (int) $stmt->fetchColumn() > 0;
    }
}
