<?php

declare(strict_types=1);

namespace App\Support;

/** Parser for distrikts-tagger (#nord, #Indre Namdal). */
final class DistriktTagParser
{
    private const MAX_TAGS = 20;
    private const MAX_TAG_LENGTH = 48;

    /** @return array{districts: list<string>, error: ?string} */
    public static function fromPost(array $post): array
    {
        return self::parseInput(trim((string) ($post['districts'] ?? $post['distrikter'] ?? '')));
    }

    /** @return array{districts: list<string>, error: ?string} */
    public static function parseInput(string $raw): array
    {
        if ($raw === '') {
            return ['districts' => [], 'error' => null];
        }

        $tags = [];
        $seen = [];
        foreach (self::extractRawTags($raw) as $candidate) {
            $tag = self::normalizeTag($candidate);
            if ($tag === '') {
                continue;
            }
            $key = mb_strtolower($tag, 'UTF-8');
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $tags[] = $tag;
            if (count($tags) > self::MAX_TAGS) {
                return ['districts' => [], 'error' => 'Maks ' . self::MAX_TAGS . ' distrikter.'];
            }
        }

        if ($tags === []) {
            return ['districts' => [], 'error' => 'Ingen gyldige distrikter. Bruk f.eks. #nord eller #Indre Namdal.'];
        }

        return ['districts' => $tags, 'error' => null];
    }

    /** @return list<string> */
    private static function extractRawTags(string $raw): array
    {
        $out = [];
        $remaining = $raw;
        while ($remaining !== '') {
            $remaining = ltrim($remaining);
            if ($remaining === '') {
                break;
            }
            if ($remaining[0] === '"' || $remaining[0] === "'") {
                $quote = $remaining[0];
                $end = strpos($remaining, $quote, 1);
                if ($end === false) {
                    $out[] = trim(substr($remaining, 1));
                    break;
                }
                $out[] = substr($remaining, 1, $end - 1);
                $remaining = substr($remaining, $end + 1);
                continue;
            }
            if (str_contains($remaining, '#') && preg_match('~\#([^\\#]+)~u', $remaining, $m, PREG_OFFSET_CAPTURE) === 1) {
                $out[] = (string) $m[1][0];
                $remaining = substr($remaining, $m[0][1] + strlen($m[0][0]));
                continue;
            }
            if (preg_match('~^([^,;]+)~u', $remaining, $m) === 1) {
                $chunk = trim((string) $m[1]);
                if ($chunk !== '') {
                    $out[] = $chunk;
                }
                $remaining = substr($remaining, strlen($m[0]));
                if (preg_match('/^[,;\s]+/u', $remaining, $sep) === 1) {
                    $remaining = substr($remaining, strlen($sep[0]));
                }
                continue;
            }
            break;
        }

        return $out;
    }

    public static function normalizeTag(string $part): string
    {
        $tag = trim($part);
        if ($tag === '') {
            return '';
        }
        if (str_starts_with($tag, '#')) {
            $tag = ltrim($tag, '#');
        }
        $tag = trim(preg_replace('/\s+/u', ' ', $tag) ?? $tag);
        if ($tag === '' || !preg_match('/^[\p{L}\p{N}\-_. ]+$/u', $tag)) {
            return '';
        }
        if (mb_strlen($tag, 'UTF-8') > self::MAX_TAG_LENGTH) {
            $tag = mb_substr($tag, 0, self::MAX_TAG_LENGTH, 'UTF-8');
        }

        return $tag;
    }

    /** @param list<string> $districts */
    public static function encodeForStorage(array $districts): ?string
    {
        $clean = [];
        $seen = [];
        foreach ($districts as $item) {
            $tag = self::normalizeTag((string) $item);
            if ($tag === '') {
                continue;
            }
            $key = mb_strtolower($tag, 'UTF-8');
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $clean[] = $tag;
            }
        }

        return $clean === [] ? null : json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
