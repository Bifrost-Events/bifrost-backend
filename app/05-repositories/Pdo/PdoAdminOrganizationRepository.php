<?php

declare(strict_types=1);

namespace App\Repositories\Pdo;

use App\Contracts\Repositories\AdminOrganizationRepositoryPort;

final class PdoAdminOrganizationRepository implements AdminOrganizationRepositoryPort
{
    private const SELECT_ORG = 'o.id, o.tenant_id, o.legacy_jaktfelt_organizer_id, o.name, o.organization_number,
        o.organization_type, o.contact_person, o.email, o.phone, o.postal_code, o.city, o.districts_json,
        o.status, o.created_at, o.updated_at, t.slug AS tenant_slug, t.name AS tenant_name';

    public function __construct(private readonly \PDO $pdo)
    {
    }

    public function findAll(?array $tenantIds = null, ?string $search = null, int $limit = 100): array
    {
        $limit = max(1, min(200, $limit));
        $sql = 'SELECT ' . self::SELECT_ORG . '
                FROM organizations o
                INNER JOIN tenants t ON t.id = o.tenant_id
                WHERE 1=1';
        $params = [];

        if ($tenantIds !== null) {
            if ($tenantIds === []) {
                return [];
            }
            $placeholders = implode(',', array_fill(0, count($tenantIds), '?'));
            $sql .= " AND o.tenant_id IN ($placeholders)";
            $params = array_merge($params, array_values($tenantIds));
        }

        if ($search !== null && trim($search) !== '') {
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], trim($search)) . '%';
            $sql .= ' AND (o.name LIKE ? OR COALESCE(o.organization_number, \'\') LIKE ? OR COALESCE(o.city, \'\') LIKE ?)';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $sql .= ' ORDER BY t.name, o.name LIMIT ' . $limit;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return array_map($this->hydrateOrganization(...), $stmt->fetchAll());
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT ' . self::SELECT_ORG . '
             FROM organizations o
             INNER JOIN tenants t ON t.id = o.tenant_id
             WHERE o.id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row === false ? null : $this->hydrateOrganization($row);
    }

    public function create(array $data): array
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO organizations (
                tenant_id, legacy_jaktfelt_organizer_id, name, organization_number, organization_type,
                contact_person, email, phone, postal_code, city, districts_json, status
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            (int) $data['tenant_id'],
            $data['legacy_jaktfelt_organizer_id'] ?? null,
            $data['name'],
            $data['organization_number'] ?? null,
            $data['organization_type'] ?? 'skytterlag',
            $data['contact_person'] ?? null,
            $data['email'] ?? null,
            $data['phone'] ?? null,
            $data['postal_code'] ?? null,
            $data['city'] ?? null,
            $this->encodeDistricts($data['districts'] ?? []),
            $data['status'] ?? 'active',
        ]);

        return $this->findById((int) $this->pdo->lastInsertId()) ?? [];
    }

    public function update(int $id, array $data): ?array
    {
        $stmt = $this->pdo->prepare(
            'UPDATE organizations SET
                name = ?, organization_number = ?, organization_type = ?,
                contact_person = ?, email = ?, phone = ?, postal_code = ?, city = ?,
                districts_json = ?, status = ?, updated_at = CURRENT_TIMESTAMP
             WHERE id = ?'
        );
        $stmt->execute([
            $data['name'],
            $data['organization_number'] ?? null,
            $data['organization_type'] ?? 'skytterlag',
            $data['contact_person'] ?? null,
            $data['email'] ?? null,
            $data['phone'] ?? null,
            $data['postal_code'] ?? null,
            $data['city'] ?? null,
            $this->encodeDistricts($data['districts'] ?? []),
            $data['status'] ?? 'active',
            $id,
        ]);

        return $this->findById($id);
    }

    public function deactivate(int $id): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE organizations SET status = 'inactive', updated_at = CURRENT_TIMESTAMP WHERE id = ?"
        );
        $stmt->execute([$id]);

        return $stmt->rowCount() > 0;
    }

    public function listMembers(int $organizationId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT m.id, m.organization_id, m.auth_user_id, m.role, m.created_at,
                    u.name AS user_name, u.email AS user_email, u.is_active AS user_is_active
             FROM organization_members m
             INNER JOIN auth_users u ON u.id = m.auth_user_id
             WHERE m.organization_id = ?
             ORDER BY m.role, u.name, u.email'
        );
        $stmt->execute([$organizationId]);

        return $stmt->fetchAll();
    }

    public function addMember(int $organizationId, int $authUserId, string $role): ?array
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO organization_members (organization_id, auth_user_id, role)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE role = VALUES(role)'
        );
        $stmt->execute([$organizationId, $authUserId, $role]);

        $find = $this->pdo->prepare(
            'SELECT m.id, m.organization_id, m.auth_user_id, m.role, m.created_at,
                    u.name AS user_name, u.email AS user_email, u.is_active AS user_is_active
             FROM organization_members m
             INNER JOIN auth_users u ON u.id = m.auth_user_id
             WHERE m.organization_id = ? AND m.auth_user_id = ? LIMIT 1'
        );
        $find->execute([$organizationId, $authUserId]);
        $row = $find->fetch();

        return $row === false ? null : $row;
    }

    public function removeMember(int $memberId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM organization_members WHERE id = ?');
        $stmt->execute([$memberId]);

        return $stmt->rowCount() > 0;
    }

    public function findMemberById(int $memberId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, organization_id, auth_user_id, role, created_at
             FROM organization_members WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$memberId]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function hydrateOrganization(array $row): array
    {
        $districts = [];
        $raw = $row['districts_json'] ?? null;
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $districts = array_values(array_filter(
                    array_map(static fn ($v): string => trim((string) $v), $decoded),
                    static fn (string $v): bool => $v !== ''
                ));
            }
        }
        $row['districts'] = $districts;
        unset($row['districts_json']);

        return $row;
    }

    /** @param array<int, string>|list<string> $districts */
    private function encodeDistricts(array $districts): ?string
    {
        $clean = array_values(array_unique(array_filter(
            array_map(static fn ($v): string => trim((string) $v), $districts),
            static fn (string $v): bool => $v !== ''
        )));
        if ($clean === []) {
            return null;
        }

        return json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
