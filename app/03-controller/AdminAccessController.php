<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\AdminService;
use App\Support\Auth;
use App\Support\Request;
use App\Support\Response;

final class AdminAccessController
{
    public function __construct(private readonly AdminService $admin)
    {
    }

    public function roles(): array
    {
        if ($denied = $this->admin->guard()) {
            return $denied;
        }

        $user = Auth::user();
        if ($user === null) {
            return Response::json(['error' => 'Unauthorized'], 401);
        }

        return Response::json(['roles' => $this->admin->listRoleDefinitions($user)]);
    }

    public function roleAssignmentsOverview(): array
    {
        if ($denied = $this->admin->guard()) {
            return $denied;
        }

        $user = Auth::user();
        if ($user === null) {
            return Response::json(['error' => 'Unauthorized'], 401);
        }

        return Response::json([
            'assignments' => $this->admin->listRoleAssignmentsOverview($user),
            'inline_limit' => AdminService::ROLE_ASSIGNMENT_INLINE_LIMIT,
        ]);
    }

    public function roleAssignments(string $role): array
    {
        if ($denied = $this->admin->guard()) {
            return $denied;
        }

        $user = Auth::user();
        if ($user === null) {
            return Response::json(['error' => 'Unauthorized'], 401);
        }

        $result = $this->admin->listRoleAssignments($user, $role);
        if ($result === null) {
            return Response::json(['error' => 'Forbidden'], 403);
        }

        return Response::json($result);
    }

    public function userAccess(int $id): array
    {
        if ($denied = $this->admin->guard()) {
            return $denied;
        }

        if ($this->admin->getUser($id) === null) {
            return Response::json(['error' => 'Not Found'], 404);
        }

        return Response::json($this->admin->getUserAccess($id));
    }

    public function grantSystemRole(int $id): array
    {
        if ($denied = $this->admin->guard()) {
            return $denied;
        }

        $actor = Auth::user();
        if ($actor === null) {
            return Response::json(['error' => 'Unauthorized'], 401);
        }

        $body = Request::jsonBody();
        $role = (string) ($body['role'] ?? 'SystemAdmin');
        $result = $this->admin->grantSystemRole($actor, $id, $role);
        if (!($result['ok'] ?? false)) {
            $status = isset($result['errors']['forbidden']) ? 403 : 422;

            return Response::json(['errors' => $result['errors'] ?? []], $status);
        }

        return Response::json(['ok' => true], 201);
    }

    public function revokeSystemRole(int $id, string $role): array
    {
        if ($denied = $this->admin->guard()) {
            return $denied;
        }

        $actor = Auth::user();
        if ($actor === null) {
            return Response::json(['error' => 'Unauthorized'], 401);
        }

        $result = $this->admin->revokeSystemRole($actor, $id, $role);
        if (!($result['ok'] ?? false)) {
            return Response::json(['errors' => $result['errors'] ?? []], 403);
        }

        return Response::json(['ok' => true]);
    }

    public function grantTenantAccess(int $id): array
    {
        if ($denied = $this->admin->guard()) {
            return $denied;
        }

        $actor = Auth::user();
        if ($actor === null) {
            return Response::json(['error' => 'Unauthorized'], 401);
        }

        $result = $this->admin->grantTenantAccess($actor, $id, Request::jsonBody());
        if (isset($result['errors'])) {
            $status = isset($result['errors']['forbidden']) ? 403 : 422;

            return Response::json(['errors' => $result['errors']], $status);
        }

        return Response::json(['access' => $result['access']], 201);
    }

    public function revokeTenantAccess(int $id, int $accessId): array
    {
        if ($denied = $this->admin->guard()) {
            return $denied;
        }

        $actor = Auth::user();
        if ($actor === null) {
            return Response::json(['error' => 'Unauthorized'], 401);
        }

        if (!$this->admin->revokeTenantAccess($actor, $id, $accessId)) {
            return Response::json(['error' => 'Not Found'], 404);
        }

        return Response::json(['ok' => true]);
    }
}
