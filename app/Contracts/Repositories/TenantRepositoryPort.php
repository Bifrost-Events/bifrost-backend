<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

interface TenantRepositoryPort
{
    /** @return list<array<string, mixed>> */
    public function findAllWithDomains(): array;

    /** @return array<string, mixed>|null */
    public function findByIdWithDomains(int $id): ?array;

    /** @return array<string, mixed>|null */
    public function findByHost(string $host): ?array;
}
