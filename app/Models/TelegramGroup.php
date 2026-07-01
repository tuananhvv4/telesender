<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class TelegramGroup extends Model
{
    protected string $table = 'telegram_groups';
    protected array $fillable = [
        'user_id',
        'telegram_account_id',
        'title',
        'peer_identifier',
        'notes',
        'is_active',
        'created_at',
        'updated_at',
    ];

    public function listForUser(int $userId): array
    {
        return $this->db()->fetchAll(
            'SELECT tg.*, ta.name AS account_name
             FROM telegram_groups tg
             INNER JOIN telegram_accounts ta ON ta.id = tg.telegram_account_id
             WHERE tg.user_id = :user_id
             ORDER BY tg.id DESC',
            ['user_id' => $userId]
        );
    }
}
