<?php

declare(strict_types=1);

namespace App\Services;

final class TelegramGroupDialogCache
{
    public const TTL_SECONDS = 300;

    public function get(int $userId, int $accountId): ?array
    {
        $path = $this->path($userId, $accountId);

        if (!is_file($path)) {
            return null;
        }

        $contents = file_get_contents($path);
        $payload = is_string($contents) ? json_decode($contents, true) : null;

        if (!is_array($payload) || !is_array($payload['dialogs'] ?? null)) {
            @unlink($path);
            return null;
        }

        $cachedAt = (int) ($payload['cached_at'] ?? 0);
        $expiresAt = (int) ($payload['expires_at'] ?? 0);

        if ($cachedAt <= 0 || $expiresAt <= time()) {
            @unlink($path);
            return null;
        }

        return [
            'dialogs' => $payload['dialogs'],
            'cached_at' => $cachedAt,
            'expires_at' => $expiresAt,
        ];
    }

    public function put(int $userId, int $accountId, array $dialogs): array
    {
        $cachedAt = time();
        $payload = [
            'dialogs' => array_values($dialogs),
            'cached_at' => $cachedAt,
            'expires_at' => $cachedAt + self::TTL_SECONDS,
        ];
        $directory = dirname($this->path($userId, $accountId));

        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            return $payload;
        }

        $path = $this->path($userId, $accountId);
        $temporaryPath = $path . '.' . bin2hex(random_bytes(6)) . '.tmp';
        $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (is_string($encoded) && file_put_contents($temporaryPath, $encoded, LOCK_EX) !== false) {
            if (!rename($temporaryPath, $path)) {
                @unlink($temporaryPath);
            }
        }

        return $payload;
    }

    private function path(int $userId, int $accountId): string
    {
        return storage_path('cache/telegram-group-dialogs/user-' . $userId . '-account-' . $accountId . '.json');
    }
}
