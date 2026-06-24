<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\AdminService;
use App\Support\Auth;
use App\Support\Request;
use App\Support\Response;

final class AdminOrganizationController
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
        $search = trim((string) ($_GET['q'] ?? $_GET['search'] ?? ''));
        if ($search !== '' && mb_strlen($search) < 3) {
            return Response::json([
                'organizations' => [],
                'q' => $search,
                'search_min_length' => 3,
            ]);
        }

        $orgs = $this->admin->listOrganizations(
            $user,
            $tenantId > 0 ? $tenantId : null,
            $search !== '' ? $search : null,
        );

        $payload = ['organizations' => $orgs];
        if ($tenantId > 0) {
            $payload['tenant_id'] = $tenantId;
        }
        if ($search !== '') {
            $payload['q'] = $search;
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

        $org = $this->admin->getOrganization($user, $id);
        if ($org === null) {
            return Response::json(['error' => 'Not Found'], 404);
        }

        return Response::json([
            'organization' => $org,
            'members' => $this->admin->listOrganizationMembers($user, $id),
        ]);
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

        $result = $this->admin->createOrganization($user, Request::jsonBody());
        if (isset($result['errors'])) {
            $status = isset($result['errors']['forbidden']) ? 403 : 422;

            return Response::json(['errors' => $result['errors']], $status);
        }

        return Response::json(['organization' => $result['organization']], 201);
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

        $result = $this->admin->updateOrganization($user, $id, Request::jsonBody());
        if (isset($result['errors'])) {
            $status = isset($result['errors']['not_found']) ? 404
                : (isset($result['errors']['forbidden']) ? 403 : 422);

            return Response::json(['errors' => $result['errors']], $status);
        }

        return Response::json(['organization' => $result['organization']]);
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

        if (!$this->admin->deactivateOrganization($user, $id)) {
            return Response::json(['error' => 'Not Found'], 404);
        }

        return Response::json(['ok' => true, 'status' => 'inactive']);
    }

    public function addMember(int $id): array
    {
        if ($denied = $this->admin->guard()) {
            return $denied;
        }

        $user = Auth::user();
        if ($user === null) {
            return Response::json(['error' => 'Unauthorized'], 401);
        }

        $result = $this->admin->addOrganizationMember($user, $id, Request::jsonBody());
        if (isset($result['errors'])) {
            $status = isset($result['errors']['forbidden']) ? 403 : 422;

            return Response::json(['errors' => $result['errors']], $status);
        }

        return Response::json(['member' => $result['member']], 201);
    }

    public function removeMember(int $id, int $memberId): array
    {
        if ($denied = $this->admin->guard()) {
            return $denied;
        }

        $user = Auth::user();
        if ($user === null) {
            return Response::json(['error' => 'Unauthorized'], 401);
        }

        if (!$this->admin->removeOrganizationMember($user, $id, $memberId)) {
            return Response::json(['error' => 'Not Found'], 404);
        }

        return Response::json(['ok' => true]);
    }
}
