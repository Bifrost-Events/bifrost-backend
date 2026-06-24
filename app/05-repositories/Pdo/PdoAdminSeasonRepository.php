<?php

declare(strict_types=1);

namespace App\Repositories\Pdo;

use App\Contracts\Repositories\AdminSeasonRepositoryPort;
use App\Support\CupStandings;

final class PdoAdminSeasonRepository implements AdminSeasonRepositoryPort
{
    private readonly bool $legacyJaktfelt;

    private readonly string $seasonsTable;

    private readonly string $roundsTable;

    private readonly ?string $competitionsTable;

    private readonly ?string $organizerJoinSql;

    public function __construct(private readonly \PDO $pdo)
    {
        $this->legacyJaktfelt = $this->tableExists('jaktfelt_seasons');
        $this->seasonsTable = $this->legacyJaktfelt ? 'jaktfelt_seasons' : 'seasons';
        $this->roundsTable = $this->legacyJaktfelt ? 'jaktfelt_rounds' : 'rounds';
        $this->competitionsTable = $this->tableExists('jaktfelt_competitions')
            ? 'jaktfelt_competitions'
            : ($this->tableExists('competitions') ? 'competitions' : null);
        $this->organizerJoinSql = $this->resolveOrganizerJoinSql();
    }

    public function findAllWithStructure(?array $tenantIds = null): array
    {
        $seasons = $this->fetchSeasonRows($tenantIds);
        if ($seasons === []) {
            return [];
        }

        $seasonIds = array_map(static fn (array $s): int => (int) $s['id'], $seasons);
        $roundsBySeason = $this->fetchRoundsGrouped($seasonIds);
        $compsBySeason = $this->fetchCompetitionsGrouped($seasonIds);

        $result = [];
        foreach ($seasons as $season) {
            $sid = (int) $season['id'];
            $rounds = $roundsBySeason[$sid] ?? [];
            $allComps = $compsBySeason[$sid] ?? [];
            $compsByRound = [];
            foreach ($allComps as $comp) {
                $rid = (int) ($comp['round_id'] ?? 0);
                $compsByRound[$rid][] = $comp;
            }
            foreach ($rounds as &$round) {
                $rid = (int) $round['id'];
                $round['competitions'] = $compsByRound[$rid] ?? [];
            }
            unset($round);

            $season['rounds'] = $rounds;
            $season['cup_competition_choices'] = array_map(static function (array $c): array {
                return [
                    'id' => (int) ($c['id'] ?? 0),
                    'name' => (string) ($c['name'] ?? ''),
                    'competition_date' => (string) ($c['competition_date'] ?? ''),
                    'is_published' => !empty($c['is_published']),
                ];
            }, $allComps);
            $result[] = $season;
        }

        return $result;
    }

    public function findById(int $id): ?array
    {
        $row = $this->fetchSingleSeasonRow($id);
        if ($row === null) {
            return null;
        }

        $season = $this->hydrateSeason($row);
        $rounds = $this->fetchRoundsGrouped([$id])[$id] ?? [];
        $allComps = $this->fetchCompetitionsGrouped([$id])[$id] ?? [];
        $compsByRound = [];
        foreach ($allComps as $comp) {
            $rid = (int) ($comp['round_id'] ?? 0);
            $compsByRound[$rid][] = $comp;
        }
        foreach ($rounds as &$round) {
            $rid = (int) $round['id'];
            $round['competitions'] = $compsByRound[$rid] ?? [];
        }
        unset($round);

        $season['rounds'] = $rounds;
        $season['cup_competition_choices'] = array_map(static function (array $c): array {
            return [
                'id' => (int) ($c['id'] ?? 0),
                'name' => (string) ($c['name'] ?? ''),
                'competition_date' => (string) ($c['competition_date'] ?? ''),
                'is_published' => !empty($c['is_published']),
            ];
        }, $allComps);

        return $season;
    }

