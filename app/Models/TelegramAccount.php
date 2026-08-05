<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class TelegramAccount extends Model
{
    protected string $table = 'telegram_accounts';
    protected array $fillable = [
        'user_id',
        'name',
        'phone_number',
        'session_name',
        'session_status',
        'is_active',
        'tg_user_id',
        'tg_username',
        'last_connected_at',
        'last_sent_at',
        'cooldown_until',
        'cooldown_reason',
        'meta_json',
        'created_at',
        'updated_at',
    ];

    public function listForUser(int $userId): array
    {
        return $this->db()->fetchAll(
            'SELECT *,
                (SELECT COUNT(*) FROM telegram_groups WHERE telegram_account_id = telegram_accounts.id) AS groups_count,
                (SELECT COUNT(*) FROM schedule_jobs WHERE telegram_account_id = telegram_accounts.id) AS schedules_count
             FROM telegram_accounts
             WHERE user_id = :user_id
             ORDER BY id DESC',
            ['user_id' => $userId]
        );
    }

    public function paginateForUser(int $userId, int $page = 1, int $perPage = 20): array
    {
        return $this->paginateQuery(
            'SELECT COUNT(*) AS aggregate
             FROM telegram_accounts
             WHERE user_id = :user_id',
            'SELECT *,
                (SELECT COUNT(*) FROM telegram_groups WHERE telegram_account_id = telegram_accounts.id) AS groups_count,
                (SELECT COUNT(*) FROM schedule_jobs WHERE telegram_account_id = telegram_accounts.id) AS schedules_count
             FROM telegram_accounts
             WHERE user_id = :user_id
             ORDER BY id DESC',
            ['user_id' => $userId],
            $page,
            $perPage
        );
    }

    public function firstConnectedForUser(int $userId): ?array
    {
        return $this->db()->fetch(
            'SELECT *
             FROM telegram_accounts
             WHERE user_id = :user_id
               AND session_status = :session_status
             ORDER BY is_active DESC, last_connected_at DESC, id DESC
             LIMIT 1',
            [
                'user_id' => $userId,
                'session_status' => 'active',
            ]
        );
    }
}
