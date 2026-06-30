<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

interface ParticipantClaimRepositoryPort
{
    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array;

    /** @return array<string, mixed> */
    public function createPending(int $participantId, ?int $currentOwnerUserId, int $newOwnerUserId): array;

    /** @return array<string, mixed>|null */
    public function findPendingByParticipantAndNewOwner(int $participantId, int $newOwnerUserId): ?array;
}
