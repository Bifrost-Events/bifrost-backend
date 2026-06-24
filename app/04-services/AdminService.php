<?php

declare(strict_types=1);

namespace App\Service;

use App\Contracts\Repositories\AdminAccessRepositoryPort;
use App\Contracts\Repositories\AdminDomainRepositoryPort;
use App\Contracts\Repositories\AdminOrganizationRepositoryPort;
use App\Contracts\Repositories\AdminSeasonRepositoryPort;
use App\Contracts\Repositories\AdminTenantRepositoryPort;
use App\Contracts\Repositories\AdminUserRepositoryPort;
use App\Support\AdminAuth;
use App\Support\CupStandings;

final class AdminService
{
  /** @var list<string> */
    private const DOMAIN_PURPOSES = ['public', 'admin', 'api', 'arrangor'];

    public const ROLE_ASSIGNMENT_INLINE_LIMIT = 5;

    public function __construct(
        private readonly AdminTenantRepositoryPort $tenants,
        private readonly AdminDomainRepositoryPort $domains,
        private readonly AdminUserRepositoryPort $users,
        private readonly AdminAccessRepositoryPort $access,
        private readonly AdminOrganizationRepositoryPort $organizations,
        private readonly AdminSeasonRepositoryPort $seasons,
    ) {
    }

    /** @return array{status: int, headers: array<string, string>, body: string}|null */
    public function guard(): ?array
    {
        return AdminAuth::requireAdmin();
    }

    /** @param array<string, mixed> $user @return list<array<string, mixed>> */
    public function listTenants(array $user): array
    {
        $filter = AdminAuth::isSystemAdmin($user) ? null : AdminAuth::allowedTenantIds($user);

        return $this->tenants->findAll($filter);
    }

    /** @param array<string, mixed> $user */
    public function getTenant(array $user, int $id): ?array
    {
        if (!AdminAuth::canManageTenant($user, $id)) {
            return null;
        }

        return $this->tenants->findById($id);
    }

    /**
     * @param array<string, mixed> $user
     * @param array<string, mixed> $body
     * @return array{tenant?: array<string, mixed>, errors?: array<string, string>}
     */
    public function createTenant(array $user, array $body): array
    {
        if (!AdminAuth::isSystemAdmin($user)) {
            return ['errors' => ['forbidden' => 'Kun SystemAdmin kan opprette tenants']];
        }

        $errors = $this->validateTenant($body);
        if ($errors !== []) {
            return ['errors' => $errors];
        }

        return ['tenant' => $this->tenants->create([
            'slug' => $this->normalizeSlug((string) $body['slug']),
            'name' => trim((string) $body['name']),
            'tenant_type' => (string) $body['tenant_type'],
            'status' => (string) ($body['status'] ?? 'active'),
        ])];
    }

    /**
     * @param array<string, mixed> $user
     * @param array<string, mixed> $body
     * @return array{tenant?: array<string, mixed>, errors?: array<string, string>}
     */
    public function updateTenant(array $user, int $id, array $body): array
    {
        if (!AdminAuth::canManageTenant($user, $id)) {
            return ['errors' => ['forbidden' => 'Ingen tilgang til denne tenant']];
        }

        $errors = $this->validateTenant($body, $id);
        if ($errors !== []) {
            return ['errors' => $errors];
        }

        $tenant = $this->tenants->update($id, [
            'slug' => $this->normalizeSlug((string) $body['slug']),
            'name' => trim((string) $body['name']),
            'tenant_type' => (string) $body['tenant_type'],
            'status' => (string) ($body['status'] ?? 'active'),
        ]);

        return $tenant === null ? ['errors' => ['not_found' => 'Tenant ikke funnet']] : ['tenant' => $tenant];
    }

    /** @param array<string, mixed> $user */
    public function deactivateTenant(array $user, int $id): bool
    {
        if (!AdminAuth::canManageTenant($user, $id)) {
            return false;
        }

        return $this->tenants->deactivate($id);
    }

