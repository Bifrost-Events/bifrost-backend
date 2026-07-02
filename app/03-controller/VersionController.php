<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\VersionService;
use App\Support\Response;

final class VersionController
{
    public function __invoke(): array
    {
        return Response::json(VersionService::metadata());
    }
}
