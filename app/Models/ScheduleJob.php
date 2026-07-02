<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class ScheduleJob extends Model
{
    protected string $table = 'schedule_jobs';
    protected array $fillable = [
        'user_id',
        'telegram_account_id',
        'telegram_group_id',
        'message_template_id',
        'timezone',
        'cron_expression',
        'schedule_type',
        'schedule_config_json',
        'next_run_at',
        'last_run_at',
        'last_error',
        'status',
        'dispatch_locked_until',
        'created_at',
        'updated_at',
    ];

    public function listForUser(int $userId): array
    {
        return $this->db()->fetchAll(
            'SELECT sj.*, ta.name AS account_name, ta.last_sent_at, ta.cooldown_until, ta.cooldown_reason,
                    tg.title AS group_title, tg.topic_id, tg.topic_title, mt.name AS template_name
             FROM schedule_jobs sj
             INNER JOIN telegram_accounts ta ON ta.id = sj.telegram_account_id
             INNER JOIN telegram_groups tg ON tg.id = sj.telegram_group_id
             INNER JOIN message_templates mt ON mt.id = sj.message_template_id
             WHERE sj.user_id = :user_id
             ORDER BY sj.status = "active" DESC, sj.next_run_at ASC, sj.id DESC',
            ['user_id' => $userId]
        );
    }

    public function paginateForUser(int $userId, int $page = 1, int $perPage = 15): array
    {
        return $this->paginateQuery(
            'SELECT COUNT(*) AS aggregate
             FROM schedule_jobs
             WHERE user_id = :user_id',
            'SELECT sj.*, ta.name AS account_name, ta.last_sent_at, ta.cooldown_until, ta.cooldown_reason,
                    tg.title AS group_title, tg.topic_id, tg.topic_title, mt.name AS template_name
             FROM schedule_jobs sj
             INNER JOIN telegram_accounts ta ON ta.id = sj.telegram_account_id
             INNER JOIN telegram_groups tg ON tg.id = sj.telegram_group_id
             INNER JOIN message_templates mt ON mt.id = sj.message_template_id
             WHERE sj.user_id = :user_id
             ORDER BY sj.status = "active" DESC, sj.next_run_at ASC, sj.id DESC',
            ['user_id' => $userId],
            $page,
            $perPage
        );
    }
}
