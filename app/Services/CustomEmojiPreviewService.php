<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\TelegramAccount;
use RuntimeException;
use Throwable;

class CustomEmojiPreviewService
{
    public function __construct(
        private readonly TelegramAccount $accounts = new TelegramAccount(),
        private readonly TelegramService $telegram = new TelegramService()
    ) {
    }

    public function resolve(array $emoji, int $viewerUserId): ?array
    {
        $identifier = trim((string) ($emoji['emoji_identifier'] ?? ''));

        if (!preg_match('/^[0-9]{5,40}$/', $identifier)) {
            return null;
        }

        $cached = $this->cachedPreview($identifier);

        if ($cached !== null) {
            return $cached;
        }

        $account = $this->accounts->firstConnectedForUser($viewerUserId);

        if ($account === null) {
            return null;
        }

        $directory = storage_path('custom-emojis');

        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('Không thể tạo thư mục cache custom emoji.');
        }

        $targetPath = $directory . DIRECTORY_SEPARATOR . $identifier . '.preview';
        $temporaryPath = $targetPath . '.' . bin2hex(random_bytes(5)) . '.part';

        try {
            $download = $this->telegram->downloadCustomEmojiPreview($account, $identifier, $temporaryPath);

            if (!rename($temporaryPath, $targetPath)) {
                throw new RuntimeException('Không thể lưu cache custom emoji.');
            }

            return [
                'path' => $targetPath,
                'mime' => (string) ($download['mime'] ?? 'application/octet-stream'),
            ];
        } catch (Throwable) {
            if (is_file($temporaryPath)) {
                unlink($temporaryPath);
            }

            return null;
        }
    }

    private function cachedPreview(string $identifier): ?array
    {
        $path = storage_path('custom-emojis/' . $identifier . '.preview');

        if (!is_file($path) || filesize($path) === 0) {
            return null;
        }

        $mime = function_exists('finfo_open')
            ? (string) (new \finfo(FILEINFO_MIME_TYPE))->file($path)
            : 'application/octet-stream';

        return [
            'path' => $path,
            'mime' => $mime !== '' ? $mime : 'application/octet-stream',
        ];
    }
}
