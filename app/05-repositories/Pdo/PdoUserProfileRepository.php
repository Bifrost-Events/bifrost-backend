<?php

declare(strict_types=1);

namespace App\Repositories\Pdo;

use App\Contracts\Repositories\UserProfileRepositoryPort;

final class PdoUserProfileRepository implements UserProfileRepositoryPort
{
    private const TABLE = 'jaktfelt_user_profiles';

    public function __construct(private readonly \PDO $pdo)
    {
    }

    public function get(string $userId): ?array
    {
        if (!$this->tableExists()) {
            return null;
        }

        $stmt = $this->pdo->prepare('SELECT * FROM ' . self::TABLE . ' WHERE user_id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }

        $out = [];
        foreach ([
            'phone', 'first_name', 'last_name', 'date_of_birth',
            'user_agreement_version', 'user_agreement_accepted_at',
            'organizer_agreement_version', 'organizer_agreement_accepted_at', 'profile_note',
        ] as $col) {
            if (array_key_exists($col, $row) && $row[$col] !== null) {
                $out[$col] = $row[$col];
            }
        }

        return $out !== [] ? $out : null;
    }

    public function save(string $userId, array $data): void
    {
        if (!$this->tableExists()) {
            return;
        }

        $allowed = [
            'phone', 'first_name', 'last_name', 'date_of_birth',
            'user_agreement_version', 'user_agreement_accepted_at',
            'organizer_agreement_version', 'organizer_agreement_accepted_at', 'profile_note',
        ];
        $filtered = array_intersect_key($data, array_flip($allowed));
        if ($filtered === []) {
            return;
        }

        $existing = $this->get($userId) ?? [];
        $merged = array_merge($existing, $filtered);
        $cols = ['user_id'];
        $placeholders = ['?'];
        $values = [$userId];
        foreach ($allowed as $col) {
            $cols[] = $col;
            $placeholders[] = '?';
            $values[] = $merged[$col] ?? null;
        }
        $updates = array_map(static fn (string $col): string => $col . ' = VALUES(' . $col . ')', $allowed);
        $updates[] = 'updated_at = NOW()';
        $sql = 'INSERT INTO ' . self::TABLE . ' (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $placeholders) . ')'
            . ' ON DUPLICATE KEY UPDATE ' . implode(', ', $updates);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($values);
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
