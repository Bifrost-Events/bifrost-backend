<?php



declare(strict_types=1);



namespace App\Repositories\Pdo;



use App\Contracts\Repositories\UserRepositoryPort;



final class PdoUserRepository implements UserRepositoryPort

{

    public function __construct(private readonly \PDO $pdo)

    {

    }



    public function findByEmail(string $email): ?array

    {

        $stmt = $this->pdo->prepare(

            'SELECT u.id, u.email, u.password_hash, u.name, u.first_name, u.last_name, u.phone,

                    u.is_active, u.first_registered_tenant_id, u.created_at, u.updated_at, u.last_login_at,

                    t.slug AS first_registered_tenant_slug, t.name AS first_registered_tenant_name

             FROM auth_users u

             LEFT JOIN tenants t ON t.id = u.first_registered_tenant_id

             WHERE u.email = ? LIMIT 1'

        );

        $stmt->execute([trim($email)]);



        $row = $stmt->fetch();

        return $row === false ? null : $row;

    }



    public function findById(int $id): ?array

    {

        $stmt = $this->pdo->prepare(

            'SELECT u.id, u.email, u.name, u.first_name, u.last_name, u.phone,

                    u.is_active, u.first_registered_tenant_id, u.created_at, u.updated_at, u.last_login_at,

                    t.slug AS first_registered_tenant_slug, t.name AS first_registered_tenant_name

             FROM auth_users u

             LEFT JOIN tenants t ON t.id = u.first_registered_tenant_id

             WHERE u.id = ? LIMIT 1'

        );

        $stmt->execute([$id]);



        $row = $stmt->fetch();

        return $row === false ? null : $row;

    }



    public function getSystemRoles(int $userId): array

    {

        $stmt = $this->pdo->prepare(

            'SELECT role, created_at

             FROM auth_system_roles

             WHERE auth_user_id = ?

             ORDER BY role'

        );

        $stmt->execute([$userId]);



        return $stmt->fetchAll();

    }



    public function getTenantAdminAccess(int $userId): array

    {

        $stmt = $this->pdo->prepare(

            'SELECT ata.tenant_id, ata.role, ata.created_at,

                    tn.slug AS tenant_slug, tn.name AS tenant_name

             FROM auth_tenant_admin_access ata

             INNER JOIN tenants tn ON tn.id = ata.tenant_id

             WHERE ata.auth_user_id = ?

             ORDER BY tn.slug, ata.role'

        );

        $stmt->execute([$userId]);



        return $stmt->fetchAll();

    }



    public function touchLastLogin(int $userId): void

    {

        $stmt = $this->pdo->prepare('UPDATE auth_users SET last_login_at = CURRENT_TIMESTAMP WHERE id = ?');

        $stmt->execute([$userId]);

    }

}

