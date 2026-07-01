<?php

declare(strict_types=1);

namespace App\Services;

use danog\MadelineProto\ParseMode;
use RuntimeException;
use danog\MadelineProto\Settings\AppInfo;

class TelegramService
{
    public function startLogin(array $account): array
    {
        $api = $this->client($account);
        $api->phoneLogin($account['phone_number']);

        return [
            'status' => 'code_sent',
            'message' => 'Telegram đã gửi mã OTP tới tài khoản này.',
        ];
    }

    public function completeCode(array $account, string $code): array
    {
        $api = $this->client($account);
        $result = $api->completePhoneLogin($code);

        if (($result['_'] ?? null) === 'account.password') {
            return [
                'status' => 'password_required',
                'message' => 'Tài khoản yêu cầu mật khẩu 2FA.',
            ];
        }

        return [
            'status' => 'active',
            'message' => 'Đăng nhập Telegram thành công.',
            'profile' => $api->getSelf(),
        ];
    }

    public function completePassword(array $account, string $password): array
    {
        $api = $this->client($account);
        $api->complete2FALogin($password);

        return [
            'status' => 'active',
            'message' => 'Xác thực 2FA thành công.',
            'profile' => $api->getSelf(),
        ];
    }

    public function sendMessage(
        array $account,
        string $peer,
        string $message,
        string $parseMode = 'HTML',
        ?int $topicId = null
    ): array
    {
        $api = $this->client($account);
        $api->start();

        $replyToTopicId = $topicId !== null && $topicId > 1 ? $topicId : null;

        $result = $api->sendMessage(
            peer: $peer,
            message: $message,
            parseMode: $this->parseMode($parseMode),
            replyToMsgId: $replyToTopicId,
            topMsgId: $replyToTopicId
        );

        return is_array($result) ? $result : ['result' => $result];
    }

    public function getForumTopics(array $account, string $peer): array
    {
        $api = $this->client($account);
        $api->start();

        $result = $api->messages->getForumTopics(
            peer: $peer,
            offset_date: 0,
            offset_id: 0,
            offset_topic: 0,
            limit: 100
        );

        $topics = [];
        foreach ((array) ($result['topics'] ?? []) as $topic) {
            $internalId = isset($topic['id']) ? (int) $topic['id'] : null;
            if ($internalId === 1) {
                continue;
            }

            $topicId = $internalId;

            if ($topicId === null) {
                continue;
            }

            $topics[] = [
                'id' => $topicId,
                'internal_id' => $internalId,
                'top_message' => isset($topic['top_message']) ? (int) $topic['top_message'] : null,
                'title' => (string) ($topic['title'] ?? ('Topic #' . $topicId)),
            ];
        }

        return $topics;
    }

    public function getSessionFile(array $account): string
    {
        return storage_path('telegram/' . $account['session_name'] . '.madeline');
    }

    private function client(array $account): object
    {
        $this->bootstrapMadeline();

        $apiId = config('services.telegram.api_id');
        $apiHash = config('services.telegram.api_hash');

        if (empty($apiId) || empty($apiHash)) {
            throw new RuntimeException('Thiếu TELEGRAM_API_ID hoặc TELEGRAM_API_HASH trong file .env.');
        }

        $sessionFile = $this->getSessionFile($account);
        $settings = (new AppInfo())
            ->setApiId((int) $apiId)
            ->setApiHash((string) $apiHash);

        return new \danog\MadelineProto\API($sessionFile, $settings);
    }

    private function bootstrapMadeline(): void
    {
        $autoload = base_path('vendor/autoload.php');

        if (is_file($autoload)) {
            require_once $autoload;
        }

        if (!class_exists(\danog\MadelineProto\API::class)) {
            throw new RuntimeException('Chưa cài dependency Telegram. Hãy chạy `composer install` trước.');
        }
    }

    private function parseMode(string $parseMode): ParseMode
    {
        return match (strtoupper($parseMode)) {
            'HTML' => ParseMode::HTML,
            'MARKDOWN' => ParseMode::MARKDOWN,
            default => ParseMode::TEXT,
        };
    }
}
