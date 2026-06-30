<?php

declare(strict_types=1);

namespace App\Repositories\Pdo;

use App\Contracts\Repositories\ParticipantClaimRepositoryPort;

final class PdoParticipantClaimRepository implements ParticipantClaimRepositoryPort
{
    private const TABLE = 'jaktfelt_participant_claims';
    private const STATUS_PENDING = 'pending';

    public function __construct(private readonly \PDO $pdo)
    {
    }

    public function findById(int $id): ?array
    {
        if (!$this->tableExists()) {
            return null;
        }

        $stmt = $this->pdo->prepare('SELECT * FROM ' . self::TABLE . ' WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row === false ? null : $this->hydrate($row);
    }

    public function createPending(int $participantId, ?int $currentOwnerUserId, int $newOwnerUserId): array
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO ' . self::TABLE . ' (participant_id, current_owner_user_id, new_owner_user_id, status, created_at) VALUES (?, ?, ?, ?, NOW())'
        );
        $stmt->execute([$participantId, $currentOwnerUserId, $newOwnerUserId, self::STATUS_PENDING]);
        $id = (int) $this->pdo->lastInsertId();
        $row = $this->findById($id);
        if ($row === null) {
            throw new \RuntimeException('Claim not found after create');
        }

        return $row;
    }

    public function findPendingByParticipantAndNewOwner(int $participantId, int $newOwnerUserId): ?array
    {
        if (!$this->tableExists()) {
            return null;
        }

        $stmt = $this->pdo->prepare(
            'SELECT * FROM ' . self::TABLE . ' WHERE participant_id = ? AND new_owner_user_id = ? AND status = ? LIMIT 1'
        );
        $stmt->execute([$participantId, $newOwnerUserId, self::STATUS_PENDING]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row === false ? null : $this->hydrate($row);
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'participant_id' => (int) ($row['participant_id'] ?? 0),
            'current_owner_user_id' => isset($row['current_owner_user_id']) ? (int) $row['current_owner_user_id'] : null,
            'new_owner_user_id' => (int) ($row['new_owner_user_id'] ?? 0),
            'status' => (string) ($row['status'] ?? ''),
            'created_at' => $row['created_at'] ?? null,
        ];
    }

    private function tableExists(): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
        );
        $stmt->execute([self::TABLE]);

        return (int) $stmt->fetchColumn() > 0;
    }
}
