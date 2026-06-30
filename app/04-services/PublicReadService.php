<?php

declare(strict_types=1);

namespace App\Service;

use App\Contracts\Repositories\PublicReadRepositoryPort;
use App\Contracts\Repositories\TenantRepositoryPort;
use App\Support\PublicStandingsCalculator;

final class PublicReadService
{
    public function __construct(
        private readonly TenantRepositoryPort $tenants,
        private readonly PublicReadRepositoryPort $publicRead,
    ) {
    }

    /** @return array{ok: true, tenant: array<string, mixed>}|array{ok: false, error: string, status: int} */
    public function resolveTenant(string $host): array
    {
        $host = strtolower(trim(explode(':', $host)[0]));
        if ($host === '') {
            return ['ok' => false, 'error' => 'Missing host', 'status' => 400];
        }

        $tenant = $this->tenants->findByHost($host);
        if ($tenant === null) {
            return ['ok' => false, 'error' => 'Tenant not found for host', 'status' => 404];
        }

        return ['ok' => true, 'tenant' => $tenant];
    }

    /**
     * @return array{ok: true, data: array<string, mixed>}|array{ok: false, error: string, status: int}
     */
    public function calendar(string $host): array
    {
        $resolved = $this->resolveTenant($host);
        if (!$resolved['ok']) {
            return $resolved;
        }

        $tenantId = (int) ($resolved['tenant']['id'] ?? 0);

        return [
            'ok' => true,
            'data' => [
                'tenant' => $resolved['tenant'],
                'season' => $this->publicRead->findActiveSeasonForTenant($tenantId),
                'competitions' => $this->publicRead->listUpcomingCompetitions($tenantId),
            ],
        ];
    }

    /**
     * @return array{ok: true, data: array<string, mixed>}|array{ok: false, error: string, status: int}
     */
    public function resultsIndex(string $host): array
    {
        $resolved = $this->resolveTenant($host);
        if (!$resolved['ok']) {
            return $resolved;
        }

        $tenantId = (int) ($resolved['tenant']['id'] ?? 0);

        return [
            'ok' => true,
            'data' => [
                'tenant' => $resolved['tenant'],
                'competitions' => $this->publicRead->listCompetitionsWithResults($tenantId),
            ],
        ];
    }

    /**
     * @return array{ok: true, data: array<string, mixed>}|array{ok: false, error: string, status: int}
     */
    public function competition(int $competitionId, string $host): array
    {
        $resolved = $this->resolveTenant($host);
        if (!$resolved['ok']) {
            return $resolved;
        }

        $tenantId = (int) ($resolved['tenant']['id'] ?? 0);
        $competition = $this->publicRead->findCompetitionForTenant($tenantId, $competitionId);
        if ($competition === null) {
            return ['ok' => false, 'error' => 'Competition not found', 'status' => 404];
        }

        return [
            'ok' => true,
            'data' => [
                'tenant' => $resolved['tenant'],
                'competition' => $competition,
            ],
        ];
    }

    /**
     * @return array{ok: true, data: array<string, mixed>}|array{ok: false, error: string, status: int}
     */
    public function competitionResults(int $competitionId, string $host): array
    {
        $resolved = $this->competition($competitionId, $host);
        if (!$resolved['ok']) {
            return $resolved;
        }

        $rows = $this->publicRead->listCompetitionResults($competitionId);

        return [
            'ok' => true,
            'data' => [
                'tenant' => $resolved['data']['tenant'],
                'competition' => $resolved['data']['competition'],
                'results' => $this->sanitizePublicResults($rows),
            ],
        ];
    }

    /**
     * @return array{ok: true, data: array<string, mixed>}|array{ok: false, error: string, status: int}
     */
    public function standings(string $host): array
    {
        $resolved = $this->resolveTenant($host);
        if (!$resolved['ok']) {
            return $resolved;
        }

        $tenantId = (int) ($resolved['tenant']['id'] ?? 0);
        $season = $this->publicRead->findActiveSeasonForTenant($tenantId);
        if ($season === null) {
            return [
                'ok' => true,
                'data' => [
                    'tenant' => $resolved['tenant'],
                    'season' => null,
                    'standings' => null,
                ],
            ];
        }

        $seasonId = (int) ($season['id'] ?? 0);
        $competitions = $this->publicRead->listSeasonCompetitions($seasonId);
        $standings = PublicStandingsCalculator::computeTotalScore(
            $season,
            $competitions,
            fn (int $eventId): array => $this->publicRead->listCompetitionResults($eventId),
        );

        return [
            'ok' => true,
            'data' => [
                'tenant' => $resolved['tenant'],
                'season' => $season,
                'standings' => $standings,
            ],
        ];
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function sanitizePublicResults(array $rows): array
    {
        return array_map(static function (array $row): array {
            $row['name'] = trim((string) ($row['first_name'] ?? '') . ' ' . (string) ($row['last_name'] ?? ''));
            unset($row['first_name'], $row['last_name']);

            return $row;
        }, $rows);
    }
}
