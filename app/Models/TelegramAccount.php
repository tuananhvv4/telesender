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
        'dispatch_locked_until',
        'operation_lock_type',
        'operation_lock_token',
        'safety_mode',
        'safety_mode_changed_at',
        'safety_mode_changed_by',
        'risk_acknowledged_at',
        'risk_acknowledged_by',
        'circuit_breaker_until',
        'circuit_breaker_reason',
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

    public function paginateForSafetyAdmin(int $page = 1, int $perPage = 20, string $query = ''): array
    {
        $bindings = [];
        $searchSql = '';
        $query = trim($query);
        if ($query !== '') {
            $bindings['search'] = '%' . $query . '%';
            $searchSql = ' WHERE ta.name LIKE :search OR u.name LIKE :search OR u.email LIKE :search';
        }

        return $this->paginateQuery(
            'SELECT COUNT(*) AS aggregate FROM telegram_accounts ta INNER JOIN users u ON u.id = ta.user_id' . $searchSql,
            'SELECT ta.*, u.name AS owner_name, u.email AS owner_email, u.can_override_safety_limits,
                    (SELECT COUNT(*) FROM schedule_jobs sj WHERE sj.telegram_account_id = ta.id AND sj.status = \'active\') AS schedules_count
             FROM telegram_accounts ta
             INNER JOIN users u ON u.id = ta.user_id' . $searchSql . '
             ORDER BY ta.safety_mode = \'risk_accepted\' DESC, ta.safety_mode = \'elevated\' DESC, ta.id DESC',
            $bindings,
            $page,
            $perPage
        );
    }
}
