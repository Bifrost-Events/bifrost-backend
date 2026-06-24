<?php



declare(strict_types=1);



namespace App\Service;



use App\Contracts\Repositories\UserRepositoryPort;



final class AuthService

{

    private const ADMIN_SYSTEM_ROLES = ['SystemAdmin'];

    private const ADMIN_TENANT_ROLES = ['CupAdmin'];



    public function __construct(private readonly UserRepositoryPort $userRepo)

    {

    }



    /**

     * @return array{ok: true, user: array<string, mixed>}|array{ok: false, error: string, status: int}

     */

    public function login(string $email, string $password): array

    {

        $user = $this->userRepo->findByEmail($email);

        if ($user === null || !(bool) $user['is_active']) {

            return ['ok' => false, 'error' => 'Invalid email or password', 'status' => 401];

        }



        $hash = (string) ($user['password_hash'] ?? '');

        if ($hash === '' || !password_verify($password, $hash)) {

            return ['ok' => false, 'error' => 'Invalid email or password', 'status' => 401];

        }



        $this->userRepo->touchLastLogin((int) $user['id']);

        $user = $this->userRepo->findById((int) $user['id']) ?? $user;



        return ['ok' => true, 'user' => $this->buildUserPayload($user)];

    }



    /**

     * @param array<string, mixed> $user

     * @return array<string, mixed>

     */

    public function buildUserPayload(array $user): array

    {

        $userId = (int) $user['id'];

        $systemRoles = $this->userRepo->getSystemRoles($userId);

        $tenantAdminAccess = $this->userRepo->getTenantAdminAccess($userId);



        $name = trim((string) ($user['name'] ?? ''));

        if ($name === '') {

            $name = trim((string) ($user['first_name'] ?? '') . ' ' . (string) ($user['last_name'] ?? ''));

        }



        $firstRegisteredTenantId = $user['first_registered_tenant_id'] ?? null;

        $firstRegisteredTenant = null;

        if ($firstRegisteredTenantId !== null && (int) $firstRegisteredTenantId > 0) {

            $firstRegisteredTenant = [

                'id' => (int) $firstRegisteredTenantId,

                'slug' => (string) ($user['first_registered_tenant_slug'] ?? ''),

                'name' => (string) ($user['first_registered_tenant_name'] ?? ''),

            ];

        }



        return [

            'id' => $userId,

            'name' => $name,

            'email' => $user['email'],

            'phone' => $user['phone'] ?? null,

            'active' => (bool) $user['is_active'],

            'first_registered_tenant_id' => $firstRegisteredTenantId !== null ? (int) $firstRegisteredTenantId : null,

            'first_registered_tenant' => $firstRegisteredTenant,

            'last_login_at' => $user['last_login_at'] ?? null,

            'system_roles' => array_map(static fn (array $row): array => [

                'role' => (string) $row['role'],

                'created_at' => $row['created_at'] ?? null,

            ], $systemRoles),

            'tenant_admin_access' => array_map(static fn (array $row): array => [

                'tenant_id' => (int) $row['tenant_id'],

                'tenant_slug' => (string) $row['tenant_slug'],

                'tenant_name' => (string) $row['tenant_name'],

                'role' => (string) $row['role'],

                'created_at' => $row['created_at'] ?? null,

            ], $tenantAdminAccess),

            'can_access_admin' => $this->canAccessAdmin($systemRoles, $tenantAdminAccess),

        ];

    }



    /**

     * @param list<array<string, mixed>> $systemRoles

     * @param list<array<string, mixed>> $tenantAdminAccess

     */

    public function canAccessAdmin(array $systemRoles, array $tenantAdminAccess): bool

    {

        foreach ($systemRoles as $row) {

            if (in_array((string) ($row['role'] ?? ''), self::ADMIN_SYSTEM_ROLES, true)) {

                return true;

            }

        }



        foreach ($tenantAdminAccess as $row) {

            if (in_array((string) ($row['role'] ?? ''), self::ADMIN_TENANT_ROLES, true)) {

                return true;

            }

        }



        return false;

    }



    /** @return list<string> */

    public static function adminSystemRoles(): array

    {

        return self::ADMIN_SYSTEM_ROLES;

    }



    /** @return list<string> */

    public static function adminTenantRoles(): array

    {

        return self::ADMIN_TENANT_ROLES;

    }

}

