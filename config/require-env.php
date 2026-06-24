<?php

declare(strict_types=1);

function require_env(string $key): void
{
    if (!isset($_ENV[$key]) || (string) $_ENV[$key] === '') {
        throw new RuntimeException(
            'Manglende påkrevd miljøvariabel: ' . $key . '. Kopier .env.example til .env.'
        );
    }
}
