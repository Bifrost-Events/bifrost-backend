<?php

declare(strict_types=1);

namespace App\Controller;

use App\Support\Auth;
use App\Support\Response;
use App\Support\Session;

final class AuthController
{
    public function login(): array
    {
        Session::startRequired();

        $input = $this->parseInput();
        $email = trim((string) ($input['email'] ?? ''));
        $password = (string) ($input['password'] ?? '');

        if ($email === '' || $password === '') {
            return Response::json(['error' => 'Email and password required'], 422);
        }

        $result = Auth::service()->login($email, $password);
        if (!$result['ok']) {
            return Response::json(['error' => $result['error']], $result['status']);
        }

        $user = $result['user'];
        if (!$user['can_access_admin']) {
            return Response::json(['error' => 'Access denied — admin role required'], 403);
        }

        Session::setUserId((int) $user['id']);

        return Response::json([
            'user' => $user,
            'session' => [
                'name' => session_name(),
                'id' => session_id(),
            ],
        ]);
    }

    public function logout(): array
    {
        Session::clear();

        return Response::json(['ok' => true]);
    }

    public function me(): array
    {
        $unauthorized = Auth::check();
        if ($unauthorized !== null) {
            return $unauthorized;
        }

        return Response::json(['user' => Auth::user()]);
    }

    /** @return array<string, mixed> */
    private function parseInput(): array
    {
        if ($_POST !== []) {
            return $_POST;
        }

        $raw = file_get_contents('php://input');
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

            return is_array($decoded) ? $decoded : [];
        } catch (\JsonException) {
            return [];
        }
    }
}
