<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

class NotificationService
{
    public function __construct(private readonly Database $db)
    {
    }

    public function notifyTelegramRestriction(
        array $account,
        string $code,
        string $message,
        string $retryAt,
        ?int $dispatchLogId = null
    ): void {
        $accountId = (int) ($account['telegram_account_id'] ?? $account['id']);
        $accountName = (string) ($account['account_name'] ?? $account['name'] ?? ('Account #' . $accountId));
        $ownerId = (int) $account['user_id'];
        $recipients = [$ownerId];
        $superAdmins = $this->db->fetchAll(
            'SELECT id FROM users WHERE role = :role AND status = :status',
            ['role' => 'super_admin', 'status' => 'active']
        );
        foreach ($superAdmins as $superAdmin) {
            $recipients[] = (int) $superAdmin['id'];
        }
        $recipients = array_values(array_unique($recipients));

        $bucket = substr($retryAt !== '' ? $retryAt : gmdate('Y-m-d H:i'), 0, 16);
        $severity = $code === 'telegram_peer_flood' ? 'critical' : 'warning';
        $title = $code === 'telegram_peer_flood'
            ? 'Telegram cảnh báo PEER_FLOOD'
            : 'Telegram đang giới hạn account';

        foreach ($recipients as $recipientId) {
            $dedupeKey = hash('sha256', implode('|', [$accountId, $code, $bucket]));
            $this->db->query(
                'INSERT IGNORE INTO user_notifications
                    (user_id, type, title, message, severity, telegram_account_id, dispatch_log_id, dedupe_key, metadata_json, read_at, created_at)
                 VALUES
                    (:user_id, :type, :title, :message, :severity, :account_id, :dispatch_log_id, :dedupe_key, :metadata_json, NULL, UTC_TIMESTAMP())',
                [
                    'user_id' => $recipientId,
                    'type' => $code,
                    'title' => $title . ': ' . $accountName,
                    'message' => $message,
                    'severity' => $severity,
                    'account_id' => $accountId,
                    'dispatch_log_id' => $dispatchLogId,
                    'dedupe_key' => $dedupeKey,
                    'metadata_json' => json_encode([
                        'retry_at' => $retryAt,
                        'safety_mode' => (string) ($account['safety_mode'] ?? AccountSafetyPolicyService::MODE_SAFE),
                    ], JSON_UNESCAPED_UNICODE),
                ]
            );
        }
    }

    public function cleanupExpired(int $days = 30): void
    {
        $this->db->query(
            'DELETE FROM user_notifications WHERE created_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL ' . max(1, $days) . ' DAY)'
        );
    }
}