    /** @param array<string, mixed> $user @return list<array<string, mixed>> */
    public function listDomains(array $user, int $tenantId): ?array
    {
        if (!AdminAuth::canManageTenant($user, $tenantId)) {
            return null;
        }

        return $this->domains->findByTenantId($tenantId);
    }

    /**
     * @param array<string, mixed> $user
     * @param array<string, mixed> $body
     * @return array{domain?: array<string, mixed>, errors?: array<string, string>}
     */
    public function createDomain(array $user, int $tenantId, array $body): array
    {
        if (!AdminAuth::canManageTenant($user, $tenantId)) {
            return ['errors' => ['forbidden' => 'Ingen tilgang til denne tenant']];
        }

        $body['tenant_id'] = $tenantId;
        $body['purpose'] = $this->normalizeDomainPurpose((string) ($body['purpose'] ?? ''));
        $errors = $this->validateDomain($body);
        if ($errors !== []) {
            return ['errors' => $errors];
        }

        return ['domain' => $this->domains->create($body)];
    }

    /**
     * @param array<string, mixed> $user
     * @param array<string, mixed> $body
     * @return array{domain?: array<string, mixed>, errors?: array<string, string>}
     */
    public function updateDomain(array $user, int $id, array $body): array
    {
        $existing = $this->domains->findById($id);
        if ($existing === null) {
            return ['errors' => ['not_found' => 'Domene ikke funnet']];
        }

        if (!AdminAuth::canManageTenant($user, (int) $existing['tenant_id'])) {
            return ['errors' => ['forbidden' => 'Ingen tilgang']];
        }

        $body['purpose'] = $this->normalizeDomainPurpose((string) ($body['purpose'] ?? ''));
        $errors = $this->validateDomain($body, $id);
        if ($errors !== []) {
            return ['errors' => $errors];
        }

        $domain = $this->domains->update($id, $body);

        return $domain === null ? ['errors' => ['not_found' => 'Domene ikke funnet']] : ['domain' => $domain];
    }

    /** @param array<string, mixed> $user */
    public function deleteDomain(array $user, int $id): bool
    {
        $existing = $this->domains->findById($id);
        if ($existing === null) {
            return false;
        }

        if (!AdminAuth::canManageTenant($user, (int) $existing['tenant_id'])) {
            return false;
        }

        return $this->domains->delete($id);
    }

    /** @return list<array<string, mixed>> */
    public function listUsers(): array
    {
        return $this->users->findAll();
    }

    /** @return list<array<string, mixed>> */
    public function searchUsers(string $query, int $limit = 50): array
    {
        $query = trim($query);
        if ($query === '' || mb_strlen($query) < 3) {
            return [];
        }

        return $this->users->search($query, $limit);
    }

    public function getUser(int $id): ?array
    {
        return $this->users->findById($id);
    }

    /**
     * @param array<string, mixed> $body
     * @return array{user?: array<string, mixed>, errors?: array<string, string>}
     */
    public function createUser(array $body): array
    {
        $errors = $this->validateUser($body, true);
        if ($errors !== []) {
            return ['errors' => $errors];
        }

        $password = (string) $body['password'];

        return ['user' => $this->users->create([
            'email' => $body['email'],
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'name' => $body['name'] ?? '',
            'phone' => $body['phone'] ?? null,
            'is_active' => $body['is_active'] ?? true,
            'first_registered_tenant_id' => $body['first_registered_tenant_id'] ?? null,
        ])];
    }

