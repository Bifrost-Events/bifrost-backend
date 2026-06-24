<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\AdminService;
use App\Support\Auth;
use App\Support\Request;
use App\Support\Response;

final class AdminDomainController
{
    public function __construct(private readonly AdminService $admin)
    {
    }

    public function index(int $tenantId): array
    {
        if ($denied = $this->admin->guard()) {
            return $denied;
        }

        $user = Auth::user();
        if ($user === null) {
            return Response::json(['error' => 'Unauthorized'], 401);
        }

        $domains = $this->admin->listDomains($user, $tenantId);
        if ($domains === null) {
            return Response::json(['error' => 'Forbidden'], 403);
        }

        return Response::json(['domains' => $domains]);
    }

    public function store(int $tenantId): array
    {
        if ($denied = $this->admin->guard()) {
            return $denied;
        }

        $user = Auth::user();
        if ($user === null) {
            return Response::json(['error' => 'Unauthorized'], 401);
        }

        $result = $this->admin->createDomain($user, $tenantId, Request::jsonBody());
        if (isset($result['errors'])) {
            $status = isset($result['errors']['forbidden']) ? 403 : 422;

            return Response::json(['errors' => $result['errors']], $status);
        }

        return Response::json(['domain' => $result['domain']], 201);
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

        $result = $this->admin->updateDomain($user, $id, Request::jsonBody());
        if (isset($result['errors'])) {
            $status = 422;
            if (isset($result['errors']['forbidden'])) {
                $status = 403;
            } elseif (isset($result['errors']['not_found'])) {
                $status = 404;
            }

            return Response::json(['errors' => $result['errors']], $status);
        }

        return Response::json(['domain' => $result['domain']]);
    }

    public function destroy(int $id): array
    {
        if ($denied = $this->admin->guard()) {
            return $denied;
        }

        $user = Auth::user();
        if ($user === null) {
            return Response::json(['error' => 'Unauthorized'], 401);
        }

        if (!$this->admin->deleteDomain($user, $id)) {
            return Response::json(['error' => 'Not Found'], 404);
        }

        return Response::json(['ok' => true]);
    }
}
