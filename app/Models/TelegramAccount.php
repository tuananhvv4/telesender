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
}
