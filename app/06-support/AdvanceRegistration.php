<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Forhåndspåmelding på nett (stevnekalender) – valgfritt per stevne.
 */
final class AdvanceRegistration
{
    public static function isEnabled(array $competition): bool
    {
        $raw = $competition['advance_registration_enabled'] ?? false;

        return $raw === true || $raw === 1 || $raw === '1';
    }

    public static function isOpenForPublic(array $competition, ?\DateTimeInterface $now = null): bool
    {
        if (!self::isEnabled($competition)) {
            return false;
        }

        $now = $now ?? new \DateTimeImmutable('today');
        $today = $now->format('Y-m-d');
        $start = $competition['registration_start'] ?? null;
        $end = $competition['registration_end'] ?? null;
        if (($start === null || $start === '') && ($end === null || $end === '')) {
            return true;
        }
        if ($start !== null && $start !== '' && $today < (string) $start) {
            return false;
        }
        if ($end !== null && $end !== '' && $today > (string) $end) {
            return false;
        }

        return true;
    }
}
