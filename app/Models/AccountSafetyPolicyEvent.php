<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class AccountSafetyPolicyEvent extends Model
{
    protected string $table = 'account_safety_policy_events';
    protected array $fillable = [
        'user_id',
        'telegram_account_id',
        'actor_user_id',
        'event_type',
        'previous_mode',
        'new_mode',
        'reason',
        'metadata_json',
        'created_at',
    ];

    public function recentForUser(int $userId, int $limit = 50): array
    {
        return $this->db()->fetchAll(
            'SELECT events.*, ta.name AS account_name, actor.name AS actor_name
             FROM account_safety_policy_events events
             INNER JOIN telegram_accounts ta ON ta.id = events.telegram_account_id
             LEFT JOIN users actor ON actor.id = events.actor_user_id
             WHERE events.user_id = :user_id
             ORDER BY events.id DESC
             LIMIT ' . max(1, $limit),
            ['user_id' => $userId]
        );
    }
}