    /**
     * @param array<string, mixed> $body
     * @return array{user?: array<string, mixed>, errors?: array<string, string>}
     */
    public function updateUser(int $id, array $body): array
    {
        $errors = $this->validateUser($body, false, $id);
        if ($errors !== []) {
            return ['errors' => $errors];
        }

        $data = [
            'email' => $body['email'],
            'name' => $body['name'] ?? '',
            'phone' => $body['phone'] ?? null,
            'is_active' => $body['is_active'] ?? true,
            'first_registered_tenant_id' => $body['first_registered_tenant_id'] ?? null,
        ];

        if (!empty($body['password'])) {
            $data['password_hash'] = password_hash((string) $body['password'], PASSWORD_DEFAULT);
        }

        $user = $this->users->update($id, $data);

        return $user === null ? ['errors' => ['not_found' => 'Bruker ikke funnet']] : ['user' => $user];
    }

    public function deactivateUser(int $id): bool
    {
        return $this->users->deactivate($id);
    }

    /** @param array<string, mixed> $user @return list<array<string, mixed>> */
    public function listRoleDefinitions(array $user): array
    {
        $isSystemAdmin = AdminAuth::isSystemAdmin($user);
        $roles = [];
        foreach ($this->access->listRoleDefinitions() as $role) {
            $entry = [
                'role' => $role,
                'scope' => $role === 'CupAdmin' ? 'tenant' : ($role === 'Organizer' ? 'organization' : 'system'),
            ];
            if ($role === 'Organizer') {
                $entry['status'] = 'planned';
                $entry['grantable'] = false;
            } elseif ($role === 'SystemAdmin') {
                $entry['grantable'] = $isSystemAdmin;
            } else {
                $entry['grantable'] = true;
            }
            $roles[] = $entry;
        }

        return $roles;
    }

    /** @param array<string, mixed> $user @return list<array<string, mixed>> */
    public function listRoleAssignmentsOverview(array $user): array
    {
        $isSystemAdmin = AdminAuth::isSystemAdmin($user);
        $tenantFilter = $isSystemAdmin ? null : AdminAuth::allowedTenantIds($user);
        $overview = [];

        if ($isSystemAdmin) {
            $overview[] = $this->buildRoleAssignmentSummary(
                'SystemAdmin',
                $this->access->listAssignmentsForSystemRole('SystemAdmin'),
                false
            );
        } else {
            $overview[] = [
                'role' => 'SystemAdmin',
                'total_count' => null,
                'preview' => [],
                'restricted' => true,
            ];
        }

        $overview[] = $this->buildRoleAssignmentSummary(
            'CupAdmin',
            $this->access->listAssignmentsForTenantRole('CupAdmin', $tenantFilter),
            true
        );

        $overview[] = [
            'role' => 'Organizer',
            'total_count' => 0,
            'preview' => [],
            'status' => 'planned',
        ];

        return $overview;
    }

    /**
     * @param array<string, mixed> $user
     * @return array{role: string, assignments: list<array<string, mixed>>}|null
     */
    public function listRoleAssignments(array $user, string $role): ?array
    {
        $role = trim($role);
        if ($role === 'Organizer') {
            return ['role' => $role, 'assignments' => []];
        }

        if ($role === 'SystemAdmin') {
            if (!AdminAuth::isSystemAdmin($user)) {
                return null;
            }

            return [
                'role' => $role,
                'assignments' => $this->access->listAssignmentsForSystemRole('SystemAdmin'),
            ];
        }

        if ($role === 'CupAdmin') {
            $tenantFilter = AdminAuth::isSystemAdmin($user) ? null : AdminAuth::allowedTenantIds($user);

            return [
                'role' => $role,
                'assignments' => $this->access->listAssignmentsForTenantRole('CupAdmin', $tenantFilter),
            ];
        }

        return null;
    }

    /**
     * @param list<array<string, mixed>> $assignments
     * @return array{role: string, total_count: int, preview: list<array<string, mixed>>, tenant_scoped?: bool}
     */
    private function buildRoleAssignmentSummary(string $role, array $assignments, bool $tenantScoped): array
    {
        return [
            'role' => $role,
            'total_count' => count($assignments),
            'preview' => array_slice($assignments, 0, self::ROLE_ASSIGNMENT_INLINE_LIMIT),
            'tenant_scoped' => $tenantScoped,
        ];
    }

