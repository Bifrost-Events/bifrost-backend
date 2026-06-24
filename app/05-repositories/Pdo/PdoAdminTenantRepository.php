<?php

declare(strict_types=1);

namespace App\Repositories\Pdo;

use App\Contracts\Repositories\AdminTenantRepositoryPort;

final class PdoAdminTenantRepository implements AdminTenantRepositoryPort
{
    public function __construct(private readonly \PDO $pdo)
    {
    }

    public function findAll(?array $tenantIdsFilter = null): array
    {
        if ($tenantIdsFilter === null) {
            $stmt = $this->pdo->query(
                'SELECT id, slug, name, tenant_type, status, created_at, updated_at
                 FROM tenants ORDER BY tenant_type ASC, name ASC'
            );

            return $stmt->fetchAll();
        }

        if ($tenantIdsFilter === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($tenantIdsFilter), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT id, slug, name, tenant_type, status, created_at, updated_at
             FROM tenants WHERE id IN ($placeholders) ORDER BY name ASC"
        );
        $stmt->execute(array_values($tenantIdsFilter));

        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, slug, name, tenant_type, status, created_at, updated_at
             FROM tenants WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public function create(array $data): array
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO tenants (slug, name, tenant_type, status)
             VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['slug'],
            $data['name'],
            $data['tenant_type'],
            $data['status'] ?? 'active',
        ]);
        $id = (int) $this->pdo->lastInsertId();

        if (($data['tenant_type'] ?? '') === 'cup') {
            $cupStmt = $this->pdo->prepare(
                'INSERT INTO cups (tenant_id, slug, name, status) VALUES (?, ?, ?, ?)'
            );
            $cupStmt->execute([
                $id,
                $data['slug'],
                $data['name'],
                $data['status'] ?? 'active',
            ]);
        }

        return $this->findById($id) ?? [];
    }

    public function update(int $id, array $data): ?array
    {
        $stmt = $this->pdo->prepare(
            'UPDATE tenants SET slug = ?, name = ?, tenant_type = ?, status = ?, updated_at = CURRENT_TIMESTAMP
             WHERE id = ?'
        );
        $stmt->execute([
            $data['slug'],
            $data['name'],
            $data['tenant_type'],
            $data['status'] ?? 'active',
            $id,
        ]);

        return $this->findById($id);
    }

    public function deactivate(int $id): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE tenants SET status = 'inactive', updated_at = CURRENT_TIMESTAMP WHERE id = ?"
        );
        $stmt->execute([$id]);

        return $stmt->rowCount() > 0;
    }

    public function slugExists(string $slug, ?int $excludeId = null): bool
    {
        if ($excludeId !== null) {
            $stmt = $this->pdo->prepare('SELECT 1 FROM tenants WHERE slug = ? AND id <> ? LIMIT 1');
            $stmt->execute([$slug, $excludeId]);
        } else {
            $stmt = $this->pdo->prepare('SELECT 1 FROM tenants WHERE slug = ? LIMIT 1');
            $stmt->execute([$slug]);
        }

        return (bool) $stmt->fetchColumn();
    }
}
