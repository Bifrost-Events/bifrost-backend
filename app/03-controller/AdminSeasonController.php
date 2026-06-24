<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\AdminService;
use App\Support\Auth;
use App\Support\Request;
use App\Support\Response;

final class AdminSeasonController
{
    public function __construct(private readonly AdminService $admin)
    {
    }

    public function index(): array
    {
        if ($denied = $this->admin->guard()) {
            return $denied;
        }

        $user = Auth::user();
        if ($user === null) {
            return Response::json(['error' => 'Unauthorized'], 401);
        }

        $tenantId = (int) ($_GET['tenant_id'] ?? 0);
        $seasons = $this->admin->listSeasons($user, $tenantId > 0 ? $tenantId : null);
        $payload = ['seasons' => $seasons];
        if ($tenantId > 0) {
            $payload['tenant_id'] = $tenantId;
        }

        return Response::json($payload);
    }

    public function show(int $id): array
    {
        if ($denied = $this->admin->guard()) {
            return $denied;
        }

        $user = Auth::user();
        if ($user === null) {
            return Response::json(['error' => 'Unauthorized'], 401);
        }

        $season = $this->admin->getSeason($user, $id);
        if ($season === null) {
            return Response::json(['error' => 'Not Found'], 404);
        }

        return Response::json(['season' => $season]);
    }

    public function store(): array
    {
        if ($denied = $this->admin->guard()) {
            return $denied;
        }

        $user = Auth::user();
        if ($user === null) {
            return Response::json(['error' => 'Unauthorized'], 401);
        }

        $result = $this->admin->createSeason($user, Request::jsonBody());
        if (isset($result['errors'])) {
            $status = isset($result['errors']['forbidden']) ? 403 : 422;

            return Response::json(['errors' => $result['errors']], $status);
        }

        return Response::json(['season' => $result['season']], 201);
    }

    public function update(int $id): array
    {
        if ($denied = $this->admin->guard()) {
            return $denied;
        }

        $user = Auth::user();
        if ($user === null) {
            return Response::json(['error' => 'Unauthorized'], 401);
        }

        $result = $this->admin->updateSeason($user, $id, Request::jsonBody());
        if (isset($result['errors'])) {
            $status = isset($result['errors']['not_found']) ? 404
                : (isset($result['errors']['forbidden']) ? 403 : 422);

            return Response::json(['errors' => $result['errors']], $status);
        }

        return Response::json(['season' => $result['season']]);
    }

    public function updateCupStandings(int $id): array
    {
        if ($denied = $this->admin->guard()) {
            return $denied;
        }

        $user = Auth::user();
        if ($user === null) {
            return Response::json(['error' => 'Unauthorized'], 401);
        }

        $result = $this->admin->updateSeasonCupStandings($user, $id, Request::jsonBody());
        if (isset($result['errors'])) {
            $status = isset($result['errors']['not_found']) ? 404 : 422;

            return Response::json(['errors' => $result['errors']], $status);
        }

        return Response::json(['season' => $result['season']]);
    }

    public function storeRound(int $seasonId): array
    {
        if ($denied = $this->admin->guard()) {
            return $denied;
        }

        $user = Auth::user();
        if ($user === null) {
            return Response::json(['error' => 'Unauthorized'], 401);
        }

        $result = $this->admin->createSeasonRound($user, $seasonId, Request::jsonBody());
        if (isset($result['errors'])) {
            $status = isset($result['errors']['not_found']) ? 404 : 422;

            return Response::json(['errors' => $result['errors']], $status);
        }

        return Response::json(['round' => $result['round']], 201);
    }
}
