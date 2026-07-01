<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class DispatchLog extends Model
{
    protected string $table = 'dispatch_logs';
    protected array $fillable = [
        'user_id',
        'schedule_job_id',
        'telegram_account_id',
        'telegram_group_id',
        'message_template_id',
        'label_id',
        'request_id',
        'status',
        'message_preview',
        'response_payload',
        'error_message',
        'sent_at',
        'created_at',
    ];

    public function recentForUser(int $userId, int $limit = 50): array
    {
        return $this->db()->fetchAll(
            "SELECT dl.*, ta.name AS account_name, tg.title AS group_title, mt.name AS template_name, ml.name AS label_name
             FROM dispatch_logs dl
             LEFT JOIN telegram_accounts ta ON ta.id = dl.telegram_account_id
             LEFT JOIN telegram_groups tg ON tg.id = dl.telegram_group_id
             LEFT JOIN message_templates mt ON mt.id = dl.message_template_id
             LEFT JOIN message_labels ml ON ml.id = dl.label_id
             WHERE dl.user_id = :user_id
             ORDER BY dl.id DESC
             LIMIT {$limit}",
            ['user_id' => $userId]
        );
    }
}
