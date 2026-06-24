<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

interface AdminOrganizationRepositoryPort
{
    /**
     * @param list<int>|null $tenantIds
     * @return list<array<string, mixed>>
     */
    public function findAll(?array $tenantIds = null, ?string $search = null, int $limit = 100): array;

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array;

    /** @param array<string, mixed> $data */
    public function create(array $data): array;

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): ?array;

    public function deactivate(int $id): bool;

    /** @return list<array<string, mixed>> */
    public function listMembers(int $organizationId): array;

    /** @return array<string, mixed>|null */
    public function addMember(int $organizationId, int $authUserId, string $role): ?array;

    public function removeMember(int $memberId): bool;

    /** @return array<string, mixed>|null */
    public function findMemberById(int $memberId): ?array;
}
