<?php

declare(strict_types=1);

namespace App\Controller;

use App\Support\Container;
use App\Support\Environment;
use App\Support\Response;

final class TenantController
{
    public function index(): array
    {
        try {
            $tenants = (new Container())->getTenantRepo()->findAllWithDomains();

            return Response::json(['tenants' => $tenants]);
        } catch (\Throwable $e) {
            return Response::json([
                'error' => 'Database error',
                'message' => Environment::safeMessage($e),
            ], 500);
        }
    }

    public function show(int $id): array
    {
        try {
            $tenant = (new Container())->getTenantRepo()->findByIdWithDomains($id);
            if ($tenant === null) {
                return Response::json(['error' => 'Tenant not found'], 404);
            }

            return Response::json(['tenant' => $tenant]);
        } catch (\Throwable $e) {
            return Response::json([
                'error' => 'Database error',
                'message' => Environment::safeMessage($e),
            ], 500);
        }
    }

    public function resolve(): array
    {
        $host = trim((string) ($_GET['host'] ?? ''));
        if ($host === '') {
            return Response::json(['error' => 'Missing query parameter: host'], 400);
        }

        try {
            $tenant = (new Container())->getTenantRepo()->findByHost($host);
            if ($tenant === null) {
                return Response::json(['error' => 'Tenant not found for host'], 404);
            }

            return Response::json(['tenant' => $tenant]);
        } catch (\Throwable $e) {
            return Response::json([
                'error' => 'Database error',
                'message' => Environment::safeMessage($e),
            ], 500);
        }
    }
}