    /** @return array{system_roles: list<array<string, mixed>>, tenant_admin_access: list<array<string, mixed>>} */
    public function getUserAccess(int $userId): array
    {
        return $this->access->getUserAccess($userId);
    }

    /**
     * @param array<string, mixed> $actor
     * @return array{ok: bool, errors?: array<string, string>}
     */
    public function grantSystemRole(array $actor, int $userId, string $role): array
    {
        if ($role !== 'SystemAdmin') {
            return ['ok' => false, 'errors' => ['role' => 'Ugyldig systemrolle']];
        }
        if (!AdminAuth::isSystemAdmin($actor)) {
            return ['ok' => false, 'errors' => ['forbidden' => 'Kun SystemAdmin kan gi SystemAdmin']];
        }
        if ($this->users->findById($userId) === null) {
            return ['ok' => false, 'errors' => ['not_found' => 'Bruker ikke funnet']];
        }

        $this->access->grantSystemRole($userId, $role);

        return ['ok' => true];
    }

    /**
     * @param array<string, mixed> $actor
     * @return array{ok: bool, errors?: array<string, string>}
     */
    public function revokeSystemRole(array $actor, int $userId, string $role): array
    {
        if ($role !== 'SystemAdmin') {
            return ['ok' => false, 'errors' => ['role' => 'Ugyldig systemrolle']];
        }
        if (!AdminAuth::isSystemAdmin($actor)) {
            return ['ok' => false, 'errors' => ['forbidden' => 'Kun SystemAdmin kan fjerne SystemAdmin']];
        }

        return ['ok' => $this->access->revokeSystemRole($userId, $role)];
    }

    /**
     * @param array<string, mixed> $actor
     * @param array<string, mixed> $body
     * @return array{access?: array<string, mixed>, errors?: array<string, string>}
     */
    public function grantTenantAccess(array $actor, int $userId, array $body): array
    {
        $tenantId = (int) ($body['tenant_id'] ?? 0);
        $role = (string) ($body['role'] ?? 'CupAdmin');

        if ($role !== 'CupAdmin') {
            return ['errors' => ['role' => 'Kun CupAdmin kan gis via admin API nå']];
        }
        if ($tenantId <= 0) {
            return ['errors' => ['tenant_id' => 'Tenant er påkrevd']];
        }
        if (!AdminAuth::canManageTenant($actor, $tenantId)) {
            return ['errors' => ['forbidden' => 'Ingen tilgang til denne tenant']];
        }
        if ($this->users->findById($userId) === null) {
            return ['errors' => ['not_found' => 'Bruker ikke funnet']];
        }

        $access = $this->access->grantTenantAccess($userId, $tenantId, $role);

        return $access === null
            ? ['errors' => ['failed' => 'Kunne ikke gi tilgang']]
            : ['access' => $access];
    }

    /** @param array<string, mixed> $actor */
    public function revokeTenantAccess(array $actor, int $userId, int $accessId): bool
    {
        $row = $this->access->findTenantAccessById($accessId);
        if ($row === null || (int) $row['auth_user_id'] !== $userId) {
            return false;
        }

        if (!AdminAuth::canManageTenant($actor, (int) $row['tenant_id'])) {
            return false;
        }

        return $this->access->revokeTenantAccess($accessId);
    }

    /** @param array<string, mixed> $user @return list<array<string, mixed>> */
    public function listOrganizations(array $user, ?int $tenantId = null, ?string $search = null): array
    {
        $tenantFilter = $this->organizationTenantFilter($user, $tenantId);

        return $this->organizations->findAll($tenantFilter, $search);
    }

    /** @param array<string, mixed> $user */
    public function getOrganization(array $user, int $id): ?array
    {
        $org = $this->organizations->findById($id);
        if ($org === null) {
            return null;
        }

        if (!AdminAuth::canManageTenant($user, (int) ($org['tenant_id'] ?? 0))) {
            return null;
        }

        return $org;
    }

