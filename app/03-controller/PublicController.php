<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\PublicReadService;
use App\Support\Response;

final class PublicController
{
    public function __construct(private readonly PublicReadService $publicRead)
    {
    }

    public function calendar(): array
    {
        $host = trim((string) ($_GET['host'] ?? ''));
        if ($host === '') {
            return Response::json(['error' => 'Missing query parameter: host'], 400);
        }

        $result = $this->publicRead->calendar($host);

        return $result['ok']
            ? Response::json($result['data'])
            : Response::json(['error' => $result['error']], $result['status']);
    }

    public function resultsIndex(): array
    {
        $host = trim((string) ($_GET['host'] ?? ''));
        if ($host === '') {
            return Response::json(['error' => 'Missing query parameter: host'], 400);
        }

        $result = $this->publicRead->resultsIndex($host);

        return $result['ok']
            ? Response::json($result['data'])
            : Response::json(['error' => $result['error']], $result['status']);
    }

    public function competition(int $id): array
    {
        $host = trim((string) ($_GET['host'] ?? ''));
        if ($host === '') {
            return Response::json(['error' => 'Missing query parameter: host'], 400);
        }

        $result = $this->publicRead->competition($id, $host);

        return $result['ok']
            ? Response::json($result['data'])
            : Response::json(['error' => $result['error']], $result['status']);
    }

    public function competitionResults(int $id): array
    {
        $host = trim((string) ($_GET['host'] ?? ''));
        if ($host === '') {
            return Response::json(['error' => 'Missing query parameter: host'], 400);
        }

        $result = $this->publicRead->competitionResults($id, $host);

        return $result['ok']
            ? Response::json($result['data'])
            : Response::json(['error' => $result['error']], $result['status']);
    }

    public function standings(): array
    {
        $host = trim((string) ($_GET['host'] ?? ''));
        if ($host === '') {
            return Response::json(['error' => 'Missing query parameter: host'], 400);
        }

        $result = $this->publicRead->standings($host);

        return $result['ok']
            ? Response::json($result['data'])
            : Response::json(['error' => $result['error']], $result['status']);
    }
}
