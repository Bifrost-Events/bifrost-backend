<?php

declare(strict_types=1);

namespace App\Repositories\Pdo;

use App\Contracts\Repositories\SignupRepositoryPort;

final class PdoSignupRepository implements SignupRepositoryPort
{
    private const SLOTS_TABLE = 'jaktfelt_competition_signup_slots';
    private const FIGURES_TABLE = 'jaktfelt_competition_signup_figures';

    public function __construct(private readonly \PDO $pdo)
    {
    }

    public function listSlotsByCompetitionId(int $competitionId): array
    {
        if (!$this->tableExists(self::SLOTS_TABLE)) {
            return [];
        }

        $stmt = $this->pdo->prepare(
            'SELECT id, competition_id, slot_number, TIME_FORMAT(start_time, \'%H:%i\') as start_time, ' .
            'COALESCE(is_reserved, 0) as is_reserved, COALESCE(is_roster_locked, 0) as is_roster_locked, ' .
            'COALESCE(is_locked, 0) as is_locked ' .
            'FROM ' . self::SLOTS_TABLE . ' WHERE competition_id = ? ORDER BY slot_number ASC'
        );
        $stmt->execute([$competitionId]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return array_map(static function (array $r): array {
            return [
                'id' => (int) ($r['id'] ?? 0),
                'competition_id' => (int) ($r['competition_id'] ?? 0),
                'slot_number' => (int) ($r['slot_number'] ?? 0),
                'start_time' => $r['start_time'] ?? null,
                'is_reserved' => (int) ($r['is_reserved'] ?? 0) === 1,
                'is_roster_locked' => (int) ($r['is_roster_locked'] ?? 0) === 1,
                'is_locked' => (int) ($r['is_locked'] ?? 0) === 1,
            ];
        }, $rows);
    }

    public function listRegistrationsByEventId(int $eventId): array
    {
        if (!$this->tableExists(self::FIGURES_TABLE)) {
            return [];
        }

        $sql = 'SELECT f.id, f.participant_id, f.registered_by_user_id, f.slot_id, s.slot_number, ' .
               'TIME_FORMAT(s.start_time, \'%H:%i\') as start_time, f.figure_number, ' .
               'p.first_name, p.last_name ' .
               'FROM ' . self::FIGURES_TABLE . ' f ' .
               'JOIN ' . self::SLOTS_TABLE . ' s ON s.id = f.slot_id ' .
               'JOIN jaktfelt_participants p ON p.id = f.participant_id ' .
               'WHERE s.competition_id = ? AND f.participant_id IS NOT NULL AND f.is_reserved = 0 ' .
               'ORDER BY s.slot_number ASC, f.figure_number ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$eventId]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return array_map(static function (array $row): array {
            $rb = $row['registered_by_user_id'] ?? null;
            $registeredByUserId = ($rb === null || $rb === '') ? null : (int) $rb;
            if ($registeredByUserId !== null && $registeredByUserId <= 0) {
                $registeredByUserId = null;
            }

            return [
                'id' => (int) ($row['id'] ?? 0),
                'participant_id' => (int) ($row['participant_id'] ?? 0),
                'registered_by_user_id' => $registeredByUserId,
                'slot_id' => isset($row['slot_id']) ? (int) $row['slot_id'] : null,
                'slot_number' => isset($row['slot_number']) ? (int) $row['slot_number'] : null,
                'start_time' => $row['start_time'] ?? null,
                'figure_number' => isset($row['figure_number']) ? (int) $row['figure_number'] : null,
                'first_name' => (string) ($row['first_name'] ?? ''),
                'last_name' => (string) ($row['last_name'] ?? ''),
            ];
        }, $rows);
    }

    public function listReservedPlacesByEventId(int $eventId): array
    {
        $sql = 'SELECT s.slot_number, f.figure_number FROM ' . self::FIGURES_TABLE . ' f ' .
               'JOIN ' . self::SLOTS_TABLE . ' s ON s.id = f.slot_id ' .
               'WHERE s.competition_id = ? AND f.is_reserved = 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$eventId]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return array_map(static fn (array $row): array => [
            'slot_number' => (int) ($row['slot_number'] ?? 0),
            'figure_number' => (int) ($row['figure_number'] ?? 0),
        ], $rows);
    }

    public function hasRegistration(int $eventId, int $participantId): bool
    {
        $sql = 'SELECT 1 FROM ' . self::FIGURES_TABLE . ' f ' .
               'JOIN ' . self::SLOTS_TABLE . ' s ON s.id = f.slot_id AND s.competition_id = ? ' .
               'WHERE f.participant_id = ? AND f.is_reserved = 0';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$eventId, $participantId]);

        return $stmt->fetch() !== false;
    }

    public function isPlaceReserved(int $eventId, int $slotId, int $figureNumber): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM ' . self::FIGURES_TABLE . ' f ' .
            'JOIN ' . self::SLOTS_TABLE . ' s ON s.id = f.slot_id ' .
            'WHERE s.competition_id = ? AND f.slot_id = ? AND f.figure_number = ? AND f.is_reserved = 1'
        );
        $stmt->execute([$eventId, $slotId, $figureNumber]);

        return $stmt->fetch() !== false;
    }

    public function findSlotById(int $slotId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, competition_id, slot_number, TIME_FORMAT(start_time, \'%H:%i\') as start_time, ' .
            'COALESCE(is_reserved, 0) as is_reserved FROM ' . self::SLOTS_TABLE . ' WHERE id = ?'
        );
        $stmt->execute([$slotId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }

        return [
            'id' => (int) ($row['id'] ?? 0),
            'competition_id' => (int) ($row['competition_id'] ?? 0),
            'slot_number' => (int) ($row['slot_number'] ?? 0),
            'start_time' => $row['start_time'] ?? null,
            'is_reserved' => (int) ($row['is_reserved'] ?? 0) === 1,
        ];
    }

    public function createRegistration(
        int $eventId,
        int $participantId,
        ?int $slotId,
        ?int $figureNumber,
        string $registeredVia,
        ?int $registeredByUserId,
    ): void {
        $figNo = $figureNumber ?? 1;
        $existingStmt = $this->pdo->prepare(
            'SELECT id, participant_id, is_reserved FROM ' . self::FIGURES_TABLE . ' WHERE slot_id = ? AND figure_number = ? LIMIT 1'
        );
        $existingStmt->execute([$slotId, $figNo]);
        $existing = $existingStmt->fetch(\PDO::FETCH_ASSOC);
        if ($existing !== false) {
            $isReserved = (int) ($existing['is_reserved'] ?? 0) === 1;
            $hasParticipant = isset($existing['participant_id']) && (int) $existing['participant_id'] > 0;
            if ($isReserved && !$hasParticipant) {
                $upd = $this->pdo->prepare(
                    'UPDATE ' . self::FIGURES_TABLE . ' SET participant_id = ?, is_reserved = 0, registered_via = ?, registered_by_user_id = ? WHERE id = ?'
                );
                $upd->execute([$participantId, $registeredVia, $registeredByUserId, (int) $existing['id']]);

                return;
            }
            throw new \RuntimeException('Plassen er opptatt');
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO ' . self::FIGURES_TABLE . ' (slot_id, figure_number, participant_id, is_reserved, registered_via, registered_by_user_id) VALUES (?, ?, ?, 0, ?, ?)'
        );
        $stmt->execute([$slotId, $figNo, $participantId, $registeredVia, $registeredByUserId]);
    }

    public function cancelRegistration(int $eventId, int $participantId): void
    {
        $stmt = $this->pdo->prepare(
            'DELETE f FROM ' . self::FIGURES_TABLE . ' f ' .
            'JOIN ' . self::SLOTS_TABLE . ' s ON s.id = f.slot_id ' .
            'WHERE s.competition_id = ? AND f.participant_id = ? AND f.is_reserved = 0'
        );
        $stmt->execute([$eventId, $participantId]);
    }

    public function listSignupsForUserInTenant(int $userId, int $tenantId): array
    {
        if (!$this->tableExists('tenant_legacy_bindings')) {
            return [];
        }

        $sql = 'SELECT f.id, f.participant_id, f.slot_id, s.slot_number, TIME_FORMAT(s.start_time, \'%H:%i\') as start_time, ' .
               'f.figure_number, c.id AS competition_id, c.name AS competition_name, c.competition_date, c.location, ' .
               'p.first_name, p.last_name ' .
               'FROM ' . self::FIGURES_TABLE . ' f ' .
               'JOIN ' . self::SLOTS_TABLE . ' s ON s.id = f.slot_id ' .
               'JOIN jaktfelt_competitions c ON c.id = s.competition_id ' .
               'JOIN jaktfelt_participants p ON p.id = f.participant_id ' .
               'JOIN jaktfelt_seasons seas ON seas.id = c.season_id ' .
               'JOIN tenant_legacy_bindings tlb ON tlb.legacy_table = \'jaktfelt_seasons\' AND tlb.legacy_id = seas.id ' .
               'WHERE p.owner_user_id = ? AND tlb.tenant_id = ? AND f.is_reserved = 0 AND f.participant_id IS NOT NULL ' .
               'AND c.is_published = 1 AND c.competition_date >= CURDATE() ' .
               'ORDER BY c.competition_date ASC, s.slot_number ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$userId, $tenantId]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return array_map(static function (array $row): array {
            return [
                'id' => (int) ($row['id'] ?? 0),
                'participant_id' => (int) ($row['participant_id'] ?? 0),
                'slot_id' => (int) ($row['slot_id'] ?? 0),
                'slot_number' => (int) ($row['slot_number'] ?? 0),
                'start_time' => $row['start_time'] ?? null,
                'figure_number' => (int) ($row['figure_number'] ?? 0),
                'competition_id' => (int) ($row['competition_id'] ?? 0),
                'competition_name' => (string) ($row['competition_name'] ?? ''),
                'competition_date' => (string) ($row['competition_date'] ?? ''),
                'location' => (string) ($row['location'] ?? ''),
                'first_name' => (string) ($row['first_name'] ?? ''),
                'last_name' => (string) ($row['last_name'] ?? ''),
            ];
        }, $rows);
    }

    public function findOrganizerById(int $organizerId): ?array
    {
        if ($organizerId < 1) {
            return null;
        }

        if ($this->tableExists('jaktfelt_organizers_v2')) {
            $stmt = $this->pdo->prepare(
                'SELECT id, name, contact_person, email, phone FROM jaktfelt_organizers_v2 WHERE id = ? LIMIT 1'
            );
            $stmt->execute([$organizerId]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($row !== false) {
                return [
                    'id' => (int) ($row['id'] ?? 0),
                    'name' => (string) ($row['name'] ?? ''),
                    'contact_person' => $row['contact_person'] ?? null,
                    'email' => $row['email'] ?? null,
                    'phone' => $row['phone'] ?? null,
                ];
            }
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
