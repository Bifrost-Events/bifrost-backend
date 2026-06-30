<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

interface SignupRepositoryPort
{
    /** @return list<array<string, mixed>> */
    public function listSlotsByCompetitionId(int $competitionId): array;

    /** @return list<array<string, mixed>> */
    public function listRegistrationsByEventId(int $eventId): array;

    /** @return list<array<string, mixed>> */
    public function listReservedPlacesByEventId(int $eventId): array;

    public function hasRegistration(int $eventId, int $participantId): bool;

    public function isPlaceReserved(int $eventId, int $slotId, int $figureNumber): bool;

    /** @return array<string, mixed>|null */
    public function findSlotById(int $slotId): ?array;

    public function createRegistration(
        int $eventId,
        int $participantId,
        ?int $slotId,
        ?int $figureNumber,
        string $registeredVia,
        ?int $registeredByUserId,
    ): void;

    public function cancelRegistration(int $eventId, int $participantId): void;

    /** @return list<array<string, mixed>> */
    public function listSignupsForUserInTenant(int $userId, int $tenantId): array;

    /** @return array<string, mixed>|null */
    public function findOrganizerById(int $organizerId): ?array;
}
