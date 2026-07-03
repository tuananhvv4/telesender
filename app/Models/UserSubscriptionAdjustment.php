<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class UserSubscriptionAdjustment extends Model
{
    protected string $table = 'user_subscription_adjustments';
    protected array $fillable = [
        'target_user_id',
        'actor_user_id',
        'delta_days',
        'previous_expires_at',
        'new_expires_at',
        'note',
        'created_at',
        'updated_at',
    ];

    public function recentForTargetUser(int $userId, int $limit = 20): array
    {
        return $this->db()->fetchAll(
            'SELECT usa.*, actor.name AS actor_name
             FROM user_subscription_adjustments usa
             INNER JOIN users actor ON actor.id = usa.actor_user_id
             WHERE usa.target_user_id = :user_id
             ORDER BY usa.id DESC
             LIMIT ' . (int) $limit,
            ['user_id' => $userId]
        );
    }
}
