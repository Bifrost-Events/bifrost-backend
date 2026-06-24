<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\AdminService;
use App\Support\Request;
use App\Support\Response;

final class AdminUserController
{
    public function __construct(private readonly AdminService $admin)
    {
    }

    public function index(): array
    {
        if ($denied = $this->admin->guard()) {
            return $denied;
        }

        $search = trim((string) ($_GET['q'] ?? $_GET['search'] ?? ''));
        $limit = max(1, min(100, (int) ($_GET['limit'] ?? 50)));

        if ($search !== '' && mb_strlen($search) < 3) {
            $users = [];
            $payload = [
                'users' => $users,
                'q' => $search,
                'search_min_length' => 3,
            ];
        } elseif ($search !== '') {
            $users = $this->admin->searchUsers($search, $limit);
            $payload = ['users' => $users, 'q' => $search];
        } else {
            $users = $this->admin->listUsers();
            $payload = ['users' => $users];
        }

        return Response::json($payload);
    }

    public function show(int $id): array
    {
        if ($denied = $this->admin->guard()) {
            return $denied;
        }

        $user = $this->admin->getUser($id);
        if ($user === null) {
            return Response::json(['error' => 'Not Found'], 404);
        }

        return Response::json(['user' => $user]);
    }

    public function store(): array
    {
        if ($denied = $this->admin->guard()) {
            return $denied;
        }

        $result = $this->admin->createUser(Request::jsonBody());
        if (isset($result['errors'])) {
            return Response::json(['errors' => $result['errors']], 422);
        }

        return Response::json(['user' => $result['user']], 201);
    }

    public function update(int $id): array
    {
        if ($denied = $this->admin->guard()) {
            return $denied;
        }

        $result = $this->admin->updateUser($id, Request::jsonBody());
        if (isset($result['errors'])) {
            $status = isset($result['errors']['not_found']) ? 404 : 422;

            return Response::json(['errors' => $result['errors']], $status);
        }

        return Response::json(['user' => $result['user']]);
    }

    public function destroy(int $id): array
    {
        if ($denied = $this->admin->guard()) {
            return $denied;
        }

        if (!$this->admin->deactivateUser($id)) {
            return Response::json(['error' => 'Not Found'], 404);
        }

        return Response::json(['ok' => true, 'is_active' => false]);
    }
}
