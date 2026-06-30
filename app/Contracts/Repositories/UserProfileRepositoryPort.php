<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

interface UserProfileRepositoryPort
{
    /** @return array<string, mixed>|null */
    public function get(string $userId): ?array;

    /** @param array<string, mixed> $data */
    public function save(string $userId, array $data): void;
}
