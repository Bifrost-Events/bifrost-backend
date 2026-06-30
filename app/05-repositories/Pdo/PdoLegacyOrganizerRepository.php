<?php

declare(strict_types=1);

namespace App\Repositories\Pdo;

use App\Support\DistriktTagParser;

/** Legacy jaktfelt_organizers_v2 + jaktfelt_organizer_members for stevner-kompatibilitet. */
final class PdoLegacyOrganizerRepository
{
    private const TABLE = 'jaktfelt_organizers_v2';
    private const MEMBERS_TABLE = 'jaktfelt_organizer_members';

    public function __construct(private readonly \PDO $pdo)
    {
    }

    public function tableExists(): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
        );
        $stmt->execute([self::TABLE]);

        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * @param list<string> $districts
     * @return array<string, mixed>
     */
    public function create(
        string $name,
        ?string $organizationNumber,
        string $organizationType,
        ?string $contactPerson,
        ?string $email,
        ?string $phone,
        ?string $postalCode,
        ?string $city,
        array $districts,
    ): array {
        $districtsJson = DistriktTagParser::encodeForStorage($districts);
        $stmt = $this->pdo->prepare(
            'INSERT INTO ' . self::TABLE . ' (name, organization_number, organization_type, contact_person, email, phone, postal_code, city, districts) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $name,
            $organizationNumber ?: null,
            $organizationType,
            $contactPerson ?: null,
            $email ?: null,
            $phone ?: null,
            $postalCode,
            $city,
            $districtsJson,
        ]);
        $id = (int) $this->pdo->lastInsertId();
        $row = $this->findById($id);
        if ($row === null) {
            throw new \RuntimeException('Organizer not found after create');
        }

        return $row;
    }

    public function addMember(int $organizerId, int $userId, string $role): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO ' . self::MEMBERS_TABLE . ' (organizer_id, user_id, role) VALUES (?, ?, ?)'
        );
        $stmt->execute([$organizerId, $userId, $role]);
    }

    /** @return list<array<string, mixed>> */
    public function listByUserId(int $userId): array
    {
        if (!$this->tableExists()) {
            return [];
        }

        $stmt = $this->pdo->prepare(
            'SELECT o.id, o.name, o.organization_number, o.organization_type, o.contact_person, o.email, o.phone, o.postal_code, o.city, o.districts, m.role
             FROM ' . self::TABLE . ' o
             JOIN ' . self::MEMBERS_TABLE . ' m ON m.organizer_id = o.id
             WHERE m.user_id = ?
             ORDER BY o.name'
        );
        $stmt->execute([$userId]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        return array_map(static function (array $row): array {
            $decoded = json_decode((string) ($row['districts'] ?? ''), true);

            return [
                'id' => (int) ($row['id'] ?? 0),
                'name' => (string) ($row['name'] ?? ''),
                'organization_number' => $row['organization_number'] ?? null,
                'organization_type' => (string) ($row['organization_type'] ?? 'skytterlag'),
                'contact_person' => $row['contact_person'] ?? null,
                'email' => $row['email'] ?? null,
                'phone' => $row['phone'] ?? null,
                'postal_code' => $row['postal_code'] ?? null,
                'city' => $row['city'] ?? null,
                'districts' => is_array($decoded) ? $decoded : [],
                'role' => (string) ($row['role'] ?? 'OWNER'),
            ];
        }, $rows);
    }

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM ' . self::TABLE . ' WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }
}
