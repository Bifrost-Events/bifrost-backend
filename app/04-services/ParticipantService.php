<?php

declare(strict_types=1);

namespace App\Service;

use App\Contracts\Repositories\ParticipantRepositoryPort;
use App\Contracts\Repositories\PublicReadRepositoryPort;
use App\Contracts\Repositories\SignupRepositoryPort;
use App\Contracts\Repositories\TenantRepositoryPort;
use App\Support\AdvanceRegistration;
use App\Support\JaktfeltId;

final class ParticipantService
{
    public function __construct(
        private readonly TenantRepositoryPort $tenants,
        private readonly PublicReadRepositoryPort $publicRead,
        private readonly ParticipantRepositoryPort $participants,
        private readonly SignupRepositoryPort $signups,
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
    public function listClasses(): array
    {
        return ['ok' => true, 'data' => ['classes' => $this->participants->listClasses()]];
    }

    /**
     * @return array{ok: true, data: array<string, mixed>}|array{ok: false, error: string, status: int}
     */
    public function listShooters(int $userId): array
    {
        return [
            'ok' => true,
            'data' => [
                'participants' => $this->participants->listByOwnerUserId($userId),
                'classes' => $this->participants->listClasses(),
                'club_suggestions' => $this->participants->listDistinctClubs(),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok: true, data: array<string, mixed>}|array{ok: false, error: string, status: int}
     */
    public function createShooter(int $userId, array $input): array
    {
        $firstName = trim((string) ($input['first_name'] ?? ''));
        $lastName = trim((string) ($input['last_name'] ?? ''));
        $classId = (int) ($input['class_id'] ?? 0);
        $club = trim((string) ($input['club'] ?? ''));
        $phone = trim((string) ($input['phone'] ?? ''));
        $dobRaw = trim((string) ($input['date_of_birth'] ?? ''));

        if ($firstName === '' || $lastName === '') {
            return ['ok' => false, 'error' => 'Fornavn og etternavn er påkrevd', 'status' => 422];
        }
        if ($classId <= 0) {
            return ['ok' => false, 'error' => 'Klasse er påkrevd', 'status' => 422];
        }

        $dateOfBirth = $this->parseDate($dobRaw);
        if ($dobRaw !== '' && $dateOfBirth === false) {
            return ['ok' => false, 'error' => 'Ugyldig fødselsdato', 'status' => 422];
        }

        try {
            $jaktfeltId = $this->generateUniqueJaktfeltId();
            $participant = $this->participants->createForUser(
                $userId,
                $firstName,
                $lastName,
                $classId,
                $dateOfBirth instanceof \DateTimeInterface ? $dateOfBirth : null,
                $phone !== '' ? $phone : null,
                $club !== '' ? $club : null,
            );
            $this->participants->addJaktfeltId((int) $participant['id'], $jaktfeltId->value);
            $participant['jaktfelt_id'] = $jaktfeltId->value;

            return ['ok' => true, 'data' => ['participant' => $participant]];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'Kunne ikke opprette deltaker: ' . $e->getMessage(), 'status' => 500];
        }
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok: true, data: array<string, mixed>}|array{ok: false, error: string, status: int}
     */
    public function updateShooter(int $userId, int $participantId, array $input): array
    {
        $firstName = trim((string) ($input['first_name'] ?? ''));
        $lastName = trim((string) ($input['last_name'] ?? ''));
        $classId = (int) ($input['class_id'] ?? 0);
        $club = trim((string) ($input['club'] ?? ''));
        $phone = trim((string) ($input['phone'] ?? ''));
        $dobRaw = trim((string) ($input['date_of_birth'] ?? ''));

        if ($participantId <= 0 || $firstName === '' || $lastName === '') {
            return ['ok' => false, 'error' => 'Fornavn og etternavn er påkrevd', 'status' => 422];
        }

        $dateOfBirth = $this->parseDate($dobRaw);
        if ($dobRaw !== '' && $dateOfBirth === false) {
            return ['ok' => false, 'error' => 'Ugyldig fødselsdato', 'status' => 422];
        }

        try {
            $this->participants->updateOwned(
                $participantId,
                $userId,
                $firstName,
                $lastName,
                $classId,
                $dateOfBirth instanceof \DateTimeInterface ? $dateOfBirth : null,
                $phone !== '' ? $phone : null,
                $club !== '' ? $club : null,
            );

            $updated = null;
            foreach ($this->participants->listByOwnerUserId($userId) as $row) {
                if ((int) ($row['id'] ?? 0) === $participantId) {
                    $updated = $row;
                    break;
                }
            }

            return ['ok' => true, 'data' => ['participant' => $updated]];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage(), 'status' => 403];
        }
    }

    /**
     * @return array{ok: true, data: array<string, mixed>}|array{ok: false, error: string, status: int}
     */
    public function competitionSignup(int $competitionId, string $host, ?int $userId): array
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

        $organizer = null;
        $organizerId = (int) ($competition['organizer_id'] ?? 0);
        if ($organizerId > 0) {
            $organizer = $this->signups->findOrganizerById($organizerId);
        }

        $registrations = $this->signups->listRegistrationsByEventId($competitionId);
        $myParticipantIds = [];
        if ($userId !== null && $userId > 0) {
            foreach ($registrations as $reg) {
                $pid = (int) ($reg['participant_id'] ?? 0);
                $regBy = $reg['registered_by_user_id'] ?? null;
                if ($pid > 0 && $regBy !== null && (int) $regBy === $userId) {
                    $myParticipantIds[] = $pid;
                }
            }
            $ownedIds = array_map(static fn (array $p): int => (int) ($p['id'] ?? 0), $this->participants->listByOwnerUserId($userId));
            foreach ($ownedIds as $oid) {
                if ($this->signups->hasRegistration($competitionId, $oid)) {
                    $myParticipantIds[] = $oid;
                }
            }
            $myParticipantIds = array_values(array_unique(array_filter($myParticipantIds)));
        }

        $data = [
            'tenant' => $resolved['tenant'],
            'competition' => $competition,
            'registration_open' => AdvanceRegistration::isOpenForPublic($competition),
            'advance_registration_enabled' => AdvanceRegistration::isEnabled($competition),
            'slots' => $this->signups->listSlotsByCompetitionId($competitionId),
            'registrations' => $registrations,
            'reserved_places' => $this->signups->listReservedPlacesByEventId($competitionId),
            'organizer' => $organizer,
            'my_participant_ids' => $myParticipantIds,
        ];

        if ($userId !== null && $userId > 0) {
            $data['participants'] = $this->participants->listByOwnerUserId($userId);
            $data['classes'] = $this->participants->listClasses();
        }

        return ['ok' => true, 'data' => $data];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok: true, data: array<string, mixed>}|array{ok: false, error: string, status: int}
     */
    public function register(int $userId, string $host, array $input): array
    {
        $resolved = $this->resolveTenant($host);
        if (!$resolved['ok']) {
            return $resolved;
        }

        $competitionId = (int) ($input['competition_id'] ?? 0);
        $participantId = (int) ($input['participant_id'] ?? 0);
        $slotId = !empty($input['slot_id']) ? (int) $input['slot_id'] : null;
        $figureNumber = !empty($input['figure_number']) ? (int) $input['figure_number'] : null;

        if ($competitionId <= 0 || $participantId <= 0) {
            return ['ok' => false, 'error' => 'competition_id og participant_id er påkrevd', 'status' => 422];
        }

        $tenantId = (int) ($resolved['tenant']['id'] ?? 0);
        $competition = $this->publicRead->findCompetitionForTenant($tenantId, $competitionId);
        if ($competition === null) {
            return ['ok' => false, 'error' => 'Stevnet finnes ikke', 'status' => 404];
        }

        if (!AdvanceRegistration::isOpenForPublic($competition)) {
            return ['ok' => false, 'error' => 'Påmelding er stengt', 'status' => 403];
        }

        $participant = $this->participants->findRowById($participantId);
        if ($participant === null || (int) ($participant['owner_user_id'] ?? 0) !== $userId) {
            return ['ok' => false, 'error' => 'Fant ikke deltakeren', 'status' => 403];
        }

        if ($this->signups->hasRegistration($competitionId, $participantId)) {
            return ['ok' => false, 'error' => 'Deltakeren er allerede påmeldt', 'status' => 409];
        }

        if ($slotId !== null) {
            $slot = $this->signups->findSlotById($slotId);
            if ($slot === null || (int) ($slot['competition_id'] ?? 0) !== $competitionId) {
                return ['ok' => false, 'error' => 'Ugyldig lag', 'status' => 422];
            }
            if (!empty($slot['is_reserved'])) {
                return ['ok' => false, 'error' => 'Dette laget er reservert for arrangør', 'status' => 403];
            }
            if ($figureNumber !== null && $figureNumber > 0 && $this->signups->isPlaceReserved($competitionId, $slotId, $figureNumber)) {
                return ['ok' => false, 'error' => 'Plassen er reservert for arrangør', 'status' => 403];
            }
        }

        try {
            $this->signups->createRegistration($competitionId, $participantId, $slotId, $figureNumber, 'web', $userId);

            return ['ok' => true, 'data' => ['success' => true]];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage(), 'status' => 409];
        }
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok: true, data: array<string, mixed>}|array{ok: false, error: string, status: int}
     */
    public function unregister(int $userId, string $host, array $input): array
    {
        $resolved = $this->resolveTenant($host);
        if (!$resolved['ok']) {
            return $resolved;
        }

        $competitionId = (int) ($input['competition_id'] ?? 0);
        $participantId = (int) ($input['participant_id'] ?? 0);

        if ($competitionId <= 0 || $participantId <= 0) {
            return ['ok' => false, 'error' => 'competition_id og participant_id er påkrevd', 'status' => 422];
        }

        $participant = $this->participants->findRowById($participantId);
        if ($participant === null || (int) ($participant['owner_user_id'] ?? 0) !== $userId) {
            return ['ok' => false, 'error' => 'Fant ikke deltakeren', 'status' => 403];
        }

        $this->signups->cancelRegistration($competitionId, $participantId);

        return ['ok' => true, 'data' => ['success' => true]];
    }

    /**
     * @return array{ok: true, data: array<string, mixed>}|array{ok: false, error: string, status: int}
     */
    public function listSignups(int $userId, string $host): array
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
                'signups' => $this->signups->listSignupsForUserInTenant($userId, $tenantId),
            ],
        ];
    }

    private function generateUniqueJaktfeltId(): JaktfeltId
    {
        for ($i = 0; $i < 100; $i++) {
            $id = JaktfeltId::generateRandom();
            if (!$this->participants->jaktfeltIdExists($id->value)) {
                return $id;
            }
        }

        throw new \RuntimeException('Kunne ikke generere unik Jaktfelt-ID');
    }

    private function parseDate(string $raw): \DateTimeImmutable|false|null
    {
        if ($raw === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable($raw);
        } catch (\Throwable) {
            return false;
        }
    }
}
