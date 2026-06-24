<?php

declare(strict_types=1);

namespace App\Support;

class Response
{
    /**
     * @param array<mixed> $data
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public static function json(array $data, int $status = 200): array
    {
        return [
            'status' => $status,
            'headers' => ['Content-Type' => 'application/json; charset=utf-8'],
            'body' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        ];
    }

    /**
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public static function html(string $body, int $status = 200): array
    {
        return [
            'status' => $status,
            'headers' => ['Content-Type' => 'text/html; charset=utf-8'],
            'body' => $body,
        ];
    }
}
