<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

interface AdminDomainRepositoryPort
{
    /** @return list<array<string, mixed>> */
    public function findByTenantId(int $tenantId): array;

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array;

    /** @param array<string, mixed> $data */
    public function create(array $data): array;

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): ?array;

    public function delete(int $id): bool;

    public function hostExists(string $host, ?int $excludeId = null): bool;
}
