<?php

declare(strict_types=1);

namespace App\Support;

/** Validering av postnummer/poststed for arrangør. */
final class OrganizerPostalInput
{
    /**
     * @return array{postal_code: ?string, city: ?string, error: ?string}
     */
    public static function fromPost(array $post): array
    {
        $postal = trim((string) ($post['postal_code'] ?? ''));
        $city = trim((string) ($post['city'] ?? ''));

        if ($postal === '') {
            return ['postal_code' => null, 'city' => $city !== '' ? $city : null, 'error' => null];
        }

        if (!preg_match('/^\d{4}$/', $postal)) {
            return ['postal_code' => null, 'city' => null, 'error' => 'Postnummer må være 4 siffer.'];
        }

        return [
            'postal_code' => $postal,
            'city' => $city !== '' ? $city : null,
            'error' => null,
        ];
    }
}
