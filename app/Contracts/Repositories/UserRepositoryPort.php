<?php



declare(strict_types=1);



namespace App\Contracts\Repositories;



interface UserRepositoryPort

{

    /** @return array<string, mixed>|null */

    public function findByEmail(string $email): ?array;



    /** @return array<string, mixed>|null */

    public function findById(int $id): ?array;



    /** @return list<array<string, mixed>> */

    public function getSystemRoles(int $userId): array;



    /** @return list<array<string, mixed>> */

    public function getTenantAdminAccess(int $userId): array;



    public function touchLastLogin(int $userId): void;

}