    /**
     * @param array<string, mixed> $user
     * @param array<string, mixed> $body
     * @return array{organization?: array<string, mixed>, errors?: array<string, string>}
     */
    public function createOrganization(array $user, array $body): array
    {
        $errors = $this->validateOrganization($body, true);
        if ($errors !== []) {
            return ['errors' => $errors];
        }

        $tenantId = (int) ($body['tenant_id'] ?? 0);
        if (!AdminAuth::canManageTenant($user, $tenantId)) {
            return ['errors' => ['forbidden' => 'Ingen tilgang til denne cupen']];
        }

        return ['organization' => $this->organizations->create($body)];
    }

    /**
     * @param array<string, mixed> $user
     * @param array<string, mixed> $body
     * @return array{organization?: array<string, mixed>, errors?: array<string, string>}
     */
    public function updateOrganization(array $user, int $id, array $body): array
    {
        $existing = $this->organizations->findById($id);
        if ($existing === null) {
            return ['errors' => ['not_found' => 'Organisasjon ikke funnet']];
        }
        if (!AdminAuth::canManageTenant($user, (int) ($existing['tenant_id'] ?? 0))) {
            return ['errors' => ['forbidden' => 'Ingen tilgang']];
        }

        $body['tenant_id'] = (int) $existing['tenant_id'];
        $errors = $this->validateOrganization($body, false);
        if ($errors !== []) {
            return ['errors' => $errors];
        }

        $org = $this->organizations->update($id, $body);

        return $org === null
            ? ['errors' => ['not_found' => 'Organisasjon ikke funnet']]
            : ['organization' => $org];
    }

    /** @param array<string, mixed> $user */
    public function deactivateOrganization(array $user, int $id): bool
    {
        $existing = $this->organizations->findById($id);
        if ($existing === null) {
            return false;
        }
        if (!AdminAuth::canManageTenant($user, (int) ($existing['tenant_id'] ?? 0))) {
            return false;
        }

        return $this->organizations->deactivate($id);
    }

    /** @param array<string, mixed> $user @return list<array<string, mixed>> */
    public function listOrganizationMembers(array $user, int $organizationId): array
    {
        if ($this->getOrganization($user, $organizationId) === null) {
            return [];
        }

        return $this->organizations->listMembers($organizationId);
    }

    /**
     * @param array<string, mixed> $user
     * @param array<string, mixed> $body
     * @return array{member?: array<string, mixed>, errors?: array<string, string>}
     */
    public function addOrganizationMember(array $user, int $organizationId, array $body): array
    {
        if ($this->getOrganization($user, $organizationId) === null) {
            return ['errors' => ['forbidden' => 'Ingen tilgang']];
        }

        $authUserId = (int) ($body['auth_user_id'] ?? $body['user_id'] ?? 0);
        $role = strtoupper(trim((string) ($body['role'] ?? 'VIEWER')));
        if ($authUserId <= 0) {
            return ['errors' => ['auth_user_id' => 'Bruker er påkrevd']];
        }
        if (!in_array($role, ['OWNER', 'ADMIN', 'REGISTRAR', 'VIEWER'], true)) {
            return ['errors' => ['role' => 'Ugyldig rolle']];
        }
        if ($this->users->findById($authUserId) === null) {
            return ['errors' => ['auth_user_id' => 'Bruker ikke funnet']];
        }

        $member = $this->organizations->addMember($organizationId, $authUserId, $role);

        return $member === null
            ? ['errors' => ['failed' => 'Kunne ikke legge til medlem']]
            : ['member' => $member];
    }

    /** @param array<string, mixed> $user */
    public function removeOrganizationMember(array $user, int $organizationId, int $memberId): bool
    {
        if ($this->getOrganization($user, $organizationId) === null) {
            return false;
        }

        $member = $this->organizations->findMemberById($memberId);
        if ($member === null || (int) ($member['organization_id'] ?? 0) !== $organizationId) {
            return false;
        }

        return $this->organizations->removeMember($memberId);
    }

