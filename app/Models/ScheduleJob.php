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

    public function listForUser(int $userId, array $filters = []): array
    {
        $bindings = ['user_id' => $userId];
        $whereSql = $this->filterSql($bindings, $filters);

        return $this->db()->fetchAll(
            'SELECT sj.*, ta.name AS account_name, ta.last_sent_at, ta.cooldown_until, ta.cooldown_reason,
                    tg.title AS group_title, tg.topic_id, tg.topic_title, mt.name AS template_name
             FROM schedule_jobs sj
             INNER JOIN telegram_accounts ta ON ta.id = sj.telegram_account_id
             INNER JOIN telegram_groups tg ON tg.id = sj.telegram_group_id
             INNER JOIN message_templates mt ON mt.id = sj.message_template_id
             ' . $whereSql . '
             ORDER BY sj.status = "active" DESC, sj.next_run_at ASC, sj.id DESC',
            $bindings
        );
    }

    public function paginateForUser(int $userId, int $page = 1, int $perPage = 15, array $filters = []): array
    {
        $bindings = ['user_id' => $userId];
        $whereSql = $this->filterSql($bindings, $filters);

        return $this->paginateQuery(
            'SELECT COUNT(*) AS aggregate
             FROM schedule_jobs sj
             INNER JOIN telegram_accounts ta ON ta.id = sj.telegram_account_id
             INNER JOIN telegram_groups tg ON tg.id = sj.telegram_group_id
             INNER JOIN message_templates mt ON mt.id = sj.message_template_id
             ' . $whereSql,
            'SELECT sj.*, ta.name AS account_name, ta.last_sent_at, ta.cooldown_until, ta.cooldown_reason,
                    tg.title AS group_title, tg.topic_id, tg.topic_title, mt.name AS template_name
             FROM schedule_jobs sj
             INNER JOIN telegram_accounts ta ON ta.id = sj.telegram_account_id
             INNER JOIN telegram_groups tg ON tg.id = sj.telegram_group_id
             INNER JOIN message_templates mt ON mt.id = sj.message_template_id
             ' . $whereSql . '
             ORDER BY sj.status = "active" DESC, sj.next_run_at ASC, sj.id DESC',
            $bindings,
            $page,
            $perPage
        );
    }

    private function filterSql(array &$bindings, array $filters): string
    {
        $whereSql = 'WHERE sj.user_id = :user_id';
        $searchQuery = trim((string) ($filters['query'] ?? ''));
        $accountId = (int) ($filters['telegram_account_id'] ?? 0);
        $templateId = (int) ($filters['message_template_id'] ?? 0);
        $status = trim((string) ($filters['status'] ?? ''));

        if ($accountId > 0) {
            $whereSql .= ' AND sj.telegram_account_id = :telegram_account_id';
            $bindings['telegram_account_id'] = $accountId;
        }

        if ($templateId > 0) {
            $whereSql .= ' AND sj.message_template_id = :message_template_id';
            $bindings['message_template_id'] = $templateId;
        }

        if (in_array($status, ['active', 'paused'], true)) {
            $whereSql .= ' AND sj.status = :status';
            $bindings['status'] = $status;
        }

        if ($searchQuery !== '') {
            $whereSql .= ' AND (
                mt.name LIKE :search
                OR ta.name LIKE :search
                OR tg.title LIKE :search
                OR tg.topic_title LIKE :search
                OR sj.schedule_type LIKE :search
                OR sj.timezone LIKE :search
                OR sj.cron_expression LIKE :search
                OR sj.last_error LIKE :search
            )';
            $bindings['search'] = '%' . $searchQuery . '%';
        }

        return $whereSql;
    }
}
