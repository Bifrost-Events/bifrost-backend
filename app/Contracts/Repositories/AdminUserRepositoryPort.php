<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

interface AdminUserRepositoryPort
{
    /** @return list<array<string, mixed>> */
    public function findAll(): array;

    /** @return list<array<string, mixed>> */
    public function search(string $query, int $limit = 50): array;

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array;

    /** @param array<string, mixed> $data */
    public function create(array $data): array;

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): ?array;

    public function deactivate(int $id): bool;

    public function emailExists(string $email, ?int $excludeId = null): bool;
}
