<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\CronExpression;
use App\Core\Database;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

class SchedulerService
{
    public function __construct(
        private readonly Database $db,
        private readonly TelegramService $telegram,
        private readonly CronExpression $cron
    ) {
    }

    public function calculateNextRun(string $expression, string $timezone, ?DateTimeImmutable $from = null): string
    {
        $from ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $nextLocal = $this->cron->nextRun($expression, $from, $timezone);
        return $nextLocal->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    public function dispatchDueJobs(): array
    {
        $jobs = $this->db->fetchAll(
            'SELECT sj.*, ta.name AS account_name, ta.phone_number, ta.session_name, ta.session_status,
                    tg.title AS group_title, tg.peer_identifier,
                    mt.name AS template_name, mt.body, mt.parse_mode, mt.label_id
             FROM schedule_jobs sj
             INNER JOIN telegram_accounts ta ON ta.id = sj.telegram_account_id
             INNER JOIN telegram_groups tg ON tg.id = sj.telegram_group_id
             INNER JOIN message_templates mt ON mt.id = sj.message_template_id
             WHERE sj.status = :status
               AND tg.is_active = 1
               AND mt.is_active = 1
               AND sj.next_run_at IS NOT NULL
               AND sj.next_run_at <= UTC_TIMESTAMP()
               AND (sj.dispatch_locked_until IS NULL OR sj.dispatch_locked_until < UTC_TIMESTAMP())
             ORDER BY sj.next_run_at ASC
             LIMIT 50',
            ['status' => 'active']
        );

        $results = [];

        foreach ($jobs as $job) {
            if (!$this->lockJob((int) $job['id'])) {
                continue;
            }

            $results[] = $this->dispatchOne($job);
        }

        return $results;
    }

    private function lockJob(int $jobId): bool
    {
        $statement = $this->db->query(
            'UPDATE schedule_jobs
             SET dispatch_locked_until = DATE_ADD(UTC_TIMESTAMP(), INTERVAL 5 MINUTE)
             WHERE id = :id
               AND (dispatch_locked_until IS NULL OR dispatch_locked_until < UTC_TIMESTAMP())',
            ['id' => $jobId]
        );

        return $statement->rowCount() === 1;
    }

    private function dispatchOne(array $job): array
    {
        $scheduledAt = new DateTimeImmutable((string) $job['next_run_at'], new DateTimeZone('UTC'));
        $requestId = 'dispatch_' . bin2hex(random_bytes(6));
        $status = 'success';
        $payload = null;
        $error = null;

        try {
            if ($job['session_status'] !== 'active') {
                throw new RuntimeException('Telegram account chưa ở trạng thái active.');
            }

            $payload = $this->telegram->sendMessage(
                $job,
                (string) $job['peer_identifier'],
                (string) $job['body'],
                (string) $job['parse_mode']
            );
        } catch (\Throwable $exception) {
            $status = 'error';
            $error = $exception->getMessage();
        }

        $nextRunAt = $this->calculateNextRun(
            (string) $job['cron_expression'],
            (string) $job['timezone'],
            $scheduledAt
        );

        $this->db->transaction(function (Database $db) use ($job, $requestId, $status, $payload, $error, $nextRunAt): void {
            $db->insert('dispatch_logs', [
                'user_id' => (int) $job['user_id'],
                'schedule_job_id' => (int) $job['id'],
                'telegram_account_id' => (int) $job['telegram_account_id'],
                'telegram_group_id' => (int) $job['telegram_group_id'],
                'message_template_id' => (int) $job['message_template_id'],
                'label_id' => $job['label_id'] ? (int) $job['label_id'] : null,
                'request_id' => $requestId,
                'status' => $status,
                'message_preview' => mb_substr((string) $job['body'], 0, 500),
                'response_payload' => $payload ? json_encode($payload, JSON_UNESCAPED_UNICODE) : null,
                'error_message' => $error,
                'sent_at' => gmdate('Y-m-d H:i:s'),
                'created_at' => gmdate('Y-m-d H:i:s'),
            ]);

            $db->update('schedule_jobs', [
                'next_run_at' => $nextRunAt,
                'last_run_at' => gmdate('Y-m-d H:i:s'),
                'last_error' => $error,
                'dispatch_locked_until' => null,
                'updated_at' => gmdate('Y-m-d H:i:s'),
            ], 'id = :id', ['id' => (int) $job['id']]);
        });

        return [
            'schedule_id' => (int) $job['id'],
            'group' => $job['group_title'],
            'account' => $job['account_name'],
            'status' => $status,
            'next_run_at' => $nextRunAt,
            'error' => $error,
        ];
    }
}
