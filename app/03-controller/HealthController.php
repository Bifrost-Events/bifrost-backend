<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\HealthService;
use App\Service\StagingResetTriggerService;
use App\Support\Container;
use App\Support\Environment;
use App\Support\Response;

final class HealthController
{
    public function __invoke(): array
    {
        if (Environment::isStaging()) {
            try {
                $basePath = dirname(__DIR__, 2);
                (new StagingResetTriggerService($basePath))->processPendingTrigger();
            } catch (\Throwable $e) {
                error_log('[staging-reset-trigger] ' . $e->getMessage());
            }
        }

        $container = new Container();
        $pdo = null;

        try {
            if (($_ENV['STORAGE_DRIVER'] ?? '') === 'pdo') {
                $pdo = $container->getPdo();
            }
        } catch (\Throwable) {
            $pdo = null;
        }

        return Response::json(HealthService::status($pdo));
    }
}