    /** @param array<string, mixed> $user @return list<array<string, mixed>> */
    public function listSeasons(array $user, ?int $tenantId = null): array
    {
        $tenantFilter = $this->organizationTenantFilter($user, $tenantId);

        return $this->seasons->findAllWithStructure($tenantFilter);
    }

    /** @param array<string, mixed> $user */
    public function getSeason(array $user, int $id): ?array
    {
        $season = $this->seasons->findById($id);
        if ($season === null) {
            return null;
        }
        if (!AdminAuth::canManageTenant($user, (int) ($season['tenant_id'] ?? 0))) {
            return null;
        }

        return $season;
    }

    /**
     * @param array<string, mixed> $user
     * @param array<string, mixed> $body
     * @return array{season?: array<string, mixed>, errors?: array<string, string>}
     */
    public function createSeason(array $user, array $body): array
    {
        $errors = $this->validateSeason($body, true);
        if ($errors !== []) {
            return ['errors' => $errors];
        }

        $tenantId = (int) ($body['tenant_id'] ?? 0);
        if (!AdminAuth::canManageTenant($user, $tenantId)) {
            return ['errors' => ['forbidden' => 'Ingen tilgang til denne cupen']];
        }

        try {
            return ['season' => $this->seasons->create($body)];
        } catch (\Throwable $e) {
            return ['errors' => ['failed' => 'Kunne ikke opprette sesong: ' . $e->getMessage()]];
        }
    }

    /**
     * @param array<string, mixed> $user
     * @param array<string, mixed> $body
     * @return array{season?: array<string, mixed>, errors?: array<string, string>}
     */
    public function updateSeason(array $user, int $id, array $body): array
    {
        $existing = $this->getSeason($user, $id);
        if ($existing === null) {
            return ['errors' => ['not_found' => 'Sesong ikke funnet']];
        }

        $errors = $this->validateSeason($body, false);
        if ($errors !== []) {
            return ['errors' => $errors];
        }

        $body['tenant_id'] = (int) $existing['tenant_id'];
        $season = $this->seasons->update($id, $body);

        return $season === null
            ? ['errors' => ['not_found' => 'Sesong ikke funnet']]
            : ['season' => $season];
    }

    /**
     * @param array<string, mixed> $user
     * @param array<string, mixed> $body
     * @return array{season?: array<string, mixed>, errors?: array<string, string>}
     */
    public function updateSeasonCupStandings(array $user, int $id, array $body): array
    {
        $existing = $this->getSeason($user, $id);
        if ($existing === null) {
            return ['errors' => ['not_found' => 'Sesong ikke funnet']];
        }

        $mode = CupStandings::normalizeMode((string) ($body['cup_standings_mode'] ?? 'total_score'));
        $placePoints = $body['placement_points'] ?? [];
        $map = [];
        if (is_array($placePoints)) {
            foreach ($placePoints as $k => $v) {
                $p = (int) $k;
                if ($p < 1 || $p > CupStandings::MAX_PLACEMENT_PLACE) {
                    continue;
                }
                if ($v === '' || $v === null || !is_numeric($v)) {
                    continue;
                }
                $map[$p] = round((float) $v, 3);
            }
        }
        ksort($map);

        $allInSeason = $this->seasons->listCompetitionsForSeason($id);
        $allIds = [];
        foreach ($allInSeason as $c) {
            $cid = (int) ($c['id'] ?? 0);
            if ($cid > 0) {
                $allIds[] = $cid;
            }
        }
        $allIds = array_values(array_unique($allIds));
        sort($allIds);

        $posted = $body['cup_competition_ids'] ?? [];
        if (!is_array($posted)) {
            $posted = [];
        }
        $selected = [];
        foreach ($posted as $v) {
            $cid = (int) $v;
            if ($cid > 0 && in_array($cid, $allIds, true)) {
                $selected[] = $cid;
            }
        }
        $selected = array_values(array_unique($selected));
        sort($selected);

        $cupCompetitionIds = null;
        if ($allIds !== [] && $selected !== $allIds) {
            $cupCompetitionIds = $selected;
        }

        $countBest = isset($body['cup_standings_count_best'])
            ? max(0, min(99, (int) $body['cup_standings_count_best']))
            : CupStandings::DEFAULT_COUNT_BEST;

        $this->seasons->updateCupStandings($id, $mode, $map, $cupCompetitionIds, $countBest);

        $season = $this->seasons->findById($id);

        return $season === null
            ? ['errors' => ['not_found' => 'Sesong ikke funnet']]
            : ['season' => $season];
    }

