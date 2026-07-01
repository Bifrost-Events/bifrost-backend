<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\StagingResetService;
use App\Support\Environment;
use App\Support\Response;

/**
 * HTTPS-endepunkt for staging database reset (kun POST, Bearer-token).
 */
final class DeployResetController
{
    public function __construct(
        private readonly string $basePath,
        private readonly ?StagingResetService $resetService = null,
    ) {
    }

    /**
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function handle(string $method): array
    {
        if (strtoupper($method) !== 'POST') {
            return Response::json([
                'status' => 'error',
                'message' => 'Method not allowed',
            ], 405);
        }

        if (!Environment::isStaging()) {
            return Response::json([
                'status' => 'error',
                'message' => 'Forbidden',
            ], 403);
        }

        if (!Environment::isStagingHttpHost()) {
            return Response::json([
                'status' => 'error',
                'message' => 'Forbidden',
            ], 403);
        }

        if (!$this->isAuthorized()) {
            return Response::json([
                'status' => 'error',
                'message' => 'Forbidden',
            ], 403);
        }

        try {
            $service = $this->resetService ?? new StagingResetService($this->basePath);
            $result = $service->resetMigrateAndSeed();

            return Response::json($result, 200);
        } catch (\Throwable $e) {
            error_log('[staging-reset] ' . $e->getMessage());

            return Response::json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    private function isAuthorized(): bool
    {
        $expected = Environment::stagingDeploySecret();
        if ($expected === '') {
            return false;
        }

        $header = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
        if ($header === '' && isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $header = (string) $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        }

        if (!preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
            return false;
        }

        $provided = trim($matches[1]);

        return hash_equals($expected, $provided);
    }
}
