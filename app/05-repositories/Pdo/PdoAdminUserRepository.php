<?php

declare(strict_types=1);

namespace App\Repositories\Pdo;

use App\Contracts\Repositories\AdminUserRepositoryPort;

final class PdoAdminUserRepository implements AdminUserRepositoryPort
{
    public function __construct(private readonly \PDO $pdo)
    {
    }

    public function findAll(): array
    {
        $stmt = $this->pdo->query(
            'SELECT u.id, u.email, u.name, u.first_name, u.last_name, u.phone, u.is_active,
                    u.first_registered_tenant_id, u.created_at, u.updated_at, u.last_login_at,
                    t.slug AS first_registered_tenant_slug, t.name AS first_registered_tenant_name
             FROM auth_users u
             LEFT JOIN tenants t ON t.id = u.first_registered_tenant_id
             ORDER BY u.email ASC'
        );

        return $stmt->fetchAll();
    }

    public function search(string $query, int $limit = 50): array
    {
        $query = trim($query);
        if ($query === '' || mb_strlen($query) < 3) {
            return [];
        }

        $limit = max(1, min(100, $limit));
        $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $query) . '%';
        $idMatch = ctype_digit($query) ? (int) $query : 0;

        $conditions = [
            'u.email LIKE ?',
            'u.name LIKE ?',
            'u.first_name LIKE ?',
            'u.last_name LIKE ?',
            "COALESCE(u.phone, '') LIKE ?",
        ];
        $params = [$like, $like, $like, $like, $like];
        if ($idMatch > 0) {
            $conditions[] = 'u.id = ?';
            $params[] = $idMatch;
        }

        $sql = 'SELECT u.id, u.email, u.name, u.first_name, u.last_name, u.phone, u.is_active,
                    u.first_registered_tenant_id, u.created_at, u.updated_at, u.last_login_at,
                    t.slug AS first_registered_tenant_slug, t.name AS first_registered_tenant_name
             FROM auth_users u
             LEFT JOIN tenants t ON t.id = u.first_registered_tenant_id
             WHERE (' . implode(' OR ', $conditions) . ')
             ORDER BY u.email ASC
             LIMIT ' . $limit;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT u.id, u.email, u.name, u.first_name, u.last_name, u.phone, u.is_active,
                    u.first_registered_tenant_id, u.created_at, u.updated_at, u.last_login_at,
                    t.slug AS first_registered_tenant_slug, t.name AS first_registered_tenant_name
             FROM auth_users u
             LEFT JOIN tenants t ON t.id = u.first_registered_tenant_id
             WHERE u.id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public function create(array $data): array
    {
        $name = trim((string) ($data['name'] ?? ''));
        $parts = $name !== '' ? preg_split('/\s+/', $name, 2) : ['', ''];
        $firstName = $parts[0] ?? '';
        $lastName = $parts[1] ?? '';

        $stmt = $this->pdo->prepare(
            'INSERT INTO auth_users (email, password_hash, name, first_name, last_name, phone, is_active, first_registered_tenant_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            strtolower(trim((string) $data['email'])),
            $data['password_hash'],
            $name,
            $firstName,
            $lastName,
            $data['phone'] ?? null,
            !empty($data['is_active']) ? 1 : 0,
            $data['first_registered_tenant_id'] ?? null,
        ]);

        return $this->findById((int) $this->pdo->lastInsertId()) ?? [];
    }

    public function update(int $id, array $data): ?array
    {
        $name = trim((string) ($data['name'] ?? ''));
        $parts = $name !== '' ? preg_split('/\s+/', $name, 2) : ['', ''];
        $firstName = $parts[0] ?? '';
        $lastName = $parts[1] ?? '';

        $fields = [
            'email = ?',
            'name = ?',
            'first_name = ?',
            'last_name = ?',
            'phone = ?',
            'is_active = ?',
            'first_registered_tenant_id = ?',
            'updated_at = CURRENT_TIMESTAMP',
        ];
        $params = [
            strtolower(trim((string) $data['email'])),
            $name,
            $firstName,
            $lastName,
            $data['phone'] ?? null,
            !empty($data['is_active']) ? 1 : 0,
            $data['first_registered_tenant_id'] ?? null,
        ];

        if (!empty($data['password_hash'])) {
            $fields[] = 'password_hash = ?';
            $params[] = $data['password_hash'];
        }

        $params[] = $id;
        $sql = 'UPDATE auth_users SET ' . implode(', ', $fields) . ' WHERE id = ?';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $this->findById($id);
    }

    public function deactivate(int $id): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE auth_users SET is_active = 0, updated_at = CURRENT_TIMESTAMP WHERE id = ?'
        );
        $stmt->execute([$id]);

        return $stmt->rowCount() > 0;
    }

    public function emailExists(string $email, ?int $excludeId = null): bool
    {
        $email = strtolower(trim($email));
        if ($excludeId !== null) {
            $stmt = $this->pdo->prepare('SELECT 1 FROM auth_users WHERE email = ? AND id <> ? LIMIT 1');
            $stmt->execute([$email, $excludeId]);
        } else {
            $stmt = $this->pdo->prepare('SELECT 1 FROM auth_users WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
        }

        return (bool) $stmt->fetchColumn();
    }
}
