<?php

declare(strict_types=1);

namespace App\Service;

use App\Contracts\Repositories\AdminOrganizationRepositoryPort;
use App\Contracts\Repositories\ParticipantClaimRepositoryPort;
use App\Contracts\Repositories\ParticipantRepositoryPort;
use App\Contracts\Repositories\TenantRepositoryPort;
use App\Contracts\Repositories\UserProfileRepositoryPort;
use App\Contracts\Repositories\UserRepositoryPort;
use App\Repositories\Pdo\PdoLegacyOrganizerRepository;
use App\Service\UseCases\EnsureParticipantForUserUseCase;
use App\Support\DistriktTagParser;
use App\Support\OrganizerPostalInput;

final class OnboardingService
{
    public function __construct(
        private readonly UserProfileRepositoryPort $profiles,
        private readonly UserRepositoryPort $users,
        private readonly ParticipantRepositoryPort $participants,
        private readonly ParticipantClaimRepositoryPort $claims,
        private readonly AdminOrganizationRepositoryPort $organizations,
        private readonly TenantRepositoryPort $tenants,
        private readonly PdoLegacyOrganizerRepository $legacyOrganizers,
        private readonly EnsureParticipantForUserUseCase $ensureParticipant,
    ) {
    }

    /**
     * @return array{ok: true, data: array<string, mixed>}|array{ok: false, error: string, status: int}
     */
    public function completeRegistration(
        int $userId,
        string $firstName,
        string $lastName,
        string $phone,
        ?string $userAgreementVersion,
    ): array {
        $this->profiles->save((string) $userId, [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'phone' => $phone,
            'user_agreement_version' => $userAgreementVersion,
            'user_agreement_accepted_at' => date('Y-m-d H:i:s'),
        ]);

        $onboarding = $this->resolveParticipantForUser($userId, $firstName, $lastName, $phone);

        return ['ok' => true, 'data' => $onboarding];
    }

