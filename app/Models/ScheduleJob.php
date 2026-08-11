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
        'occurrence_due_at',
        'last_run_at',
        'last_error',
        'queue_reason_code',
        'status',
        'dispatch_locked_until',
        'created_at',
        'updated_at',
    ];

    public function listForUser(int $userId, array $filters = []): array
    {
        $bindings = ['user_id' => $userId];
        $whereSql = $this->filterSql($bindings, $filters);

        $items = $this->db()->fetchAll(
            'SELECT sj.*, ta.name AS account_name, ta.last_sent_at, ta.cooldown_until, ta.cooldown_reason,
                    ta.safety_mode, ta.circuit_breaker_until, ta.circuit_breaker_reason,
                    tg.title AS group_title, tg.topic_id, tg.topic_title, mt.name AS template_name
             FROM schedule_jobs sj
             INNER JOIN telegram_accounts ta ON ta.id = sj.telegram_account_id
             INNER JOIN telegram_groups tg ON tg.id = sj.telegram_group_id
             INNER JOIN message_templates mt ON mt.id = sj.message_template_id
             ' . $whereSql . '
             ORDER BY sj.status = "active" DESC, sj.next_run_at ASC, sj.id DESC',
            $bindings
        );

        return $this->attachTargetGroups($items);
    }

    public function paginateForUser(int $userId, int $page = 1, int $perPage = 15, array $filters = []): array
    {
        $bindings = ['user_id' => $userId];
        $whereSql = $this->filterSql($bindings, $filters);

        $result = $this->paginateQuery(
            'SELECT COUNT(*) AS aggregate
             FROM schedule_jobs sj
             INNER JOIN telegram_accounts ta ON ta.id = sj.telegram_account_id
             INNER JOIN telegram_groups tg ON tg.id = sj.telegram_group_id
             INNER JOIN message_templates mt ON mt.id = sj.message_template_id
             ' . $whereSql,
            'SELECT sj.*, ta.name AS account_name, ta.last_sent_at, ta.cooldown_until, ta.cooldown_reason,
                    ta.safety_mode, ta.circuit_breaker_until, ta.circuit_breaker_reason,
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

        $result['items'] = $this->attachTargetGroups($result['items']);
        return $result;
    }

    public function syncGroups(int $scheduleId, array $groupIds): void
    {
        $groupIds = array_values(array_unique(array_map('intval', $groupIds)));
        $this->db()->execute(
            'DELETE FROM schedule_job_groups WHERE schedule_job_id = :schedule_job_id',
            ['schedule_job_id' => $scheduleId]
        );

        $createdAt = gmdate('Y-m-d H:i:s');
        foreach ($groupIds as $sortOrder => $groupId) {
            $this->db()->insert('schedule_job_groups', [
                'schedule_job_id' => $scheduleId,
                'telegram_group_id' => $groupId,
                'sort_order' => $sortOrder,
                'created_at' => $createdAt,
            ]);
        }
    }

    public function reassignPrimaryGroupBeforeDelete(int $groupId): void
    {
        $this->db()->execute(
            'UPDATE schedule_jobs sj
             INNER JOIN (
                 SELECT schedule_job_id, MIN(telegram_group_id) AS replacement_group_id
                 FROM schedule_job_groups
                 WHERE telegram_group_id <> :excluded_group_id
                 GROUP BY schedule_job_id
             ) replacements ON replacements.schedule_job_id = sj.id
             SET sj.telegram_group_id = replacements.replacement_group_id
             WHERE sj.telegram_group_id = :group_id',
            [
                'excluded_group_id' => $groupId,
                'group_id' => $groupId,
            ]
        );
    }

    public function hasSchedulesForGroup(int $groupId): bool
    {
        return $this->db()->fetch(
            'SELECT 1
             FROM schedule_job_groups
             WHERE telegram_group_id = :telegram_group_id
             LIMIT 1',
            ['telegram_group_id' => $groupId]
        ) !== null;
    }

    public function hasLockedSchedulesForGroup(int $groupId): bool
    {
        return $this->db()->fetch(
            'SELECT 1
             FROM schedule_job_groups sjg
             INNER JOIN schedule_jobs sj ON sj.id = sjg.schedule_job_id
             WHERE sjg.telegram_group_id = :telegram_group_id
               AND sj.dispatch_locked_until >= UTC_TIMESTAMP()
             LIMIT 1',
            ['telegram_group_id' => $groupId]
        ) !== null;
    }

    public function pauseSchedulesWithoutActiveGroupsForGroup(int $groupId): void
    {
        $this->db()->execute(
            'UPDATE schedule_jobs sj
             INNER JOIN schedule_job_groups affected ON affected.schedule_job_id = sj.id
             SET sj.status = \'paused\',
                 sj.last_error = \'Lịch đã tự tạm dừng vì không còn nhóm Telegram đang hoạt động.\',
                 sj.dispatch_locked_until = NULL,
                 sj.updated_at = UTC_TIMESTAMP()
             WHERE affected.telegram_group_id = :telegram_group_id
               AND NOT EXISTS (
                   SELECT 1
                   FROM schedule_job_groups active_sjg
                   INNER JOIN telegram_groups active_tg ON active_tg.id = active_sjg.telegram_group_id
                   WHERE active_sjg.schedule_job_id = sj.id
                     AND active_tg.is_active = 1
               )',
            ['telegram_group_id' => $groupId]
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
                OR EXISTS (
                    SELECT 1
                    FROM schedule_job_groups search_sjg
                    INNER JOIN telegram_groups search_tg ON search_tg.id = search_sjg.telegram_group_id
                    WHERE search_sjg.schedule_job_id = sj.id
                      AND (search_tg.title LIKE :search OR search_tg.topic_title LIKE :search)
                )
                OR sj.schedule_type LIKE :search
                OR sj.timezone LIKE :search
                OR sj.cron_expression LIKE :search
                OR sj.last_error LIKE :search
            )';
            $bindings['search'] = '%' . $searchQuery . '%';
        }

        return $whereSql;
    }

    private function attachTargetGroups(array $schedules): array
    {
        if ($schedules === []) {
            return [];
        }

        $scheduleIds = array_values(array_unique(array_map(
            static fn (array $schedule): int => (int) $schedule['id'],
            $schedules
        )));
        $placeholders = [];
        $bindings = [];

        foreach ($scheduleIds as $index => $scheduleId) {
            $key = 'schedule_' . $index;
            $placeholders[] = ':' . $key;
            $bindings[$key] = $scheduleId;
        }

        $rows = $this->db()->fetchAll(
            'SELECT sjg.schedule_job_id, tg.id, tg.title, tg.topic_id, tg.topic_title, tg.is_active
             FROM schedule_job_groups sjg
             INNER JOIN telegram_groups tg ON tg.id = sjg.telegram_group_id
             WHERE sjg.schedule_job_id IN (' . implode(', ', $placeholders) . ')
             ORDER BY sjg.schedule_job_id ASC, sjg.sort_order ASC, sjg.telegram_group_id ASC',
            $bindings
        );
        $groupsBySchedule = [];
        $linkedScheduleIds = [];

        foreach ($rows as $row) {
            $scheduleId = (int) $row['schedule_job_id'];
            $linkedScheduleIds[$scheduleId] = true;

            if (!(bool) $row['is_active']) {
                continue;
            }

            $groupsBySchedule[$scheduleId][] = [
                'id' => (int) $row['id'],
                'title' => (string) $row['title'],
                'topic_id' => $row['topic_id'] !== null ? (int) $row['topic_id'] : null,
                'topic_title' => (string) ($row['topic_title'] ?? ''),
                'is_active' => (int) $row['is_active'],
            ];
        }

        foreach ($schedules as &$schedule) {
            $scheduleId = (int) $schedule['id'];
            $targetGroups = isset($linkedScheduleIds[$scheduleId])
                ? ($groupsBySchedule[$scheduleId] ?? [])
                : [[
                    'id' => (int) $schedule['telegram_group_id'],
                    'title' => (string) $schedule['group_title'],
                    'topic_id' => $schedule['topic_id'] !== null ? (int) $schedule['topic_id'] : null,
                    'topic_title' => (string) ($schedule['topic_title'] ?? ''),
                    'is_active' => 1,
                ]];
            $schedule['target_groups'] = $targetGroups;
            $schedule['telegram_group_ids'] = array_column($targetGroups, 'id');
            $schedule['group_count'] = count($targetGroups);
        }
        unset($schedule);

        return $schedules;
    }
}
