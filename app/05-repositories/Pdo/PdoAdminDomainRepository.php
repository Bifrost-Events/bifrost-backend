<?php

declare(strict_types=1);

namespace App\Repositories\Pdo;

use App\Contracts\Repositories\AdminDomainRepositoryPort;

final class PdoAdminDomainRepository implements AdminDomainRepositoryPort
{
    public function __construct(private readonly \PDO $pdo)
    {
    }

    public function findByTenantId(int $tenantId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, tenant_id, host, purpose, is_primary, created_at
             FROM tenant_domains WHERE tenant_id = ? ORDER BY is_primary DESC, host ASC'
        );
        $stmt->execute([$tenantId]);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$row) {
            $row['is_primary'] = (bool) $row['is_primary'];
        }

        return $rows;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, tenant_id, host, purpose, is_primary, created_at
             FROM tenant_domains WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }
        $row['is_primary'] = (bool) $row['is_primary'];

        return $row;
    }

    public function create(array $data): array
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO tenant_domains (tenant_id, host, purpose, is_primary)
             VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['tenant_id'],
            strtolower(trim((string) $data['host'])),
            $data['purpose'],
            !empty($data['is_primary']) ? 1 : 0,
        ]);
        $id = (int) $this->pdo->lastInsertId();

        return $this->findById($id) ?? [];
    }

    public function update(int $id, array $data): ?array
    {
        $stmt = $this->pdo->prepare(
            'UPDATE tenant_domains SET host = ?, purpose = ?, is_primary = ? WHERE id = ?'
        );
        $stmt->execute([
            strtolower(trim((string) $data['host'])),
            $data['purpose'],
            !empty($data['is_primary']) ? 1 : 0,
            $id,
        ]);

        return $this->findById($id);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM tenant_domains WHERE id = ?');
        $stmt->execute([$id]);

        return $stmt->rowCount() > 0;
    }

    public function hostExists(string $host, ?int $excludeId = null): bool
    {
        $host = strtolower(trim($host));
        if ($excludeId !== null) {
            $stmt = $this->pdo->prepare('SELECT 1 FROM tenant_domains WHERE host = ? AND id <> ? LIMIT 1');
            $stmt->execute([$host, $excludeId]);
        } else {
            $stmt = $this->pdo->prepare('SELECT 1 FROM tenant_domains WHERE host = ? LIMIT 1');
            $stmt->execute([$host]);
        }

        return (bool) $stmt->fetchColumn();
    }
}