    /**
     * @return array{ok: true, data: array<string, mixed>}|array{ok: false, error: string, status: int}
     */
    public function getProfile(int $userId): array
    {
        return [
            'ok' => true,
            'data' => [
                'profile' => $this->profiles->get((string) $userId) ?? [],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok: true, data: array<string, mixed>}|array{ok: false, error: string, status: int}
     */
    public function updateProfile(int $userId, array $input): array
    {
        $data = [];
        foreach (['phone', 'first_name', 'last_name', 'date_of_birth'] as $key) {
            if (array_key_exists($key, $input)) {
                $val = trim((string) $input[$key]);
                $data[$key] = $val !== '' ? $val : null;
            }
        }
        if (!empty($input['organizer_agreement_version'])) {
            $data['organizer_agreement_version'] = trim((string) $input['organizer_agreement_version']);
            $data['organizer_agreement_accepted_at'] = date('Y-m-d H:i:s');
        }
        if ($data === []) {
            return ['ok' => false, 'error' => 'Ingen data å lagre', 'status' => 422];
        }

        $this->profiles->save((string) $userId, $data);

        return $this->getProfile($userId);
    }

    /**
     * @return array{ok: true, data: array<string, mixed>}|array{ok: false, error: string, status: int}
     */
    public function onboardingParticipant(int $userId): array
    {
        $profile = $this->profiles->get((string) $userId) ?? [];
        [$firstName, $lastName, $phone] = $this->resolveUserIdentity($userId, $profile);
        if ($firstName === '' || $lastName === '') {
            return ['ok' => false, 'error' => 'Brukerprofil mangler navn', 'status' => 422];
        }

        return ['ok' => true, 'data' => $this->resolveParticipantForUser($userId, $firstName, $lastName, $phone)];
    }

    /**
     * @return array{ok: true, data: array<string, mixed>}|array{ok: false, error: string, status: int}
     */
    public function claimParticipant(int $userId, int $participantId): array
    {
        if ($participantId <= 0) {
            return ['ok' => false, 'error' => 'Ugyldig deltaker', 'status' => 422];
        }

        $row = $this->participants->findRowById($participantId);
        if ($row === null) {
            return ['ok' => false, 'error' => 'Deltaker ikke funnet', 'status' => 404];
        }

        $currentOwner = isset($row['owner_user_id']) ? (int) $row['owner_user_id'] : null;
        if ($currentOwner === $userId) {
            return ['ok' => false, 'error' => 'Du er allerede eier av denne deltakeren', 'status' => 422];
        }

        $existing = $this->claims->findPendingByParticipantAndNewOwner($participantId, $userId);
        if ($existing !== null) {
            return [
                'ok' => true,
                'data' => [
                    'claim' => $existing,
                    'message' => $currentOwner === null
                        ? 'Deltakeren har ingen nåværende eier. Administrator vil følge opp eierskap.'
                        : 'Forespørselen er allerede sendt til nåværende eier.',
                ],
            ];
        }

        $claim = $this->claims->createPending($participantId, $currentOwner > 0 ? $currentOwner : null, $userId);

        return [
            'ok' => true,
            'data' => [
                'claim' => $claim,
                'message' => $currentOwner === null || $currentOwner <= 0
                    ? 'Deltakeren har ingen nåværende eier. Administrator vil følge opp eierskap.'
                    : 'Forespørselen er sendt til nåværende eier. De kan godkjenne at du overtar deltakerens eierskap.',
            ],
        ];
    }

    /**
     * @return array{ok: true, data: array<string, mixed>}|array{ok: false, error: string, status: int}
     */
    public function listOrganizations(int $userId): array
    {
        $orgs = $this->organizations->listByAuthUserId($userId);
        if ($orgs === [] && $this->legacyOrganizers->tableExists()) {
            $orgs = $this->legacyOrganizers->listByUserId($userId);
        }

        return ['ok' => true, 'data' => ['organizations' => $orgs]];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok: true, data: array<string, mixed>}|array{ok: false, error: string, status: int}
     */
    public function createOrganization(int $userId, array $input): array
    {
        $profile = $this->profiles->get((string) $userId) ?? [];
        if (empty($profile['organizer_agreement_version'])) {
            return ['ok' => false, 'error' => 'Du må godta arrangøravtalen før du kan opprette arrangør', 'status' => 422];
        }

        $host = trim((string) ($input['host'] ?? ''));
        $tenantId = (int) ($input['tenant_id'] ?? 0);
        if ($tenantId <= 0 && $host !== '') {
            $tenant = $this->tenants->findByHost($host);
            $tenantId = $tenant !== null ? (int) ($tenant['id'] ?? 0) : 0;
        }
        if ($tenantId <= 0) {
            return ['ok' => false, 'error' => 'Kunne ikke finne cup (tenant)', 'status' => 422];
        }

        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            return ['ok' => false, 'error' => 'Arrangørnavn er påkrevd', 'status' => 422];
        }

        $postalFields = OrganizerPostalInput::fromPost($input);
        if ($postalFields['error'] !== null) {
            return ['ok' => false, 'error' => $postalFields['error'], 'status' => 422];
        }

        $distriktFields = DistriktTagParser::fromPost($input);
        if ($distriktFields['error'] !== null) {
            return ['ok' => false, 'error' => $distriktFields['error'], 'status' => 422];
        }

        $contactPerson = trim((string) ($input['contact_person'] ?? ''));
        $email = trim((string) ($input['email'] ?? ''));
        $phone = trim((string) ($input['phone'] ?? ''));
        $orgNumber = trim((string) ($input['organization_number'] ?? ''));
        $orgType = trim((string) ($input['organization_type'] ?? 'skytterlag')) ?: 'skytterlag';

        $legacyId = null;
        if ($this->legacyOrganizers->tableExists()) {
            $legacy = $this->legacyOrganizers->create(
                $name,
                $orgNumber !== '' ? $orgNumber : null,
                $orgType,
                $contactPerson !== '' ? $contactPerson : null,
                $email !== '' ? $email : null,
                $phone !== '' ? $phone : null,
                $postalFields['postal_code'],
                $postalFields['city'],
                $distriktFields['districts'],
            );
            $legacyId = (int) ($legacy['id'] ?? 0);
            if ($legacyId > 0) {
                $this->legacyOrganizers->addMember($legacyId, $userId, 'OWNER');
            }
        }

        $org = $this->organizations->create([
            'tenant_id' => $tenantId,
            'legacy_jaktfelt_organizer_id' => $legacyId > 0 ? $legacyId : null,
            'name' => $name,
            'organization_number' => $orgNumber !== '' ? $orgNumber : null,
            'organization_type' => $orgType,
            'contact_person' => $contactPerson !== '' ? $contactPerson : null,
            'email' => $email !== '' ? $email : null,
            'phone' => $phone !== '' ? $phone : null,
            'postal_code' => $postalFields['postal_code'],
            'city' => $postalFields['city'],
            'districts' => $distriktFields['districts'],
            'status' => 'active',
        ]);

        $orgId = (int) ($org['id'] ?? 0);
        if ($orgId > 0) {
            $this->organizations->addMember($orgId, $userId, 'OWNER');
        }

        return ['ok' => true, 'data' => ['organization' => $org]];
    }

    /** @return array<string, mixed> */
    private function resolveParticipantForUser(int $userId, string $firstName, string $lastName, string $phone): array
    {
        $phoneOrNull = $phone !== '' ? $phone : null;
        $candidate = $this->participants->findByNamesAndPhone($firstName, $lastName, $phoneOrNull)
            ?? $this->participants->findByNamesAndPhone($firstName, $lastName, null);

        if ($candidate !== null) {
            $candidateId = (int) ($candidate['id'] ?? 0);
            $ownerId = isset($candidate['owner_user_id']) ? (int) $candidate['owner_user_id'] : null;
            $jaktfeltId = $this->participants->getJaktfeltId($candidateId);

            return [
                'existing_participant' => $this->formatParticipant($candidate, $jaktfeltId, $ownerId === $userId),
                'created_participant' => null,
            ];
        }

        $created = $this->ensureParticipant->execute($userId, $firstName, $lastName, $phoneOrNull, null);
        if ($created !== null) {
            return [
                'existing_participant' => null,
                'created_participant' => $this->formatParticipantFromListRow($created),
            ];
        }

        $owned = $this->participants->listByOwnerUserId($userId);
        if ($owned !== []) {
            $row = $owned[0];

            return [
                'existing_participant' => $this->formatParticipantFromListRow($row),
                'created_participant' => null,
            ];
        }

        return ['existing_participant' => null, 'created_participant' => null];
    }

    /** @param array<string, mixed> $profile @return array{0: string, 1: string, 2: string} */
    private function resolveUserIdentity(int $userId, array $profile): array
    {
        $firstName = trim((string) ($profile['first_name'] ?? ''));
        $lastName = trim((string) ($profile['last_name'] ?? ''));
        $phone = trim((string) ($profile['phone'] ?? ''));
        if ($firstName !== '' && $lastName !== '') {
            return [$firstName, $lastName, $phone];
        }

        $user = $this->users->findById($userId);
        if ($user === null) {
            return ['', '', ''];
        }

        $firstName = trim((string) ($user['first_name'] ?? ''));
        $lastName = trim((string) ($user['last_name'] ?? ''));
        if ($firstName === '' && $lastName === '') {
            $name = trim((string) ($user['name'] ?? ''));
            if ($name !== '') {
                $parts = preg_split('/\s+/', $name, 2) ?: ['', ''];
                $firstName = (string) ($parts[0] ?? '');
                $lastName = (string) ($parts[1] ?? '');
            }
        }
        if ($phone === '') {
            $phone = trim((string) ($user['phone'] ?? ''));
        }

        if ($firstName !== '' && $lastName !== '') {
            $this->profiles->save((string) $userId, [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'phone' => $phone !== '' ? $phone : null,
            ]);
        }

        return [$firstName, $lastName, $phone];
    }

    /** @param array<string, mixed> $row */
    private function formatParticipant(array $row, ?string $jaktfeltId, bool $isMine): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'name' => trim((string) ($row['first_name'] ?? '') . ' ' . (string) ($row['last_name'] ?? '')),
            'first_name' => (string) ($row['first_name'] ?? ''),
            'last_name' => (string) ($row['last_name'] ?? ''),
            'date_of_birth' => !empty($row['date_of_birth']) ? (string) $row['date_of_birth'] : null,
            'phone' => !empty($row['phone']) ? (string) $row['phone'] : null,
            'jaktfelt_id' => $jaktfeltId,
            'is_mine' => $isMine,
        ];
    }

    /** @param array<string, mixed> $row */
    private function formatParticipantFromListRow(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'name' => trim((string) ($row['first_name'] ?? '') . ' ' . (string) ($row['last_name'] ?? '')),
            'first_name' => (string) ($row['first_name'] ?? ''),
            'last_name' => (string) ($row['last_name'] ?? ''),
            'date_of_birth' => $row['date_of_birth'] ?? null,
            'phone' => $row['phone'] ?? null,
            'jaktfelt_id' => $row['jaktfelt_id'] ?? null,
            'is_mine' => true,
        ];
    }
}
