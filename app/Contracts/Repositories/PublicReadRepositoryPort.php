<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

interface PublicReadRepositoryPort
{
  /** @return array<string, mixed>|null */
    public function findActiveSeasonForTenant(int $tenantId): ?array;

    /** @return list<array<string, mixed>> */
    public function listUpcomingCompetitions(int $tenantId): array;

    /** @return list<array<string, mixed>> */
    public function listCompetitionsWithResults(int $tenantId): array;

    /** @return array<string, mixed>|null */
    public function findCompetitionForTenant(int $tenantId, int $competitionId): ?array;

    /** @return list<array<string, mixed>> */
    public function listCompetitionResults(int $competitionId): array;

    /** @return list<array<string, mixed>> */
    public function listSeasonCompetitions(int $seasonId): array;
}
