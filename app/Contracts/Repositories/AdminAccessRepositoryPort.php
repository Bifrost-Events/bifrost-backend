<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

interface AdminAccessRepositoryPort
{
    /** @return list<string> */
    public function listRoleDefinitions(): array;

    /** @return list<array<string, mixed>> */
    public function listAssignmentsForSystemRole(string $role): array;

    /**
     * @param list<int>|null $tenantIds
     * @return list<array<string, mixed>>
     */
    public function listAssignmentsForTenantRole(string $role, ?array $tenantIds = null): array;

    /** @return array{system_roles: list<array<string, mixed>>, tenant_admin_access: list<array<string, mixed>>} */
    public function getUserAccess(int $authUserId): array;

    public function grantSystemRole(int $authUserId, string $role): bool;

    public function revokeSystemRole(int $authUserId, string $role): bool;

    /** @return array<string, mixed>|null */
    public function grantTenantAccess(int $authUserId, int $tenantId, string $role): ?array;

    public function revokeTenantAccess(int $accessId): bool;

    /** @return array<string, mixed>|null */
    public function findTenantAccessById(int $accessId): ?array;
}
