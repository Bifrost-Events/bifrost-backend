<?php

declare(strict_types=1);

namespace App\Repositories\Pdo;

use App\Contracts\Repositories\AdminAccessRepositoryPort;

final class PdoAdminAccessRepository implements AdminAccessRepositoryPort
{
    public function __construct(private readonly \PDO $pdo)
    {
    }

    public function listRoleDefinitions(): array
    {
        return [
            'SystemAdmin',
            'CupAdmin',
            'Organizer',
        ];
    }

    public function listAssignmentsForSystemRole(string $role): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT sr.id AS assignment_id, sr.auth_user_id AS user_id, sr.role, sr.created_at,
                    u.name AS user_name, u.email AS user_email, u.is_active AS user_is_active
             FROM auth_system_roles sr
             INNER JOIN auth_users u ON u.id = sr.auth_user_id
             WHERE sr.role = ?
             ORDER BY u.name, u.email'
        );
        $stmt->execute([$role]);

        return $stmt->fetchAll();
    }

    public function listAssignmentsForTenantRole(string $role, ?array $tenantIds = null): array
    {
        $sql = 'SELECT ata.id AS assignment_id, ata.auth_user_id AS user_id, ata.tenant_id, ata.role, ata.created_at,
                       u.name AS user_name, u.email AS user_email, u.is_active AS user_is_active,
                       t.slug AS tenant_slug, t.name AS tenant_name
                FROM auth_tenant_admin_access ata
                INNER JOIN auth_users u ON u.id = ata.auth_user_id
                INNER JOIN tenants t ON t.id = ata.tenant_id
                WHERE ata.role = ?';
        $params = [$role];

        if ($tenantIds !== null && $tenantIds !== []) {
            $placeholders = implode(',', array_fill(0, count($tenantIds), '?'));
            $sql .= ' AND ata.tenant_id IN (' . $placeholders . ')';
            $params = array_merge($params, $tenantIds);
        }

        $sql .= ' ORDER BY t.name, u.name, u.email';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function getUserAccess(int $authUserId): array
    {
        $systemStmt = $this->pdo->prepare(
            'SELECT id, role, created_at FROM auth_system_roles WHERE auth_user_id = ? ORDER BY role'
        );
        $systemStmt->execute([$authUserId]);
        $systemRoles = $systemStmt->fetchAll();

        $tenantStmt = $this->pdo->prepare(
            'SELECT ata.id, ata.auth_user_id, ata.tenant_id, ata.role, ata.created_at,
                    t.slug AS tenant_slug, t.name AS tenant_name
             FROM auth_tenant_admin_access ata
             INNER JOIN tenants t ON t.id = ata.tenant_id
             WHERE ata.auth_user_id = ?
             ORDER BY t.slug, ata.role'
        );
        $tenantStmt->execute([$authUserId]);
        $tenantAccess = $tenantStmt->fetchAll();

        return [
            'system_roles' => $systemRoles,
            'tenant_admin_access' => $tenantAccess,
        ];
    }

    public function grantSystemRole(int $authUserId, string $role): bool
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO auth_system_roles (auth_user_id, role) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE created_at = auth_system_roles.created_at'
        );
        $stmt->execute([$authUserId, $role]);

        return true;
    }

    public function revokeSystemRole(int $authUserId, string $role): bool
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM auth_system_roles WHERE auth_user_id = ? AND role = ?'
        );
        $stmt->execute([$authUserId, $role]);

        return $stmt->rowCount() > 0;
    }

    public function grantTenantAccess(int $authUserId, int $tenantId, string $role): ?array
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO auth_tenant_admin_access (auth_user_id, tenant_id, role)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE created_at = auth_tenant_admin_access.created_at'
        );
        $stmt->execute([$authUserId, $tenantId, $role]);

        $find = $this->pdo->prepare(
            'SELECT ata.id, ata.auth_user_id, ata.tenant_id, ata.role, ata.created_at,
                    t.slug AS tenant_slug, t.name AS tenant_name
             FROM auth_tenant_admin_access ata
             INNER JOIN tenants t ON t.id = ata.tenant_id
             WHERE ata.auth_user_id = ? AND ata.tenant_id = ? AND ata.role = ? LIMIT 1'
        );
        $find->execute([$authUserId, $tenantId, $role]);
        $row = $find->fetch();

        return $row === false ? null : $row;
    }

    public function revokeTenantAccess(int $accessId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM auth_tenant_admin_access WHERE id = ?');
        $stmt->execute([$accessId]);

        return $stmt->rowCount() > 0;
    }

    public function findTenantAccessById(int $accessId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, auth_user_id, tenant_id, role, created_at FROM auth_tenant_admin_access WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$accessId]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }
}
