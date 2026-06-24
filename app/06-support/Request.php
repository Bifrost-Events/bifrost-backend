<?php

declare(strict_types=1);

namespace App\Support;

final class Request
{
    /** @return array<string, mixed> */
    public static function jsonBody(): array
    {
        if ($_POST !== []) {
            return $_POST;
        }

        $raw = file_get_contents('php://input');
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

            return is_array($decoded) ? $decoded : [];
        } catch (\JsonException) {
            return [];
        }
    }
}
