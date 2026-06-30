<?php

declare(strict_types=1);

namespace App\Support;

use App\Contracts\Repositories\UserRepositoryPort;
use App\Repositories\Pdo\PdoUserRepository;
use App\Service\AuthService;

final class Auth
{
    public static function service(): AuthService
    {
        $container = new Container();

        return new AuthService(
            new PdoUserRepository($container->getPdo()),
            $container->getAdminUserRepo(),
        );
    }

    /** @return array<string, mixed>|null */
    public static function user(): ?array
    {
        $userId = Session::getUserId();
        if ($userId === null) {
            return null;
        }

        $pdo = (new Container())->getPdo();
        $repo = new PdoUserRepository($pdo);
        $user = $repo->findById($userId);
        if ($user === null || !(bool) $user['is_active']) {
            Session::clear();

            return null;
        }

        return self::service()->buildUserPayload($user);
    }

    public static function check(): ?array
    {
        $user = self::user();
        if ($user === null) {
            return Response::json(['error' => 'Unauthorized'], 401);
        }

        return null;
    }
}
