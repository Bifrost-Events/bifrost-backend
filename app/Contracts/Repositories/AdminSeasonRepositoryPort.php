<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

interface AdminSeasonRepositoryPort
{
    /** @return list<array<string, mixed>> */
    public function findAllWithStructure(?array $tenantIds = null): array;

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array;

    /** @param array<string, mixed> $data @return array<string, mixed> */
    public function create(array $data): array;

    /** @param array<string, mixed> $data @return array<string, mixed>|null */
    public function update(int $id, array $data): ?array;

    /**
     * @param array<int, float> $placementPointsByPlace
     * @param list<int>|null $cupCompetitionIds
     */
    public function updateCupStandings(
        int $id,
        string $mode,
        array $placementPointsByPlace,
        ?array $cupCompetitionIds,
        int $cupStandingsCountBest,
    ): void;

    /** @param array<string, mixed> $data @return array<string, mixed> */
    public function createRound(int $seasonId, array $data): array;

    /** @return list<array<string, mixed>> */
    public function listCompetitionsForSeason(int $seasonId): array;
}
