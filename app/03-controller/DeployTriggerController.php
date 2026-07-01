<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\StagingResetTriggerService;
use App\Support\Environment;
use App\Support\Response;

/**
 * Prosesserer FTP-kød staging-reset (GET, kun staging).
 */
final class DeployTriggerController
{
    public function __construct(
        private readonly string $basePath,
        private readonly ?StagingResetTriggerService $triggerService = null,
    ) {
    }

    /**
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function handle(string $method): array
    {
        if (strtoupper($method) !== 'GET') {
            return Response::json([
                'status' => 'error',
                'message' => 'Method not allowed',
            ], 405);
        }

        if (!Environment::isStaging() || !Environment::isStagingHttpHost()) {
            return Response::json([
                'status' => 'error',
                'message' => 'Forbidden',
            ], 403);
        }

        $service = $this->triggerService ?? new StagingResetTriggerService($this->basePath);
        $result = $service->processPendingTrigger();

        if ($result === null) {
            return Response::json([
                'status' => 'noop',
                'message' => 'No pending staging reset trigger',
            ], 200);
        }

        return Response::json($result, 200);
    }
}
