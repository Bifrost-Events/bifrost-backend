<?php

declare(strict_types=1);

namespace App\Repositories\Pdo;

use App\Contracts\Repositories\ParticipantRepositoryPort;

final class PdoParticipantRepository implements ParticipantRepositoryPort
{
    private const TABLE = 'jaktfelt_participants';
    private const IDENTIFIERS_TABLE = 'jaktfelt_participant_identifiers';
    private const CLASSES_TABLE = 'jaktfelt_classes';
    private const PARTICIPANT_CLASSES_TABLE = 'jaktfelt_participant_classes';

    public function __construct(private readonly \PDO $pdo)
    {
    }

    public function listByOwnerUserId(int $ownerUserId): array
    {
        $today = date('Y-m-d');
        $stmt = $this->pdo->prepare(
            'SELECT p.id, p.first_name, p.last_name, p.date_of_birth, p.phone, p.club, ' .
            'COALESCE(pi_j.value, pi_s.value) AS jaktfelt_id, pc.class_id, c.name AS class_name ' .
            'FROM ' . self::TABLE . ' p ' .
            'LEFT JOIN ' . self::IDENTIFIERS_TABLE . ' pi_j ON pi_j.participant_id = p.id AND pi_j.identifier_type = \'jaktfelt_id\' ' .
            'LEFT JOIN ' . self::IDENTIFIERS_TABLE . ' pi_s ON pi_s.participant_id = p.id AND pi_s.identifier_type = \'shooter_id\' ' .
            'LEFT JOIN ' . self::PARTICIPANT_CLASSES_TABLE . ' pc ON pc.participant_id = p.id AND pc.from_date <= ? AND (pc.to_date IS NULL OR pc.to_date >= ?) ' .
            'LEFT JOIN ' . self::CLASSES_TABLE . ' c ON c.id = pc.class_id ' .
            'WHERE p.owner_user_id = ? ORDER BY p.last_name, p.first_name'
        );
        $stmt->execute([$today, $today, $ownerUserId]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return array_map($this->hydrateListRow(...), $rows);
    }

    public function findRowById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM ' . self::TABLE . ' WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    public function jaktfeltIdExists(string $value): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM ' . self::IDENTIFIERS_TABLE . ' WHERE value = ? AND identifier_type IN (\'jaktfelt_id\', \'shooter_id\') LIMIT 1'
        );
        $stmt->execute([$value]);

        return $stmt->fetch(\PDO::FETCH_ASSOC) !== false;
    }

    public function createForUser(
        int $ownerUserId,
        string $firstName,
        string $lastName,
        int $classId,
        ?\DateTimeInterface $dateOfBirth,
        ?string $phone,
        ?string $club,
    ): array {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO ' . self::TABLE . ' (owner_user_id, owner_organizer_id, first_name, last_name, date_of_birth, phone, club, source) VALUES (?, NULL, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $ownerUserId,
                $firstName,
                $lastName,
                $dateOfBirth?->format('Y-m-d'),
                $phone,
                $club,
                'self_registered',
            ]);
            $id = (int) $this->pdo->lastInsertId();
            $stmt = $this->pdo->prepare(
                'INSERT INTO ' . self::PARTICIPANT_CLASSES_TABLE . ' (participant_id, class_id, from_date) VALUES (?, ?, ?)'
            );
            $stmt->execute([$id, $classId, date('Y-m-d')]);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        $rows = $this->listByOwnerUserId($ownerUserId);
        foreach ($rows as $row) {
            if ((int) ($row['id'] ?? 0) === $id) {
                return $row;
            }
        }

