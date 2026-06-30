<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\OnboardingService;
use App\Service\ParticipantService;
use App\Support\Auth;
use App\Support\Request;
use App\Support\Response;
use App\Support\Session;

final class ParticipantController
{
    public function __construct(
        private readonly ParticipantService $participants,
        private readonly ?OnboardingService $onboarding = null,
    ) {
    }

    private function onboarding(): OnboardingService
    {
        if ($this->onboarding !== null) {
            return $this->onboarding;
        }

        return (new \App\Support\Container())->getOnboardingService();
    }

    public function classes(): array
    {
        $result = $this->participants->listClasses();

        return $result['ok']
            ? Response::json($result['data'])
            : Response::json(['error' => $result['error']], $result['status']);
    }

    public function shooters(): array
    {
        if ($denied = Auth::check()) {
            return $denied;
        }

        $userId = Session::getUserId();
        if ($userId === null) {
            return Response::json(['error' => 'Unauthorized'], 401);
        }

        $result = $this->participants->listShooters($userId);

        return $result['ok']
            ? Response::json($result['data'])
            : Response::json(['error' => $result['error']], $result['status']);
    }

    public function createShooter(): array
    {
        if ($denied = Auth::check()) {
            return $denied;
        }

        $userId = Session::getUserId();
        if ($userId === null) {
            return Response::json(['error' => 'Unauthorized'], 401);
        }

        $result = $this->participants->createShooter($userId, Request::jsonBody());

        return $result['ok']
            ? Response::json($result['data'], 201)
            : Response::json(['error' => $result['error']], $result['status']);
    }

    public function updateShooter(int $id): array
    {
        if ($denied = Auth::check()) {
            return $denied;
        }

        $userId = Session::getUserId();
        if ($userId === null) {
            return Response::json(['error' => 'Unauthorized'], 401);
        }

        $result = $this->participants->updateShooter($userId, $id, Request::jsonBody());

        return $result['ok']
            ? Response::json($result['data'])
            : Response::json(['error' => $result['error']], $result['status']);
    }

    public function competitionSignup(int $competitionId): array
    {
        $host = trim((string) ($_GET['host'] ?? ''));
        if ($host === '') {
            return Response::json(['error' => 'Missing query parameter: host'], 400);
        }

        Session::startRequired();
        $userId = Session::getUserId();

        $result = $this->participants->competitionSignup($competitionId, $host, $userId);

        return $result['ok']
            ? Response::json($result['data'])
            : Response::json(['error' => $result['error']], $result['status']);
    }

    public function signups(): array
    {
        if ($denied = Auth::check()) {
            return $denied;
        }

        $host = trim((string) ($_GET['host'] ?? ''));
        if ($host === '') {
            return Response::json(['error' => 'Missing query parameter: host'], 400);
        }

        $userId = Session::getUserId();
        if ($userId === null) {
            return Response::json(['error' => 'Unauthorized'], 401);
        }

        $result = $this->participants->listSignups($userId, $host);

        return $result['ok']
            ? Response::json($result['data'])
            : Response::json(['error' => $result['error']], $result['status']);
    }

    public function register(): array
    {
        if ($denied = Auth::check()) {
            return $denied;
        }

        $host = trim((string) ($_GET['host'] ?? ''));
        if ($host === '') {
            return Response::json(['error' => 'Missing query parameter: host'], 400);
        }

        $userId = Session::getUserId();
        if ($userId === null) {
            return Response::json(['error' => 'Unauthorized'], 401);
        }

        $result = $this->participants->register($userId, $host, Request::jsonBody());

        return $result['ok']
            ? Response::json($result['data'])
            : Response::json(['error' => $result['error']], $result['status']);
    }

    public function unregister(): array
    {
        if ($denied = Auth::check()) {
            return $denied;
        }

        $host = trim((string) ($_GET['host'] ?? ''));
        if ($host === '') {
            return Response::json(['error' => 'Missing query parameter: host'], 400);
        }

        $userId = Session::getUserId();
        if ($userId === null) {
            return Response::json(['error' => 'Unauthorized'], 401);
        }

        $result = $this->participants->unregister($userId, $host, Request::jsonBody());

        return $result['ok']
            ? Response::json($result['data'])
            : Response::json(['error' => $result['error']], $result['status']);
    }

    public function profile(): array
    {
        if ($denied = Auth::check()) {
            return $denied;
        }

        $userId = Session::getUserId();
        if ($userId === null) {
            return Response::json(['error' => 'Unauthorized'], 401);
        }

        $result = $this->onboarding()->getProfile($userId);

        return $result['ok']
            ? Response::json($result['data'])
            : Response::json(['error' => $result['error']], $result['status']);
    }

    public function updateProfile(): array
    {
        if ($denied = Auth::check()) {
            return $denied;
        }

        $userId = Session::getUserId();
        if ($userId === null) {
            return Response::json(['error' => 'Unauthorized'], 401);
        }

        $result = $this->onboarding()->updateProfile($userId, Request::jsonBody());

        return $result['ok']
            ? Response::json($result['data'])
            : Response::json(['error' => $result['error']], $result['status']);
    }

    public function onboardingParticipant(): array
    {
        if ($denied = Auth::check()) {
            return $denied;
        }

        $userId = Session::getUserId();
        if ($userId === null) {
            return Response::json(['error' => 'Unauthorized'], 401);
        }

        $result = $this->onboarding()->onboardingParticipant($userId);

        return $result['ok']
            ? Response::json($result['data'])
            : Response::json(['error' => $result['error']], $result['status']);
    }

    public function claimParticipant(int $id): array
    {
        if ($denied = Auth::check()) {
            return $denied;
        }

        $userId = Session::getUserId();
        if ($userId === null) {
            return Response::json(['error' => 'Unauthorized'], 401);
        }

        $result = $this->onboarding()->claimParticipant($userId, $id);

        return $result['ok']
            ? Response::json($result['data'])
            : Response::json(['error' => $result['error']], $result['status']);
    }

    public function organizations(): array
    {
        if ($denied = Auth::check()) {
            return $denied;
        }

        $userId = Session::getUserId();
        if ($userId === null) {
            return Response::json(['error' => 'Unauthorized'], 401);
        }

        $result = $this->onboarding()->listOrganizations($userId);

        return $result['ok']
            ? Response::json($result['data'])
            : Response::json(['error' => $result['error']], $result['status']);
    }

    public function createOrganization(): array
    {
        if ($denied = Auth::check()) {
            return $denied;
        }

        $userId = Session::getUserId();
        if ($userId === null) {
            return Response::json(['error' => 'Unauthorized'], 401);
        }

        $body = Request::jsonBody();
        if (trim((string) ($_GET['host'] ?? '')) !== '' && !isset($body['host'])) {
            $body['host'] = trim((string) $_GET['host']);
        }

        $result = $this->onboarding()->createOrganization($userId, $body);

        return $result['ok']
            ? Response::json($result['data'], 201)
            : Response::json(['error' => $result['error']], $result['status']);
    }
}
