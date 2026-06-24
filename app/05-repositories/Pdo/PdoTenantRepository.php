<?php

declare(strict_types=1);

namespace App\Repositories\Pdo;

use App\Contracts\Repositories\TenantRepositoryPort;

final class PdoTenantRepository implements TenantRepositoryPort
{
    public function __construct(private readonly \PDO $pdo)
    {
    }

    public function findAllWithDomains(): array
    {
        $tenants = $this->fetchTenants(null);
        $domainsByTenant = $this->fetchDomainsGrouped();

        return array_map(
            fn (array $tenant): array => $this->attachDomains($tenant, $domainsByTenant),
            $tenants
        );
    }

    public function findByIdWithDomains(int $id): ?array
    {
        $tenants = $this->fetchTenants($id);
        if ($tenants === []) {
            return null;
        }

        $domainsByTenant = $this->fetchDomainsGrouped($id);

        return $this->attachDomains($tenants[0], $domainsByTenant);
    }

    public function findByHost(string $host): ?array
    {
        $host = strtolower(trim($host));
        if ($host === '') {
            return null;
        }

        $stmt = $this->pdo->prepare(
            'SELECT tenant_id FROM tenant_domains WHERE host = ? LIMIT 1'
        );
        $stmt->execute([$host]);
        $tenantId = $stmt->fetchColumn();
        if ($tenantId === false) {
            return null;
        }

        return $this->findByIdWithDomains((int) $tenantId);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchTenants(?int $id): array
    {
        if ($id !== null) {
            $stmt = $this->pdo->prepare(
                'SELECT id, slug, name, tenant_type, status, created_at, updated_at
                 FROM tenants WHERE id = ?'
            );
            $stmt->execute([$id]);
            $row = $stmt->fetch();

            return $row === false ? [] : [$row];
        }

        $stmt = $this->pdo->query(
            'SELECT id, slug, name, tenant_type, status, created_at, updated_at
             FROM tenants ORDER BY id ASC'
        );

        return $stmt->fetchAll();
    }

    /**
     * @return array<int, list<array<string, mixed>>>
     */
    private function fetchDomainsGrouped(?int $tenantId = null): array
    {
        if ($tenantId !== null) {
            $stmt = $this->pdo->prepare(
                'SELECT id, tenant_id, host, purpose, is_primary, created_at
                 FROM tenant_domains WHERE tenant_id = ? ORDER BY is_primary DESC, host ASC'
            );
            $stmt->execute([$tenantId]);
        } else {
            $stmt = $this->pdo->query(
                'SELECT id, tenant_id, host, purpose, is_primary, created_at
                 FROM tenant_domains ORDER BY tenant_id ASC, is_primary DESC, host ASC'
            );
        }

        $grouped = [];
        foreach ($stmt->fetchAll() as $row) {
            $tid = (int) $row['tenant_id'];
            $row['is_primary'] = (bool) $row['is_primary'];
            $grouped[$tid][] = [
                'id' => (int) $row['id'],
                'host' => $row['host'],
                'purpose' => $row['purpose'],
                'is_primary' => $row['is_primary'],
                'created_at' => $row['created_at'],
            ];
        }

        return $grouped;
    }

    /**
     * @param array<string, mixed> $tenant
     * @param array<int, list<array<string, mixed>>> $domainsByTenant
     * @return array<string, mixed>
     */
    private function attachDomains(array $tenant, array $domainsByTenant): array
    {
        $tenantId = (int) $tenant['id'];
        $tenant['id'] = $tenantId;
        $tenant['domains'] = $domainsByTenant[$tenantId] ?? [];

        return $tenant;
    }
}
