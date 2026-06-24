<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\AdminService;
use App\Support\Auth;
use App\Support\Request;
use App\Support\Response;

final class AdminTenantController
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

        return Response::json(['tenants' => $this->admin->listTenants($user)]);
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

        $tenant = $this->admin->getTenant($user, $id);
        if ($tenant === null) {
            return Response::json(['error' => 'Not Found'], 404);
        }

        return Response::json(['tenant' => $tenant]);
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

        $result = $this->admin->createTenant($user, Request::jsonBody());
        if (isset($result['errors'])) {
            $status = isset($result['errors']['forbidden']) ? 403 : 422;

            return Response::json(['errors' => $result['errors']], $status);
        }

        return Response::json(['tenant' => $result['tenant']], 201);
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

        $result = $this->admin->updateTenant($user, $id, Request::jsonBody());
        if (isset($result['errors'])) {
            $status = 422;
            if (isset($result['errors']['forbidden'])) {
                $status = 403;
            } elseif (isset($result['errors']['not_found'])) {
                $status = 404;
            }

            return Response::json(['errors' => $result['errors']], $status);
        }

        return Response::json(['tenant' => $result['tenant']]);
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

        if (!$this->admin->deactivateTenant($user, $id)) {
            return Response::json(['error' => 'Not Found'], 404);
        }

        return Response::json(['ok' => true, 'status' => 'inactive']);
    }
}
