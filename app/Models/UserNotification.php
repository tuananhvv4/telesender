<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class UserNotification extends Model
{
    protected string $table = 'user_notifications';
    protected array $fillable = [
        'user_id',
        'type',
        'title',
        'message',
        'severity',
        'telegram_account_id',
        'dispatch_log_id',
        'dedupe_key',
        'metadata_json',
        'read_at',
        'created_at',
    ];

    public function paginateForUser(int $userId, int $page = 1, int $perPage = 20): array
    {
        return $this->paginateQuery(
            'SELECT COUNT(*) AS aggregate FROM user_notifications WHERE user_id = :user_id',
            'SELECT notifications.*, ta.name AS account_name
             FROM user_notifications notifications
             LEFT JOIN telegram_accounts ta ON ta.id = notifications.telegram_account_id
             WHERE notifications.user_id = :user_id
             ORDER BY notifications.id DESC',
            ['user_id' => $userId],
            $page,
            $perPage
        );
    }

    public function unreadCount(int $userId): int
    {
        return $this->count('user_id = :user_id AND read_at IS NULL', ['user_id' => $userId]);
    }
}
