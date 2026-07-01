<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\CronExpression;
use App\Core\Database;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;
use Throwable;

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
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $jobs = $this->db->fetchAll(
            'SELECT sj.*, ta.name AS account_name, ta.phone_number, ta.session_name, ta.session_status,
                    ta.last_sent_at, ta.cooldown_until, ta.cooldown_reason,
                    tg.title AS group_title, tg.peer_identifier, tg.topic_id, tg.topic_title,
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
        $jobsByAccount = [];

        foreach ($jobs as $job) {
            $jobsByAccount[(int) $job['telegram_account_id']][] = $job;
        }

        foreach ($jobsByAccount as $accountJobs) {
            $accountJobs = $this->normalizeAccountQueue($accountJobs, $now);
            $job = $accountJobs[0] ?? null;

            if ($job === null || !$this->isDueNow((string) ($job['next_run_at'] ?? ''), $now)) {
                continue;
            }

            $results[] = $this->dispatchOne($job, $now);
        }

        return $results;
    }

    public function dispatchScheduleNow(int $scheduleId, int $userId): array
    {
        $job = $this->db->fetch(
            'SELECT sj.*, ta.name AS account_name, ta.phone_number, ta.session_name, ta.session_status,
                    ta.last_sent_at, ta.cooldown_until, ta.cooldown_reason,
                    tg.title AS group_title, tg.peer_identifier, tg.topic_id, tg.topic_title, tg.is_active AS group_active,
                    mt.name AS template_name, mt.body, mt.parse_mode, mt.label_id, mt.is_active AS template_active
             FROM schedule_jobs sj
             INNER JOIN telegram_accounts ta ON ta.id = sj.telegram_account_id
             INNER JOIN telegram_groups tg ON tg.id = sj.telegram_group_id
             INNER JOIN message_templates mt ON mt.id = sj.message_template_id
             WHERE sj.id = :id
               AND sj.user_id = :user_id
             LIMIT 1',
            [
                'id' => $scheduleId,
                'user_id' => $userId,
            ]
        );

        if ($job === null) {
            throw new RuntimeException('Không tìm thấy schedule cần gửi.');
        }

        if (!(bool) $job['group_active']) {
            throw new RuntimeException('Nhóm Telegram này đang tắt, chưa thể gửi ngay.');
        }

        if (!(bool) $job['template_active']) {
            throw new RuntimeException('Template này đang tắt, chưa thể gửi ngay.');
        }

        if (!$this->lockJob((int) $job['id'])) {
            return [
                'schedule_id' => (int) $job['id'],
                'group' => $job['group_title'],
                'account' => $job['account_name'],
                'status' => 'locked',
                'next_run_at' => (string) ($job['next_run_at'] ?? ''),
                'error' => 'Schedule đang được xử lý ở tiến trình khác. Hãy thử lại sau ít phút.',
            ];
        }

        return $this->dispatchOne($job, new DateTimeImmutable('now', new DateTimeZone('UTC')), true);
    }

    public function analyzeScheduleRisk(string $expression, string $timezone): array
    {
        $localNow = new DateTimeImmutable('now', new DateTimeZone($timezone));
        $cursor = $localNow;
        $occurrences = [];

        for ($i = 0; $i < 60; $i++) {
            $cursor = $this->cron->nextRun($expression, $cursor, $timezone);
            $occurrences[] = $cursor;

            if ($cursor >= $localNow->modify('+24 hours')) {
                break;
            }
        }

        $runsPerDay = count(array_filter(
            $occurrences,
            static fn (DateTimeImmutable $occurrence): bool => $occurrence <= $localNow->modify('+24 hours')
        ));

        $minGapMinutes = null;
        for ($i = 1, $length = count($occurrences); $i < $length; $i++) {
            $gap = (int) floor(($occurrences[$i]->getTimestamp() - $occurrences[$i - 1]->getTimestamp()) / 60);
            $minGapMinutes = $minGapMinutes === null ? $gap : min($minGapMinutes, $gap);
        }

        $blockRuns = (int) config('safety.schedule_limits.block_runs_per_day', 48);
        $highRuns = (int) config('safety.schedule_limits.high_runs_per_day', 24);
        $warnRuns = (int) config('safety.schedule_limits.warn_runs_per_day', 12);
        $highGap = (int) config('safety.schedule_limits.high_min_gap_minutes', 30);
        $warnGap = (int) config('safety.schedule_limits.warn_min_gap_minutes', 60);

        $risk = 'safe';
        $message = 'Mật độ lịch gửi đang ở mức an toàn.';

        if ($runsPerDay > $blockRuns || ($minGapMinutes !== null && $minGapMinutes < $highGap)) {
            $risk = 'blocked';
            $message = 'Lịch này quá dày, dễ chạm anti-spam. Hãy giãn cách thêm trước khi lưu.';
        } elseif ($runsPerDay > $highRuns) {
            $risk = 'high';
            $message = 'Lịch gửi khá dày. Hệ thống sẽ tự giới hạn theo account để giảm rủi ro.';
        } elseif ($runsPerDay > $warnRuns || ($minGapMinutes !== null && $minGapMinutes < $warnGap)) {
            $risk = 'medium';
            $message = 'Lịch này tương đối dày. Nên dùng account phụ và tránh lặp nội dung quá giống nhau.';
        }

        return [
            'risk' => $risk,
            'runs_per_day' => $runsPerDay,
            'min_gap_minutes' => $minGapMinutes,
            'message' => $message,
        ];
    }

    public function analyzeAccountScheduleRisk(array $schedules): array
    {
        $activeSchedules = array_values(array_filter(
            $schedules,
            static fn (array $schedule): bool => (string) ($schedule['status'] ?? 'active') === 'active'
        ));

        $pausedCount = count($schedules) - count($activeSchedules);
        $minGapLimit = (int) config('safety.account_limits.min_minutes_between_sends', 8);
        $hourlyLimit = (int) config('safety.account_limits.max_success_per_hour', 6);
        $dailyLimit = (int) config('safety.account_limits.max_success_per_day', 30);

        if ($activeSchedules === []) {
            return [
                'risk' => 'safe',
                'message' => 'Chưa có schedule active nào trên account này.',
                'active_schedule_count' => 0,
                'paused_schedule_count' => $pausedCount,
                'runs_per_day' => 0,
                'min_gap_minutes' => null,
                'max_runs_per_hour' => 0,
                'conflict_pairs' => 0,
                'same_minute_pairs' => 0,
                'queue_likely' => false,
                'next_occurrences' => [],
            ];
        }

        $occurrences = $this->collectAccountOccurrences($activeSchedules);
        $runsPerDay = count($occurrences);
        $minGapMinutes = null;
        $conflictPairs = 0;
        $sameMinutePairs = 0;

        for ($i = 1, $length = count($occurrences); $i < $length; $i++) {
            $gap = (int) floor(($occurrences[$i]['utc']->getTimestamp() - $occurrences[$i - 1]['utc']->getTimestamp()) / 60);
            $minGapMinutes = $minGapMinutes === null ? $gap : min($minGapMinutes, $gap);

            if ($gap < $minGapLimit) {
                $conflictPairs++;
            }

            if ($gap === 0) {
                $sameMinutePairs++;
            }
        }

        $maxRunsPerHour = 0;
        $windowStart = 0;
        $countOccurrences = count($occurrences);

        for ($windowEnd = 0; $windowEnd < $countOccurrences; $windowEnd++) {
            while (
                $windowStart < $windowEnd &&
                ($occurrences[$windowEnd]['utc']->getTimestamp() - $occurrences[$windowStart]['utc']->getTimestamp()) >= 3600
            ) {
                $windowStart++;
            }

            $maxRunsPerHour = max($maxRunsPerHour, $windowEnd - $windowStart + 1);
        }

        $queueLikely = ($minGapMinutes !== null && $minGapMinutes < $minGapLimit)
            || $maxRunsPerHour > $hourlyLimit
            || $runsPerDay > $dailyLimit;

        $risk = 'safe';
        $message = 'Tổng lịch của account này đang nằm trong giới hạn an toàn.';

        if ($sameMinutePairs > 0 || $maxRunsPerHour > $hourlyLimit || $runsPerDay > $dailyLimit) {
            $risk = 'high';
            $message = 'Có dấu hiệu quá tải theo account. Một số lịch có thể bị dời bởi hàng đợi hoặc guard an toàn.';
        } elseif ($conflictPairs > 0) {
            $risk = 'medium';
            $message = 'Một vài mốc giờ đang quá sát nhau trên cùng account. App sẽ phải xếp hàng để giữ khoảng cách an toàn.';
        }

        return [
            'risk' => $risk,
            'message' => $message,
            'active_schedule_count' => count($activeSchedules),
            'paused_schedule_count' => $pausedCount,
            'runs_per_day' => $runsPerDay,
            'min_gap_minutes' => $minGapMinutes,
            'max_runs_per_hour' => $maxRunsPerHour,
            'conflict_pairs' => $conflictPairs,
            'same_minute_pairs' => $sameMinutePairs,
            'queue_likely' => $queueLikely,
            'next_occurrences' => array_map(
                static fn (array $occurrence): array => [
                    'label' => $occurrence['local']->format('d/m H:i'),
                    'group_title' => (string) ($occurrence['group_title'] ?? ''),
                    'template_name' => (string) ($occurrence['template_name'] ?? ''),
                    'timezone' => (string) ($occurrence['timezone'] ?? ''),
                ],
                array_slice($occurrences, 0, 6)
            ),
        ];
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

    private function collectAccountOccurrences(array $schedules): array
    {
        $nowUtc = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $horizonUtc = $nowUtc->modify('+24 hours');
        $occurrences = [];

        foreach ($schedules as $schedule) {
            $expression = trim((string) ($schedule['cron_expression'] ?? ''));
            $timezone = trim((string) ($schedule['timezone'] ?? 'UTC'));

            if ($expression === '' || $timezone === '') {
                continue;
            }

            $cursor = $nowUtc;

            for ($i = 0; $i < 120; $i++) {
                $nextLocal = $this->cron->nextRun($expression, $cursor, $timezone);
                $nextUtc = $nextLocal->setTimezone(new DateTimeZone('UTC'));

                if ($nextUtc > $horizonUtc) {
                    break;
                }

                $occurrences[] = [
                    'utc' => $nextUtc,
                    'local' => $nextLocal,
                    'group_title' => $schedule['group_title'] ?? null,
                    'template_name' => $schedule['template_name'] ?? null,
                    'timezone' => $timezone,
                ];

                $cursor = $nextUtc;
            }
        }

        usort(
            $occurrences,
            static fn (array $left, array $right): int => $left['utc'] <=> $right['utc']
        );

        return $occurrences;
    }

    private function normalizeAccountQueue(array $jobs, DateTimeImmutable $now): array
    {
        usort($jobs, function (array $left, array $right): int {
            $leftRunAt = strtotime((string) ($left['next_run_at'] ?? '')) ?: 0;
            $rightRunAt = strtotime((string) ($right['next_run_at'] ?? '')) ?: 0;

            return $leftRunAt <=> $rightRunAt ?: ((int) $left['id'] <=> (int) $right['id']);
        });

        if ($jobs === []) {
            return $jobs;
        }

        $guard = $this->determineGuard($jobs[0], $now);
        $queueStart = $guard['retry_at'] ?? $now;
        $minGapMinutes = (int) config('safety.account_limits.min_minutes_between_sends', 8);

        foreach ($jobs as $index => &$job) {
            $slot = $index === 0
                ? $queueStart
                : $queueStart->modify('+' . ($minGapMinutes * $index) . ' minutes');

            $slotString = $slot->format('Y-m-d H:i:s');
            $currentNextRunAt = (string) ($job['next_run_at'] ?? '');
            $queueNote = null;

            if ($index === 0 && $guard !== null) {
                $queueNote = 'Queue: ' . $guard['reason'];
            } elseif ($index > 0) {
                $queueNote = 'Queue: Schedule này đang chờ tới lượt theo account, dự kiến gửi lúc ' . fmt_datetime($slotString);
            }

            if (($index === 0 && $guard === null) || ($currentNextRunAt === $slotString && (string) ($job['last_error'] ?? '') === (string) $queueNote)) {
                continue;
            }

            $this->db->update('schedule_jobs', [
                'next_run_at' => $slotString,
                'last_error' => $queueNote,
                'updated_at' => $now->format('Y-m-d H:i:s'),
            ], 'id = :id', ['id' => (int) $job['id']]);

            $job['next_run_at'] = $slotString;
            $job['last_error'] = $queueNote;
        }
        unset($job);

        return $jobs;
    }

    private function dispatchOne(array $job, ?DateTimeImmutable $now = null, bool $manual = false): array
    {
        $now ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $scheduledAt = $this->nullableDate((string) ($job['next_run_at'] ?? '')) ?? $now;
        $guard = $this->determineGuard($job, $now);

        if ($guard !== null) {
            return $this->guardDispatch($job, $guard['retry_at'], $guard['reason'], $now, $manual);
        }

        $requestId = 'dispatch_' . bin2hex(random_bytes(6));
        $status = 'success';
        $payload = null;
        $error = null;
        $accountUpdates = [
            'updated_at' => $now->format('Y-m-d H:i:s'),
        ];
        $retryAt = null;

        try {
            if ($job['session_status'] !== 'active') {
                throw new RuntimeException('Telegram account chưa ở trạng thái active.');
            }

            $payload = $this->telegram->sendMessage(
                $job,
                (string) $job['peer_identifier'],
                (string) $job['body'],
                (string) $job['parse_mode'],
                $job['topic_id'] !== null ? (int) $job['topic_id'] : null
            );

            $accountUpdates['last_sent_at'] = $now->format('Y-m-d H:i:s');
            $accountUpdates['cooldown_until'] = $this->buildPostSendCooldown($now)->format('Y-m-d H:i:s');
            $accountUpdates['cooldown_reason'] = 'Giãn cách an toàn sau lần gửi gần nhất.';
        } catch (\Throwable $exception) {
            $status = 'error';
            $error = $exception->getMessage();
            $failureGuard = $this->buildFailureGuard($exception, $now);

            if ($failureGuard !== null) {
                $retryAt = $failureGuard['retry_at'];
                $accountUpdates['cooldown_until'] = $retryAt->format('Y-m-d H:i:s');
                $accountUpdates['cooldown_reason'] = $failureGuard['reason'];
                $error = $failureGuard['reason'] . ' | Chi tiết: ' . $exception->getMessage();
            }
        }

        $nextRunAt = $manual
            ? $this->determineManualNextRunAt($job, $scheduledAt, $now)
            : $this->calculateNextRun(
                (string) $job['cron_expression'],
                (string) $job['timezone'],
                $scheduledAt
            );
        if ($retryAt !== null) {
            $nextRunAt = $this->maxDateTimeString($nextRunAt, $retryAt->format('Y-m-d H:i:s'));
        }

        $this->db->transaction(function (Database $db) use ($job, $requestId, $status, $payload, $error, $nextRunAt, $accountUpdates, $now): void {
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
                'sent_at' => $now->format('Y-m-d H:i:s'),
                'created_at' => $now->format('Y-m-d H:i:s'),
            ]);

            $db->update('schedule_jobs', [
                'next_run_at' => $nextRunAt,
                'last_run_at' => $now->format('Y-m-d H:i:s'),
                'last_error' => $error,
                'dispatch_locked_until' => null,
                'updated_at' => $now->format('Y-m-d H:i:s'),
            ], 'id = :id', ['id' => (int) $job['id']]);

            $db->update('telegram_accounts', $accountUpdates, 'id = :id', ['id' => (int) $job['telegram_account_id']]);
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

    private function determineGuard(array $job, DateTimeImmutable $now): ?array
    {
        $guards = [];
        $cooldownUntil = $this->nullableDate((string) ($job['cooldown_until'] ?? ''));
        if ($cooldownUntil !== null && $cooldownUntil > $now) {
            $guards[] = [
                'retry_at' => $cooldownUntil,
                'reason' => 'Account đang trong thời gian cooldown an toàn đến ' . fmt_datetime($cooldownUntil->format('Y-m-d H:i:s')),
            ];
        }

        $lastSentAt = $this->nullableDate((string) ($job['last_sent_at'] ?? ''));
        $minGap = (int) config('safety.account_limits.min_minutes_between_sends', 8);
        if ($lastSentAt !== null) {
            $nextAllowedAt = $lastSentAt->modify('+' . $minGap . ' minutes');
            if ($nextAllowedAt > $now) {
                $guards[] = [
                    'retry_at' => $nextAllowedAt,
                    'reason' => 'Account vừa gửi gần đây, hệ thống đang giãn cách tối thiểu ' . $minGap . ' phút giữa hai lần gửi.',
                ];
            }
        }

        $hourlyLimit = (int) config('safety.account_limits.max_success_per_hour', 6);
        $hourly = $this->successWindow((int) $job['telegram_account_id'], '1 HOUR');
        if ($hourly['count'] >= $hourlyLimit && $hourly['oldest_at'] !== null) {
            $guards[] = [
                'retry_at' => $hourly['oldest_at']->modify('+1 hour'),
                'reason' => 'Account đã chạm giới hạn an toàn theo giờ. Hệ thống tạm lùi lịch để tránh spam flag.',
            ];
        }

        $dailyLimit = (int) config('safety.account_limits.max_success_per_day', 30);
        $daily = $this->successWindow((int) $job['telegram_account_id'], '1 DAY');
        if ($daily['count'] >= $dailyLimit && $daily['oldest_at'] !== null) {
            $guards[] = [
                'retry_at' => $daily['oldest_at']->modify('+1 day'),
                'reason' => 'Account đã chạm giới hạn an toàn theo ngày. Hệ thống tạm lùi lịch để tránh khóa tài khoản.',
            ];
        }

        if ($guards === []) {
            return null;
        }

        usort($guards, static fn (array $left, array $right): int => $left['retry_at'] <=> $right['retry_at']);

        return $guards[count($guards) - 1];
    }

    private function guardDispatch(array $job, DateTimeImmutable $retryAt, string $reason, DateTimeImmutable $now, bool $manual = false): array
    {
        $requestId = 'guard_' . bin2hex(random_bytes(6));
        $nextRunAt = $retryAt->format('Y-m-d H:i:s');

        if ($manual) {
            $currentNextRunAt = $this->nullableDate((string) ($job['next_run_at'] ?? ''));
            if ($currentNextRunAt !== null) {
                $nextRunAt = $this->maxDateTimeString($currentNextRunAt->format('Y-m-d H:i:s'), $nextRunAt);
            }
        }

        $this->db->transaction(function (Database $db) use ($job, $requestId, $reason, $now, $nextRunAt): void {
            $db->insert('dispatch_logs', [
                'user_id' => (int) $job['user_id'],
                'schedule_job_id' => (int) $job['id'],
                'telegram_account_id' => (int) $job['telegram_account_id'],
                'telegram_group_id' => (int) $job['telegram_group_id'],
                'message_template_id' => (int) $job['message_template_id'],
                'label_id' => $job['label_id'] ? (int) $job['label_id'] : null,
                'request_id' => $requestId,
                'status' => 'guarded',
                'message_preview' => mb_substr((string) $job['body'], 0, 500),
                'response_payload' => null,
                'error_message' => $reason,
                'sent_at' => $now->format('Y-m-d H:i:s'),
                'created_at' => $now->format('Y-m-d H:i:s'),
            ]);

            $db->update('schedule_jobs', [
                'next_run_at' => $nextRunAt,
                'last_error' => $reason,
                'dispatch_locked_until' => null,
                'updated_at' => $now->format('Y-m-d H:i:s'),
            ], 'id = :id', ['id' => (int) $job['id']]);

            $db->update('telegram_accounts', [
                'cooldown_until' => $nextRunAt,
                'cooldown_reason' => $reason,
                'updated_at' => $now->format('Y-m-d H:i:s'),
            ], 'id = :id', ['id' => (int) $job['telegram_account_id']]);
        });

        return [
            'schedule_id' => (int) $job['id'],
            'group' => $job['group_title'],
            'account' => $job['account_name'],
            'status' => 'guarded',
            'next_run_at' => $nextRunAt,
            'error' => $reason,
        ];
    }

    private function buildFailureGuard(Throwable $exception, DateTimeImmutable $now): ?array
    {
        $message = $exception->getMessage();
        $normalized = strtoupper($message);
        $looksLikeSpam = str_contains($normalized, 'FLOOD_WAIT')
            || str_contains($normalized, 'PEER_FLOOD')
            || str_contains($normalized, 'TOO_MANY_REQUESTS')
            || str_contains($normalized, 'SPAM');

        if (!$looksLikeSpam) {
            return null;
        }

        $retryAfterSeconds = $this->extractRetryAfterSeconds($message);
        $retryAt = $retryAfterSeconds !== null
            ? $now->modify('+' . $retryAfterSeconds . ' seconds')
            : $now->modify('+' . (int) config('safety.account_limits.spam_cooldown_minutes', 180) . ' minutes');

        return [
            'retry_at' => $retryAt,
            'reason' => 'Telegram đang giới hạn account này do tín hiệu spam/rate limit. Hệ thống đã tự cooldown để giảm rủi ro.',
        ];
    }

    private function determineManualNextRunAt(array $job, DateTimeImmutable $scheduledAt, DateTimeImmutable $now): string
    {
        if ($scheduledAt > $now) {
            return $scheduledAt->format('Y-m-d H:i:s');
        }

        return $this->calculateNextRun(
            (string) $job['cron_expression'],
            (string) $job['timezone'],
            $now
        );
    }

    private function successWindow(int $accountId, string $intervalSql): array
    {
        $row = $this->db->fetch(
            "SELECT COUNT(*) AS aggregate, MIN(sent_at) AS oldest_sent_at
             FROM dispatch_logs
             WHERE telegram_account_id = :account_id
               AND status = 'success'
               AND sent_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL {$intervalSql})",
            ['account_id' => $accountId]
        );

        return [
            'count' => (int) ($row['aggregate'] ?? 0),
            'oldest_at' => isset($row['oldest_sent_at']) && $row['oldest_sent_at'] !== null
                ? new DateTimeImmutable((string) $row['oldest_sent_at'], new DateTimeZone('UTC'))
                : null,
        ];
    }

    private function buildPostSendCooldown(DateTimeImmutable $now): DateTimeImmutable
    {
        $min = (int) config('safety.account_limits.post_send_jitter_seconds_min', 20);
        $max = (int) config('safety.account_limits.post_send_jitter_seconds_max', 75);
        $seconds = random_int(min($min, $max), max($min, $max));

        return $now->modify('+' . $seconds . ' seconds');
    }

    private function extractRetryAfterSeconds(string $message): ?int
    {
        if (preg_match('/FLOOD_WAIT_([0-9]+)/i', $message, $matches) === 1) {
            return (int) $matches[1];
        }

        if (preg_match('/([0-9]+)\s*seconds?/i', $message, $matches) === 1) {
            return (int) $matches[1];
        }

        if (preg_match('/([0-9]+)\s*minutes?/i', $message, $matches) === 1) {
            return (int) $matches[1] * 60;
        }

        return null;
    }

    private function nullableDate(string $value): ?DateTimeImmutable
    {
        if ($value === '') {
            return null;
        }

        return new DateTimeImmutable($value, new DateTimeZone('UTC'));
    }

    private function maxDateTimeString(string $left, string $right): string
    {
        return strtotime($left) >= strtotime($right) ? $left : $right;
    }

    private function isDueNow(string $value, DateTimeImmutable $now): bool
    {
        $date = $this->nullableDate($value);

        return $date !== null && $date <= $now;
    }
}
