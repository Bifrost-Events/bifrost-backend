<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

interface ParticipantRepositoryPort
{
    /** @return list<array<string, mixed>> */
    public function listByOwnerUserId(int $ownerUserId): array;

    /** @return array<string, mixed>|null */
    public function findRowById(int $id): ?array;

    public function jaktfeltIdExists(string $value): bool;

  /** @return array<string, mixed> */
    public function createForUser(
        int $ownerUserId,
        string $firstName,
        string $lastName,
        int $classId,
        ?\DateTimeInterface $dateOfBirth,
        ?string $phone,
        ?string $club,
    ): array;

    public function updateOwned(
        int $id,
        int $ownerUserId,
        string $firstName,
        string $lastName,
        int $classId,
        ?\DateTimeInterface $dateOfBirth,
        ?string $phone,
        ?string $club,
    ): void;

    public function addJaktfeltId(int $participantId, string $jaktfeltIdValue): void;

    /** @return list<string> */
    public function listDistinctClubs(int $limit = 200): array;

    /** @return list<array<string, mixed>> */
    public function listClasses(): array;

    /** @return array<string, mixed>|null */
    public function findByNamesAndPhone(string $firstName, string $lastName, ?string $phone): ?array;

    public function getJaktfeltId(int $participantId): ?string;

    /** @return array<string, mixed>|null */
    public function findClassByCode(string $code): ?array;

    public function transferOwnership(int $participantId, int $newOwnerUserId): void;
}
