<?php

declare(strict_types=1);

namespace App\Controller;

use App\Support\Auth;
use App\Support\Container;
use App\Support\Response;
use App\Support\Session;

final class AuthController
{
    public function __construct(private readonly ?Container $container = null)
    {
    }

    private function container(): Container
    {
        return $this->container ?? new Container();
    }
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

    public function participantLogin(): array
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
        Session::setUserId((int) $user['id']);

        return Response::json([
            'success' => true,
            'user' => $user,
            'session' => [
                'name' => session_name(),
                'id' => session_id(),
            ],
        ]);
    }

    public function participantRegister(): array
    {
        Session::startRequired();

        $input = $this->parseInput();
        $email = trim((string) ($input['email'] ?? ''));
        $password = (string) ($input['password'] ?? '');
        $firstName = trim((string) ($input['first_name'] ?? ''));
        $lastName = trim((string) ($input['last_name'] ?? ''));
        $phone = trim((string) ($input['phone'] ?? ''));
        $name = trim((string) ($input['name'] ?? ''));
        if ($firstName === '' && $lastName === '' && $name !== '') {
            $parts = preg_split('/\s+/', $name, 2) ?: ['', ''];
            $firstName = (string) ($parts[0] ?? '');
            $lastName = (string) ($parts[1] ?? '');
        }
        $tenantId = isset($input['tenant_id']) ? (int) $input['tenant_id'] : null;
        if ($tenantId !== null && $tenantId <= 0) {
            $tenantId = null;
        }
        $userAgreementVersion = trim((string) ($input['user_agreement_version'] ?? ''));

        $result = Auth::service()->registerParticipant($email, $password, $firstName, $lastName, $phone, $tenantId);
        if (!$result['ok']) {
            $payload = ['error' => $result['error']];
            if (isset($result['errors'])) {
                $payload['errors'] = $result['errors'];
            }

            return Response::json($payload, $result['status']);
        }

        $user = $result['user'];
        $userId = (int) $user['id'];
        Session::setUserId($userId);

        $onboardingData = [];
        if ($userAgreementVersion !== '') {
            $onboardingResult = $this->container()->getOnboardingService()->completeRegistration(
                $userId,
                $firstName,
                $lastName,
                $phone,
                $userAgreementVersion,
            );
            if ($onboardingResult['ok']) {
                $onboardingData = $onboardingResult['data'];
            }
        }

        return Response::json([
            'success' => true,
            'user' => $user,
            'onboarding' => $onboardingData,
            'session' => [
                'name' => session_name(),
                'id' => session_id(),
            ],
        ], 201);
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