    public function create(array $data): array
    {
        $tenantId = (int) ($data['tenant_id'] ?? 0);
        $name = trim((string) ($data['name'] ?? ''));
        $year = (int) ($data['year'] ?? 0);
        $startDate = $this->nullableDate($data['start_date'] ?? null);
        $endDate = $this->nullableDate($data['end_date'] ?? null);
        $isActive = !empty($data['is_active']);

        if ($this->legacyJaktfelt) {
            $stmt = $this->pdo->prepare(
                'INSERT INTO jaktfelt_seasons (name, year, start_date, end_date, is_active)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->execute([$name, $year, $startDate, $endDate, $isActive ? 1 : 0]);
            $seasonId = (int) $this->pdo->lastInsertId();
            $this->bindSeasonToTenant($seasonId, $tenantId);
        } else {
            $cupId = $this->defaultCupIdForTenant($tenantId);
            $stmt = $this->pdo->prepare(
                'INSERT INTO seasons (tenant_id, cup_id, name, year, start_date, end_date, is_active)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([$tenantId, $cupId, $name, $year, $startDate, $endDate, $isActive ? 1 : 0]);
            $seasonId = (int) $this->pdo->lastInsertId();
        }

        $season = $this->findById($seasonId);
        if ($season === null) {
            throw new \RuntimeException('Kunne ikke lese opprettet sesong');
        }

        return $season;
    }

    public function update(int $id, array $data): ?array
    {
        $existing = $this->fetchSingleSeasonRow($id);
        if ($existing === null) {
            return null;
        }

        $name = trim((string) ($data['name'] ?? $existing['name'] ?? ''));
        $year = (int) ($data['year'] ?? $existing['year'] ?? 0);
        $startDate = $this->nullableDate($data['start_date'] ?? $existing['start_date'] ?? null);
        $endDate = $this->nullableDate($data['end_date'] ?? $existing['end_date'] ?? null);
        $isActive = array_key_exists('is_active', $data)
            ? !empty($data['is_active'])
            : ((int) ($existing['is_active'] ?? 0) === 1);

        $stmt = $this->pdo->prepare(
            'UPDATE ' . $this->seasonsTable . ' SET name = ?, year = ?, start_date = ?, end_date = ?, is_active = ? WHERE id = ?'
        );
        $stmt->execute([$name, $year, $startDate, $endDate, $isActive ? 1 : 0, $id]);

        return $this->findById($id);
    }

    public function updateCupStandings(
        int $id,
        string $mode,
        array $placementPointsByPlace,
        ?array $cupCompetitionIds,
        int $cupStandingsCountBest,
    ): void {
        $mode = CupStandings::normalizeMode($mode);
        $json = json_encode($placementPointsByPlace, JSON_UNESCAPED_UNICODE);
        $idsJson = $cupCompetitionIds === null
            ? null
            : json_encode(array_values($cupCompetitionIds), JSON_UNESCAPED_UNICODE);
        $countBest = max(0, min(99, $cupStandingsCountBest));

        $stmt = $this->pdo->prepare(
            'UPDATE ' . $this->seasonsTable . '
             SET cup_standings_mode = ?, cup_placement_points_json = ?,
                 cup_standings_competition_ids_json = ?, cup_standings_count_best = ?
             WHERE id = ?'
        );
        $stmt->execute([$mode, $json, $idsJson, $countBest, $id]);
    }

    public function createRound(int $seasonId, array $data): array
    {
        $roundNumber = max(1, (int) ($data['round_number'] ?? 1));
        $name = trim((string) ($data['name'] ?? ''));
        $startDate = (string) ($data['start_date'] ?? date('Y-m-d'));
        $endDate = (string) ($data['end_date'] ?? $startDate);
        $resultDeadline = (string) ($data['result_deadline'] ?? $endDate);
        $isActive = !array_key_exists('is_active', $data) || !empty($data['is_active']);

        if ($this->legacyJaktfelt) {
            $stmt = $this->pdo->prepare(
                'INSERT INTO jaktfelt_rounds
                 (season_id, round_number, name, start_date, end_date, result_deadline, is_active)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([$seasonId, $roundNumber, $name, $startDate, $endDate, $resultDeadline, $isActive ? 1 : 0]);
        } else {
            $tenantId = $this->tenantIdForSeason($seasonId);
            $stmt = $this->pdo->prepare(
                'INSERT INTO rounds
                 (tenant_id, season_id, round_number, name, start_date, end_date, result_deadline, is_active)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([$tenantId, $seasonId, $roundNumber, $name, $startDate, $endDate, $resultDeadline, $isActive ? 1 : 0]);
        }

        $roundId = (int) $this->pdo->lastInsertId();
        $stmt = $this->pdo->prepare('SELECT * FROM ' . $this->roundsTable . ' WHERE id = ? LIMIT 1');
        $stmt->execute([$roundId]);
        $row = $stmt->fetch();

        return $row === false ? [] : $this->hydrateRound($row);
    }

    public function listCompetitionsForSeason(int $seasonId): array
    {
        if ($this->competitionsTable === null) {
            return [];
        }

        $sql = 'SELECT c.id, c.name, c.competition_date, c.is_published, c.round_id, c.organizer_id';
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
    private function fetchSeasonRows(?array $tenantIds): array
    {
        if ($this->legacyJaktfelt && $this->tableExists('tenant_legacy_bindings')) {
            $sql = 'SELECT s.id, s.name, s.year, s.is_active, s.start_date, s.end_date,
                           s.cup_standings_mode, s.cup_placement_points_json,
                           s.cup_standings_competition_ids_json, s.cup_standings_count_best,
                           tlb.tenant_id
                    FROM jaktfelt_seasons s
                    INNER JOIN tenant_legacy_bindings tlb
                        ON tlb.legacy_table = \'jaktfelt_seasons\' AND tlb.legacy_id = s.id';
            $params = [];
            if ($tenantIds !== null) {
                if ($tenantIds === []) {
                    return [];
                }
                $placeholders = implode(',', array_fill(0, count($tenantIds), '?'));
                $sql .= " WHERE tlb.tenant_id IN ($placeholders)";
                $params = array_values($tenantIds);
            }
            $sql .= ' ORDER BY s.year DESC, s.id DESC';
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            return array_map($this->hydrateSeason(...), $stmt->fetchAll());
        }

        if (!$this->tableExists($this->seasonsTable)) {
            return [];
        }

        $sql = 'SELECT s.*, s.tenant_id FROM ' . $this->seasonsTable . ' s WHERE 1=1';
        $params = [];
        if ($tenantIds !== null) {
            if ($tenantIds === []) {
                return [];
            }
            $placeholders = implode(',', array_fill(0, count($tenantIds), '?'));
            $sql .= " AND s.tenant_id IN ($placeholders)";
            $params = array_values($tenantIds);
        }
        $sql .= ' ORDER BY s.year DESC, s.id DESC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return array_map($this->hydrateSeason(...), $stmt->fetchAll());
    }

    /** @return array<string, mixed>|null */
    private function fetchSingleSeasonRow(int $id): ?array
    {
        if ($this->legacyJaktfelt && $this->tableExists('tenant_legacy_bindings')) {
            $stmt = $this->pdo->prepare(
                'SELECT s.id, s.name, s.year, s.is_active, s.start_date, s.end_date,
                        s.cup_standings_mode, s.cup_placement_points_json,
                        s.cup_standings_competition_ids_json, s.cup_standings_count_best,
                        tlb.tenant_id
                 FROM jaktfelt_seasons s
                 LEFT JOIN tenant_legacy_bindings tlb
                    ON tlb.legacy_table = \'jaktfelt_seasons\' AND tlb.legacy_id = s.id
                 WHERE s.id = ? LIMIT 1'
            );
            $stmt->execute([$id]);
            $row = $stmt->fetch();

            return $row === false ? null : $this->hydrateSeason($row);
        }

        $stmt = $this->pdo->prepare('SELECT * FROM ' . $this->seasonsTable . ' WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row === false ? null : $this->hydrateSeason($row);
    }

    /**
     * @param list<int> $seasonIds
     * @return array<int, list<array<string, mixed>>>
     */
    private function fetchRoundsGrouped(array $seasonIds): array
    {
        if ($seasonIds === [] || !$this->tableExists($this->roundsTable)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($seasonIds), '?'));
        $stmt = $this->pdo->prepare(
            'SELECT * FROM ' . $this->roundsTable . '
             WHERE season_id IN (' . $placeholders . ')
             ORDER BY round_number ASC'
        );
        $stmt->execute($seasonIds);
        $grouped = [];
        foreach ($stmt->fetchAll() as $row) {
            $sid = (int) ($row['season_id'] ?? 0);
            $grouped[$sid][] = $this->hydrateRound($row);
        }

        return $grouped;
    }

    /**
     * @param list<int> $seasonIds
     * @return array<int, list<array<string, mixed>>>
     */
    private function fetchCompetitionsGrouped(array $seasonIds): array
    {
        if ($seasonIds === [] || $this->competitionsTable === null) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($seasonIds), '?'));
        $sql = 'SELECT c.id, c.season_id, c.name, c.location, c.competition_date, c.round_id,
                       c.organizer_id, c.is_published';
        if ($this->organizerJoinSql !== null) {
            $sql .= ', o.name AS organizer_name';
        }
        $sql .= ' FROM ' . $this->competitionsTable . ' c';
        if ($this->organizerJoinSql !== null) {
            $sql .= ' ' . $this->organizerJoinSql;
        }
        $sql .= ' WHERE c.season_id IN (' . $placeholders . ') ORDER BY c.competition_date ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($seasonIds);
        $grouped = [];
        foreach ($stmt->fetchAll() as $row) {
            $sid = (int) ($row['season_id'] ?? 0);
            $grouped[$sid][] = $this->hydrateCompetition($row);
        }

        return $grouped;
    }

    private function bindSeasonToTenant(int $seasonId, int $tenantId): void
    {
        if (!$this->tableExists('tenant_legacy_bindings') || $tenantId <= 0) {
            return;
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO tenant_legacy_bindings (tenant_id, legacy_table, legacy_id, note)
             VALUES (?, \'jaktfelt_seasons\', ?, ?)
             ON DUPLICATE KEY UPDATE tenant_id = VALUES(tenant_id), note = VALUES(note)'
        );
        $stmt->execute([$tenantId, $seasonId, 'Opprettet fra Bifrost Admin']);
    }

    private function defaultCupIdForTenant(int $tenantId): int
    {
        if (!$this->tableExists('cups')) {
            throw new \RuntimeException('Mangler cups-tabell for greenfield-sesong');
        }
        $stmt = $this->pdo->prepare('SELECT id FROM cups WHERE tenant_id = ? ORDER BY id ASC LIMIT 1');
        $stmt->execute([$tenantId]);
        $id = $stmt->fetchColumn();
        if ($id === false) {
            throw new \RuntimeException('Ingen cup registrert for tenant');
        }

        return (int) $id;
    }

    private function tenantIdForSeason(int $seasonId): int
    {
        $row = $this->fetchSingleSeasonRow($seasonId);

        return (int) ($row['tenant_id'] ?? 0);
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
            'SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
        );
        $stmt->execute([$table]);

        return (int) $stmt->fetchColumn() > 0;
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function hydrateSeason(array $row): array
    {
        $mode = CupStandings::normalizeMode((string) ($row['cup_standings_mode'] ?? 'total_score'));
        $pts = [];
        $rawJson = $row['cup_placement_points_json'] ?? null;
        if (is_string($rawJson) && $rawJson !== '') {
            $decoded = json_decode($rawJson, true);
            if (is_array($decoded)) {
                $pts = $decoded;
            }
        }

        $cupCompIds = null;
        $rawIds = $row['cup_standings_competition_ids_json'] ?? null;
        if (is_string($rawIds) && $rawIds !== '') {
            $decodedIds = json_decode($rawIds, true);
            if (is_array($decodedIds)) {
                $cupCompIds = array_values(array_unique(array_filter(
                    array_map('intval', $decodedIds),
                    static fn (int $x): bool => $x > 0
                )));
            }
        }

        $countBest = max(0, (int) ($row['cup_standings_count_best'] ?? CupStandings::DEFAULT_COUNT_BEST));

        return [
            'id' => (int) ($row['id'] ?? 0),
            'tenant_id' => (int) ($row['tenant_id'] ?? 0),
            'name' => (string) ($row['name'] ?? ''),
            'year' => (int) ($row['year'] ?? 0),
            'is_active' => (int) ($row['is_active'] ?? 0) === 1,
            'start_date' => $row['start_date'] ?? null,
            'end_date' => $row['end_date'] ?? null,
            'cup_standings_mode' => $mode,
            'cup_placement_points' => $pts,
            'cup_standings_competition_ids' => $cupCompIds,
            'cup_standings_count_best' => $countBest,
        ];
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function hydrateRound(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'season_id' => (int) ($row['season_id'] ?? 0),
            'round_number' => (int) ($row['round_number'] ?? 0),
            'name' => (string) ($row['name'] ?? ''),
            'start_date' => (string) ($row['start_date'] ?? ''),
            'end_date' => (string) ($row['end_date'] ?? ''),
            'result_deadline' => (string) ($row['result_deadline'] ?? ''),
            'is_active' => (int) ($row['is_active'] ?? 1) === 1,
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
        ];
    }

    private function nullableDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }
}
