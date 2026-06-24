<?php

declare(strict_types=1);

namespace App\Service;

final class HealthService
{
    /**
     * @return array{status: string, service: string, timestamp: string, database?: string}
     */
    public static function status(?\PDO $pdo = null): array
    {
        $result = [
            'status' => 'ok',
            'service' => 'bifrost-backend',
            'timestamp' => date('c'),
        ];

        if ($pdo === null) {
            return $result;
        }

        try {
            $pdo->query('SELECT 1');
            $result['database'] = 'ok';
        } catch (\Throwable) {
            $result['status'] = 'degraded';
            $result['database'] = 'error';
        }

        return $result;
    }
}