    /**
     * @param array<string, mixed> $user
     * @param array<string, mixed> $body
     * @return array{round?: array<string, mixed>, errors?: array<string, string>}
     */
    public function createSeasonRound(array $user, int $seasonId, array $body): array
    {
        $existing = $this->getSeason($user, $seasonId);
        if ($existing === null) {
            return ['errors' => ['not_found' => 'Sesong ikke funnet']];
        }

        $errors = $this->validateRound($body);
        if ($errors !== []) {
            return ['errors' => $errors];
        }

        try {
            return ['round' => $this->seasons->createRound($seasonId, $body)];
        } catch (\Throwable $e) {
            return ['errors' => ['failed' => 'Kunne ikke opprette runde: ' . $e->getMessage()]];
        }
    }

    /**
     * @param array<string, mixed> $user
     * @return list<int>|null
     */
    private function organizationTenantFilter(array $user, ?int $tenantId): ?array
    {
        if (AdminAuth::isSystemAdmin($user)) {
            if ($tenantId !== null && $tenantId > 0) {
                return [$tenantId];
            }

            return null;
        }

        $allowed = AdminAuth::allowedTenantIds($user);
        if ($tenantId !== null && $tenantId > 0) {
            return in_array($tenantId, $allowed, true) ? [$tenantId] : [];
        }

        return $allowed;
    }

    /** @param array<string, mixed> $body @return array<string, string> */
    private function validateOrganization(array $body, bool $isCreate): array
    {
        $errors = [];
        $name = trim((string) ($body['name'] ?? ''));
        $tenantId = (int) ($body['tenant_id'] ?? 0);
        $type = trim((string) ($body['organization_type'] ?? 'skytterlag'));
        $status = (string) ($body['status'] ?? 'active');

        if ($isCreate && $tenantId <= 0) {
            $errors['tenant_id'] = 'Cup er påkrevd';
        }
        if ($name === '') {
            $errors['name'] = 'Navn er påkrevd';
        }
        if (!in_array($type, ['skytterlag', 'klubb', 'forbund', 'annet'], true)) {
            $errors['organization_type'] = 'Ugyldig organisasjonstype';
        }
        if (!in_array($status, ['active', 'inactive'], true)) {
            $errors['status'] = 'Status må være active eller inactive';
        }

        $email = trim((string) ($body['email'] ?? ''));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Ugyldig e-post';
        }

