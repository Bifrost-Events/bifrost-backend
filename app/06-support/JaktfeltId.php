<?php

declare(strict_types=1);

namespace App\Support;

/** Jaktfelt-ID: 6-sifret tall (100000–999999). Prefiks JC- kun for visning. */
final class JaktfeltId
{
    private const MIN = 100000;
    private const MAX = 999999;

    public function __construct(public readonly string $value)
    {
        if ($value === '') {
            throw new \InvalidArgumentException('Jaktfelt-ID kan ikke være tom');
        }
    }

    public static function generateRandom(): self
    {
        return new self((string) random_int(self::MIN, self::MAX));
    }

    public function displayValue(string $prefix = 'JC-'): string
    {
        return $prefix . $this->value;
    }
}
