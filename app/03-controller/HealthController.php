<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\HealthService;
use App\Support\Container;
use App\Support\Response;

final class HealthController
{
    public function __invoke(): array
    {
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