        return [
            'id' => $id,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'date_of_birth' => $dateOfBirth?->format('Y-m-d'),
            'phone' => $phone,
            'club' => $club,
            'class_id' => $classId,
        ];
    }

    public function updateOwned(
        int $id,
        int $ownerUserId,
        string $firstName,
        string $lastName,
        int $classId,
        ?\DateTimeInterface $dateOfBirth,
        ?string $phone,
        ?string $club,
    ): void {
        $row = $this->findRowById($id);
        if ($row === null || (int) ($row['owner_user_id'] ?? 0) !== $ownerUserId) {
            throw new \RuntimeException('Deltaker ikke funnet eller ingen tilgang');
        }

        $clubValue = $club !== null ? trim($club) : null;
        if ($clubValue === '') {
            $clubValue = null;
        }

        $stmt = $this->pdo->prepare(
            'UPDATE ' . self::TABLE . ' SET first_name=?, last_name=?, date_of_birth=?, phone=?, club=? WHERE id=?'
        );
        $stmt->execute([$firstName, $lastName, $dateOfBirth?->format('Y-m-d'), $phone, $clubValue, $id]);

        if ($classId > 0) {
            $currentClassId = $this->getCurrentClassId($id);
            if ($currentClassId !== $classId) {
                $this->setParticipantClass($id, $classId, new \DateTimeImmutable('today'));
            }
        }
    }

    public function addJaktfeltId(int $participantId, string $jaktfeltIdValue): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO ' . self::IDENTIFIERS_TABLE . ' (participant_id, identifier_type, value) VALUES (?, \'jaktfelt_id\', ?)'
        );
        $stmt->execute([$participantId, $jaktfeltIdValue]);
    }

    public function listDistinctClubs(int $limit = 200): array
    {
        $limit = max(1, min(500, $limit));
        try {
            $stmt = $this->pdo->prepare(
                'SELECT DISTINCT club FROM ' . self::TABLE . ' WHERE club IS NOT NULL AND club <> \'\' ORDER BY club ASC LIMIT ' . (int) $limit
            );
            $stmt->execute();
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            $out = [];
            foreach ($rows as $r) {
                $c = trim((string) ($r['club'] ?? ''));
                if ($c !== '') {
                    $out[] = $c;
                }
            }

            return $out;
        } catch (\PDOException) {
            return [];
        }
    }

    public function findByNamesAndPhone(string $firstName, string $lastName, ?string $phone): ?array
    {
        $sql = 'SELECT * FROM ' . self::TABLE . ' WHERE first_name = ? AND last_name = ?';
        $params = [trim($firstName), trim($lastName)];
        if ($phone !== null && trim($phone) !== '') {
            $sql .= ' AND phone = ?';
            $params[] = trim($phone);
        }
        $sql .= ' LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    public function getJaktfeltId(int $participantId): ?string
    {
        $stmt = $this->pdo->prepare(
            'SELECT value FROM ' . self::IDENTIFIERS_TABLE . ' WHERE participant_id = ? AND identifier_type IN (\'jaktfelt_id\', \'shooter_id\') LIMIT 1'
        );
        $stmt->execute([$participantId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row === false ? null : (string) ($row['value'] ?? '');
    }

    public function findClassByCode(string $code): ?array
    {
        if (!$this->tableExists(self::CLASSES_TABLE)) {
            return null;
        }

        $stmt = $this->pdo->prepare('SELECT id, name, code FROM ' . self::CLASSES_TABLE . ' WHERE code = ? LIMIT 1');
        $stmt->execute([$code]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }

        return [
            'id' => (int) ($row['id'] ?? 0),
            'name' => (string) ($row['name'] ?? ''),
            'code' => (string) ($row['code'] ?? ''),
        ];
    }

    public function transferOwnership(int $participantId, int $newOwnerUserId): void
    {
        $stmt = $this->pdo->prepare('UPDATE ' . self::TABLE . ' SET owner_user_id = ? WHERE id = ?');
        $stmt->execute([$newOwnerUserId, $participantId]);
    }

    public function listClasses(): array
    {
        if (!$this->tableExists(self::CLASSES_TABLE)) {
            return [];
        }

        $stmt = $this->pdo->query('SELECT id, name, code FROM ' . self::CLASSES_TABLE . ' ORDER BY sort_order ASC, name ASC');
        if ($stmt === false) {
            return [];
        }

        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return array_map(static function (array $row): array {
            return [
                'id' => (int) ($row['id'] ?? 0),
                'name' => (string) ($row['name'] ?? ''),
                'code' => isset($row['code']) ? (string) $row['code'] : null,
            ];
        }, $rows);
    }

    private function getCurrentClassId(int $participantId): ?int
    {
        $today = date('Y-m-d');
        $stmt = $this->pdo->prepare(
            'SELECT class_id FROM ' . self::PARTICIPANT_CLASSES_TABLE . ' ' .
            'WHERE participant_id = ? AND from_date <= ? AND (to_date IS NULL OR to_date >= ?) LIMIT 1'
        );
        $stmt->execute([$participantId, $today, $today]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row ? (int) $row['class_id'] : null;
    }

    private function setParticipantClass(int $participantId, int $classId, \DateTimeInterface $fromDate): void
    {
        $today = date('Y-m-d');
        $from = $fromDate->format('Y-m-d');
        $endPrev = (new \DateTimeImmutable($from))->modify('-1 day')->format('Y-m-d');
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'UPDATE ' . self::PARTICIPANT_CLASSES_TABLE . ' SET to_date = ? ' .
                'WHERE participant_id = ? AND (to_date IS NULL OR to_date >= ?)'
            );
            $stmt->execute([$endPrev, $participantId, $today]);
            $stmt = $this->pdo->prepare(
                'INSERT INTO ' . self::PARTICIPANT_CLASSES_TABLE . ' (participant_id, class_id, from_date) VALUES (?, ?, ?)'
            );
            $stmt->execute([$participantId, $classId, $from]);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /** @param array<string, mixed> $r */
    private function hydrateListRow(array $r): array
    {
        return [
            'id' => (int) ($r['id'] ?? 0),
            'first_name' => (string) ($r['first_name'] ?? ''),
            'last_name' => (string) ($r['last_name'] ?? ''),
            'date_of_birth' => !empty($r['date_of_birth']) ? (string) $r['date_of_birth'] : null,
            'phone' => !empty($r['phone']) ? (string) $r['phone'] : null,
            'club' => isset($r['club']) && trim((string) $r['club']) !== '' ? trim((string) $r['club']) : null,
            'jaktfelt_id' => $r['jaktfelt_id'] ?? null,
            'class_id' => isset($r['class_id']) ? (int) $r['class_id'] : null,
            'class_name' => $r['class_name'] ?? null,
        ];
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
