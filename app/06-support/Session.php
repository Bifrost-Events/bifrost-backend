<?php

declare(strict_types=1);

namespace App\Support;

final class Session
{
    private const SESSION_COOKIE_NAME = 'BIFROSTSESSID';

    private const SESSION_KEY = 'bifrost_auth_user_id';

    /** @var bool|null */
    private static ?bool $configured = null;

    public static function isActive(): bool
    {
        return session_status() === PHP_SESSION_ACTIVE;
    }

    public static function startRequired(): void
    {
        if (self::isActive()) {
            return;
        }

        self::configureCookieParams();
        session_name(self::SESSION_COOKIE_NAME);
        session_start();
    }

    public static function setUserId(int $userId): void
    {
        self::startRequired();
        $_SESSION[self::SESSION_KEY] = $userId;
    }

    public static function getUserId(): ?int
    {
        if (!self::isActive() && !isset($_COOKIE[self::SESSION_COOKIE_NAME])) {
            return null;
        }

        self::startRequired();
        $id = $_SESSION[self::SESSION_KEY] ?? null;

        return is_int($id) ? $id : (is_numeric($id) ? (int) $id : null);
    }

    public static function clear(): void
    {
        if (!self::isActive()) {
            return;
        }

        unset($_SESSION[self::SESSION_KEY]);
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 3600,
                'path' => $p['path'] ?? '/',
                'domain' => $p['domain'] ?? '',
                'secure' => (bool) ($p['secure'] ?? false),
                'httponly' => (bool) ($p['httponly'] ?? true),
                'samesite' => $p['samesite'] ?? 'Lax',
            ]);
        }
        session_destroy();
    }

    private static function configureCookieParams(): void
    {
        if (self::$configured === true) {
            return;
        }

        $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
        $domain = '';
        if (str_ends_with($host, '.bifrost.local') || $host === 'bifrost.local') {
            $domain = '.bifrost.local';
        }

        $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => $domain,
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        self::$configured = true;
    }
}
