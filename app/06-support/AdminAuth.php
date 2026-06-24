<?php

declare(strict_types=1);

namespace App\Support;

final class AdminAuth
{
    /** @return array{status: int, headers: array<string, string>, body: string}|null */
    public static function requireAdmin(): ?array
    {
        Session::startRequired();
        $unauthorized = Auth::check();
        if ($unauthorized !== null) {
            return $unauthorized;
        }

        $user = Auth::user();
        if ($user === null || !($user['can_access_admin'] ?? false)) {
            return Response::json(['error' => 'Forbidden'], 403);
        }

        return null;
    }

    /** @param array<string, mixed> $user */
    public static function isSystemAdmin(array $user): bool
    {
        foreach ($user['system_roles'] ?? [] as $role) {
            if (is_array($role) && ($role['role'] ?? '') === 'SystemAdmin') {
                return true;
            }
        }

        return false;
    }

    /** @param array<string, mixed> $user */
    public static function canManageTenant(array $user, int $tenantId): bool
    {
        if (self::isSystemAdmin($user)) {
            return true;
        }

        foreach ($user['tenant_admin_access'] ?? [] as $access) {
            if (!is_array($access)) {
                continue;
            }
            if (($access['role'] ?? '') === 'CupAdmin' && (int) ($access['tenant_id'] ?? 0) === $tenantId) {
                return true;
            }
        }

        return false;
    }

    /** @param array<string, mixed> $user @return list<int> */
    public static function allowedTenantIds(array $user): array
    {
        if (self::isSystemAdmin($user)) {
            return [];
        }

        $ids = [];
        foreach ($user['tenant_admin_access'] ?? [] as $access) {
            if (is_array($access) && ($access['role'] ?? '') === 'CupAdmin') {
                $id = (int) ($access['tenant_id'] ?? 0);
                if ($id > 0) {
                    $ids[] = $id;
                }
            }
        }

        return array_values(array_unique($ids));
    }
}