        return $errors;
    }

    /** @param array<string, mixed> $body @return array<string, string> */
    private function validateTenant(array $body, ?int $excludeId = null): array
    {
        $errors = [];
        $slug = $this->normalizeSlug((string) ($body['slug'] ?? ''));
        $name = trim((string) ($body['name'] ?? ''));
        $type = (string) ($body['tenant_type'] ?? '');
        $status = (string) ($body['status'] ?? 'active');

        if ($slug === '') {
            $errors['slug'] = 'Slug er påkrevd';
        } elseif (!preg_match('/^[a-z0-9][a-z0-9-]{1,62}$/', $slug)) {
            $errors['slug'] = 'Slug må være små bokstaver, tall og bindestrek';
        } elseif ($this->tenants->slugExists($slug, $excludeId)) {
            $errors['slug'] = 'Slug er allerede i bruk';
        }

        if ($name === '') {
            $errors['name'] = 'Navn er påkrevd';
        }

        if (!in_array($type, ['platform', 'cup'], true)) {
            $errors['tenant_type'] = 'Type må være platform eller cup';
        }

        if (!in_array($status, ['active', 'inactive'], true)) {
            $errors['status'] = 'Status må være active eller inactive';
        }

        return $errors;
    }

    /** @param array<string, mixed> $body @return array<string, string> */
    private function validateDomain(array $body, ?int $excludeId = null): array
    {
        $errors = [];
        $host = strtolower(trim((string) ($body['host'] ?? '')));
        $purpose = $this->normalizeDomainPurpose((string) ($body['purpose'] ?? ''));

        if ($host === '') {
            $errors['host'] = 'Host er påkrevd';
        } elseif ($this->domains->hostExists($host, $excludeId)) {
            $errors['host'] = 'Host er allerede i bruk';
        }

        if (!in_array($purpose, self::DOMAIN_PURPOSES, true)) {
            $errors['purpose'] = 'Type må være public, admin, api eller organizer';
        }

        return $errors;
    }

    /** @param array<string, mixed> $body @return array<string, string> */
    private function validateUser(array $body, bool $isCreate, ?int $excludeId = null): array
    {
        $errors = [];
        $email = strtolower(trim((string) ($body['email'] ?? '')));
        $name = trim((string) ($body['name'] ?? ''));

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Gyldig e-post er påkrevd';
        } elseif ($this->users->emailExists($email, $excludeId)) {
            $errors['email'] = 'E-post er allerede i bruk';
        }

        if ($name === '') {
            $errors['name'] = 'Navn er påkrevd';
        }

        if ($isCreate && trim((string) ($body['password'] ?? '')) === '') {
            $errors['password'] = 'Passord er påkrevd ved opprettelse';
        }

        return $errors;
    }

    /** @param array<string, mixed> $body @return array<string, string> */
    private function validateSeason(array $body, bool $isCreate): array
    {
        $errors = [];
        $name = trim((string) ($body['name'] ?? ''));
        $year = (int) ($body['year'] ?? 0);
        $tenantId = (int) ($body['tenant_id'] ?? 0);

        if ($isCreate && $tenantId <= 0) {
            $errors['tenant_id'] = 'Cup er påkrevd';
        }
        if ($name === '') {
            $errors['name'] = 'Navn er påkrevd';
        }
        if ($year < 2000 || $year > 2100) {
            $errors['year'] = 'År må være mellom 2000 og 2100';
        }

        return $errors;
    }

    /** @param array<string, mixed> $body @return array<string, string> */
    private function validateRound(array $body): array
    {
        $errors = [];
        $name = trim((string) ($body['name'] ?? ''));
        $roundNumber = (int) ($body['round_number'] ?? 0);

        if ($name === '') {
            $errors['name'] = 'Rundenavn er påkrevd';
        }
        if ($roundNumber < 1 || $roundNumber > 99) {
            $errors['round_number'] = 'Runde-nr må være mellom 1 og 99';
        }
        foreach (['start_date', 'end_date', 'result_deadline'] as $field) {
            $val = trim((string) ($body[$field] ?? ''));
            if ($val === '') {
                $errors[$field] = 'Dato er påkrevd';
            }
        }

        return $errors;
    }

    private function normalizeSlug(string $slug): string
    {
        return strtolower(trim($slug));
    }

    private function normalizeDomainPurpose(string $purpose): string
    {
        $purpose = strtolower(trim($purpose));

        return match ($purpose) {
            'organizer', 'arrangor', 'arrangør' => 'arrangor',
            default => $purpose,
        };
    }
}
