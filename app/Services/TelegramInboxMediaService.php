<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;

class TelegramInboxMediaService
{
    public function __construct(
        private readonly TelegramInboxService $inbox,
        private readonly TelegramService $telegram,
        private readonly TelegramAccountLockService $locks
    ) {
    }

    public function stream(int $messageId): never
    {
        $message = $this->inbox->mediaMessageOrFail($messageId);
        $source = json_decode((string) $message['media_source_json'], true);
        $fileId = trim((string) ($source['file_id'] ?? ''));
        if ($fileId === '') {
            throw new HttpException(404, 'Media không có nguồn tải hợp lệ.');
        }

        $size = isset($message['media_size']) ? (int) $message['media_size'] : 0;
        $fileName = trim((string) ($message['media_file_name'] ?? ''));
        $mimeType = trim((string) ($message['media_mime_type'] ?? ''));
        if ($size <= 0 || $mimeType === '') {
            throw new HttpException(422, 'Telegram chưa cung cấp đủ metadata để tải media này.');
        }
        if ($fileName === '') {
            $fileName = 'telegram-media' . $this->extensionForMime($mimeType);
        }

        $accountId = (int) $message['telegram_account_id'];
        $token = $this->locks->acquireMedia(
            $accountId,
            max(30, (int) config('inbox.media_lock_seconds', 120)),
            max(1, (int) config('inbox.media_dispatch_lookahead_seconds', 300))
        );
        if ($token === null) {
            throw new HttpException(423, 'Telegram account đang ưu tiên gửi tin. Hãy thử tải media lại sau.');
        }

        $release = function () use ($accountId, $token): void {
            $this->locks->release($accountId, $token);
        };
        register_shutdown_function($release);

        try {
            $this->telegram->downloadInboxMedia($message, $fileId, $size, $fileName, $mimeType);
        } finally {
            $release();
        }

        exit;
    }

    private function extensionForMime(string $mimeType): string
    {
        return match (strtolower($mimeType)) {
            'image/jpeg' => '.jpg',
            'image/png' => '.png',
            'image/webp' => '.webp',
            'video/mp4' => '.mp4',
            'audio/mpeg' => '.mp3',
            'audio/ogg' => '.ogg',
            default => '',
        };
    }
}
