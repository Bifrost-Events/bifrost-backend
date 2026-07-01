<?php

declare(strict_types=1);

namespace App\Service;

use App\Support\DeployPathResolver;
use App\Support\Environment;

/**
 * FTP-kø for staging-reset: CI laster opp trigger-fil; server prosesserer via cron eller HTTP.
 */
final class StagingResetTriggerService
{
    public function __construct(
        private readonly string $basePath,
        private readonly ?StagingResetService $resetService = null,
    ) {
    }

    public static function triggerRelativePath(): string
    {
        return 'storage/framework/staging-reset.trigger';
    }

    public function triggerPath(): string
    {
        return rtrim($this->basePath, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, self::triggerRelativePath());
    }

    /**
     * @return array{queued_at: string, token: string}
     */
    public function buildTriggerPayload(string $secret): array
    {
        $queuedAt = gmdate('Y-m-d\TH:i:s\Z');
        $token = hash_hmac('sha256', $queuedAt, $secret);

        return [
            'queued_at' => $queuedAt,
            'token' => $token,
        ];
    }

    /**
     * @return array{status: string, environment: string, message: string, database: string, migrations: int, seeds: int}|null
     */
    public function processPendingTrigger(): ?array
    {
        if (!Environment::isStaging()) {
            return null;
        }

        $path = $this->triggerPath();
        if (!is_file($path)) {
            return null;
        }

        $secret = Environment::stagingDeploySecret();
        if ($secret === '') {
            return null;
        }

        $raw = file_get_contents($path);
        if ($raw === false || trim($raw) === '') {
            @unlink($path);

            return null;
        }

        try {
            /** @var array{queued_at?: string, token?: string} $payload */
            $payload = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            @unlink($path);

            return null;
        }

        $queuedAt = trim((string) ($payload['queued_at'] ?? ''));
        $token = trim((string) ($payload['token'] ?? ''));
        if ($queuedAt === '' || $token === '') {
            @unlink($path);

            return null;
        }

        $expected = hash_hmac('sha256', $queuedAt, $secret);
        if (!hash_equals($expected, $token)) {
            @unlink($path);

            return null;
        }

        @unlink($path);

        $service = $this->resetService ?? new StagingResetService($this->basePath);

        return $service->resetMigrateAndSeed();
    }

    public function lockFilePath(): string
    {
        return DeployPathResolver::lockFilePath($this->basePath);
    }
}
