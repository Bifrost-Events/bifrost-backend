<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

interface AdminTenantRepositoryPort
{
    /** @return list<array<string, mixed>> */
    public function findAll(?array $tenantIdsFilter = null): array;

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array;

    /** @param array<string, mixed> $data */
    public function create(array $data): array;

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): ?array;

    public function deactivate(int $id): bool;

    public function slugExists(string $slug, ?int $excludeId = null): bool;
}
