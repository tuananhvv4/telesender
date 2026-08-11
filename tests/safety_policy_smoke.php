<?php

declare(strict_types=1);

use App\Core\Application;
use App\Core\CronExpression;
use App\Services\AccountSafetyPolicyService;
use App\Services\SchedulerService;
use App\Services\TelegramService;
use App\Services\ScheduleBuilderService;

require dirname(__DIR__) . '/bootstrap/autoload.php';

$app = Application::boot(base_path());
$db = $app->db();
$suffix = bin2hex(random_bytes(5));
$userId = null;
$accountId = null;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

try {
    $now = gmdate('Y-m-d H:i:s');
    $userId = $db->insert('users', [
        'name' => 'Safety smoke test',
        'email' => 'safety-' . $suffix . '@example.test',
        'password_hash' => password_hash($suffix, PASSWORD_DEFAULT),
        'role' => 'admin',
        'status' => 'active',
        'subscription_expires_at' => gmdate('Y-m-d H:i:s', time() + 86400),
        'max_telegram_accounts' => null,
        'max_schedule_jobs' => null,
        'can_override_safety_limits' => 1,
        'internal_note' => null,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $accountId = $db->insert('telegram_accounts', [
        'user_id' => $userId,
        'name' => 'Safety account ' . $suffix,
        'phone_number' => '+84000000000',
        'session_name' => 'safety_' . $suffix,
        'session_status' => 'active',
        'is_active' => 1,
        'safety_mode' => 'safe',
        'meta_json' => null,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $groupId = $db->insert('telegram_groups', [
        'user_id' => $userId,
        'telegram_account_id' => $accountId,
        'title' => 'Safety group',
        'peer_identifier' => '-100000000001',
        'topic_id' => null,
        'topic_title' => null,
        'notes' => null,
        'is_active' => 1,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $templateId = $db->insert('message_templates', [
        'user_id' => $userId,
        'label_id' => null,
        'name' => 'Safety template',
        'body' => 'Safety test',
        'parse_mode' => 'HTML',
        'is_active' => 1,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $scheduleId = $db->insert('schedule_jobs', [
        'user_id' => $userId,
        'telegram_account_id' => $accountId,
        'telegram_group_id' => $groupId,
        'message_template_id' => $templateId,
        'timezone' => 'Asia/Ho_Chi_Minh',
        'cron_expression' => '0 */2 * * *',
        'schedule_type' => 'advanced',
        'schedule_config_json' => null,
        'next_run_at' => gmdate('Y-m-d H:i:s', time() + 86400),
        'last_run_at' => null,
        'last_error' => 'Queue: Account đã chạm giới hạn an toàn theo ngày.',
        'queue_reason_code' => 'daily_success_limit',
        'status' => 'active',
        'dispatch_locked_until' => null,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $db->insert('schedule_job_groups', [
        'schedule_job_id' => $scheduleId,
        'telegram_group_id' => $groupId,
        'sort_order' => 0,
        'created_at' => $now,
    ]);

    $queueNow = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    $queueDueAt = $queueNow->modify('-1 minute')->format('Y-m-d H:i:s');
    $extraScheduleIds = [];
    $queueJobs = [];
    for ($index = 0; $index < 9; $index++) {
        $queueScheduleId = $index === 0 ? $scheduleId : $db->insert('schedule_jobs', [
            'user_id' => $userId,
            'telegram_account_id' => $accountId,
            'telegram_group_id' => $groupId,
            'message_template_id' => $templateId,
            'timezone' => 'Asia/Ho_Chi_Minh',
            'cron_expression' => '0 9 * * *',
            'schedule_type' => 'advanced',
            'schedule_config_json' => null,
            'next_run_at' => $queueDueAt,
            'occurrence_due_at' => null,
            'last_run_at' => null,
            'last_error' => null,
            'queue_reason_code' => null,
            'status' => 'active',
            'dispatch_locked_until' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if ($index === 0) {
            $db->update('schedule_jobs', [
                'next_run_at' => $queueDueAt,
                'occurrence_due_at' => null,
                'last_error' => null,
                'queue_reason_code' => null,
            ], 'id = :id', ['id' => $scheduleId]);
        } else {
            $extraScheduleIds[] = $queueScheduleId;
            $db->insert('schedule_job_groups', [
                'schedule_job_id' => $queueScheduleId,
                'telegram_group_id' => $groupId,
                'sort_order' => 0,
                'created_at' => $now,
            ]);
        }

        $queueJobs[] = [
            'id' => $queueScheduleId,
            'user_id' => $userId,
            'telegram_account_id' => $accountId,
            'account_name' => 'Safety account ' . $suffix,
            'safety_mode' => 'safe',
            'last_sent_at' => null,
            'cooldown_until' => null,
            'cooldown_reason' => null,
            'circuit_breaker_until' => null,
            'circuit_breaker_reason' => null,
            'cron_expression' => '0 9 * * *',
            'timezone' => 'Asia/Ho_Chi_Minh',
            'next_run_at' => $queueDueAt,
            'occurrence_due_at' => null,
            'last_error' => null,
            'target_groups' => [],
        ];
    }

    $normalizeQueue = new ReflectionMethod(SchedulerService::class, 'normalizeAccountQueue');
    $normalizedQueue = $normalizeQueue->invoke(
        new SchedulerService($db, new TelegramService(), new CronExpression()),
        $queueJobs,
        $queueNow
    );
    $assert(count($normalizedQueue) === 8, 'Eight safe-mode schedules must remain queued inside the 60-minute window.');
    $skippedQueueCount = (int) ($db->fetch(
        'SELECT COUNT(*) AS aggregate FROM schedule_jobs WHERE user_id = :user_id AND queue_reason_code = :reason',
        ['user_id' => $userId, 'reason' => 'stale_occurrence_skipped']
    )['aggregate'] ?? 0);
    $assert($skippedQueueCount === 1, 'The ninth colliding safe-mode schedule must skip its occurrence beyond 60 minutes.');

    foreach ($extraScheduleIds as $extraScheduleId) {
        $db->query('DELETE FROM schedule_jobs WHERE id = :id', ['id' => $extraScheduleId]);
    }
    $db->update('schedule_jobs', [
        'next_run_at' => gmdate('Y-m-d H:i:s', time() + 86400),
        'occurrence_due_at' => null,
        'last_error' => 'Queue: Account đã chạm giới hạn an toàn theo ngày.',
        'queue_reason_code' => 'daily_success_limit',
    ], 'id = :id', ['id' => $scheduleId]);

    for ($index = 0; $index < 40; $index++) {
        $db->insert('dispatch_logs', [
            'user_id' => $userId,
            'schedule_job_id' => $scheduleId,
            'schedule_run_key' => 'smoke_' . $suffix . '_' . $index,
            'telegram_account_id' => $accountId,
            'telegram_group_id' => $groupId,
            'message_template_id' => $templateId,
            'template_name_snapshot' => 'Safety template',
            'message_body_snapshot' => 'Safety test',
            'parse_mode_snapshot' => 'HTML',
            'label_id' => null,
            'request_id' => 'smoke_' . $suffix . '_' . $index,
            'status' => 'success',
            'safety_mode_snapshot' => 'safe',
            'safety_override_used' => 0,
            'safety_usage_snapshot_json' => null,
            'message_preview' => 'Safety test',
            'response_payload' => null,
            'error_message' => null,
            'sent_at' => gmdate('Y-m-d H:i:s', time() - 60 - $index),
            'created_at' => $now,
        ]);
    }

    $policy = new AccountSafetyPolicyService($db, new CronExpression());
    $scheduler = new SchedulerService($db, new TelegramService(), new CronExpression());
    $account = $db->fetch('SELECT * FROM telegram_accounts WHERE id = :id', ['id' => $accountId]);
    $actor = $db->fetch('SELECT * FROM users WHERE id = :id', ['id' => $userId]);
    $safeGuard = $policy->determineVolumeGuard($account, new DateTimeImmutable('now', new DateTimeZone('UTC')));
    $assert(($safeGuard['code'] ?? '') === 'daily_success_limit', 'Safe mode must stop at the 40-per-day limit.');

    $changed = $policy->changeMode($account, $actor, 'risk_accepted', 'release_now', true, 'Smoke test');
    $assert($changed['queue_updated_count'] === 1, 'Risk mode must release the queued schedule.');
    $account = $db->fetch('SELECT * FROM telegram_accounts WHERE id = :id', ['id' => $accountId]);
    $schedule = $db->fetch('SELECT * FROM schedule_jobs WHERE id = :id', ['id' => $scheduleId]);
    $assert($account['safety_mode'] === 'risk_accepted', 'Account must be in risk accepted mode.');
    $assert($schedule['queue_reason_code'] === null, 'Released schedule must clear queue reason.');
    $assert($policy->determineVolumeGuard($account, new DateTimeImmutable('now', new DateTimeZone('UTC'))) === null, 'Risk mode must bypass volume limits.');
    $safeDenseRisk = $scheduler->analyzeScheduleRisk('* * * * *', 'UTC', 1, ['safety_mode' => 'safe']);
    $riskDenseRisk = $scheduler->analyzeScheduleRisk('* * * * *', 'UTC', 1, $account);
    $safeTenTargetRisk = $scheduler->analyzeScheduleRisk('0 8 * * *', 'UTC', 10, ['safety_mode' => 'safe']);
    $elevatedTenTargetRisk = $scheduler->analyzeScheduleRisk('0 8 * * *', 'UTC', 10, ['safety_mode' => 'elevated']);
    $assert($safeDenseRisk['risk'] === 'blocked', 'Safe mode must reject a one-minute schedule.');
    $assert($riskDenseRisk['risk'] !== 'blocked', 'Risk accepted mode must allow a one-minute schedule.');
    $assert($safeTenTargetRisk['risk'] === 'blocked', 'Safe mode must enforce its hourly target limit while creating schedules.');
    $assert($elevatedTenTargetRisk['risk'] !== 'blocked', 'Elevated mode must use elevated limits while creating schedules.');
    $oneMinuteSchedule = (new ScheduleBuilderService(new CronExpression()))->buildFromPayload([
        'schedule_type' => 'interval_minutes',
        'interval_minutes' => 1,
    ]);
    $assert($oneMinuteSchedule['cron_expression'] === '*/1 * * * *', 'Schedule builder must support one-minute risk schedules.');
    $assert($policy->shouldOpenCircuitBreaker($account, 'telegram_spam_signal'), 'Generic Telegram spam signals must open the breaker immediately.');

    $successfulTelegram = new class extends TelegramService {
        public function sendMessage(
            array $account,
            string $peer,
            string $message,
            string $parseMode = 'HTML',
            ?int $topicId = null
        ): array {
            return ['id' => 123456];
        }
    };
    $oldRunAt = gmdate('Y-m-d H:i:s', time() - 30 * 60);
    $lockUntil = gmdate('Y-m-d H:i:s', time() + 300);
    $db->update('schedule_jobs', [
        'next_run_at' => $oldRunAt,
        'dispatch_locked_until' => $lockUntil,
    ], 'id = :id', ['id' => $scheduleId]);
    $db->update('telegram_accounts', [
        'dispatch_locked_until' => $lockUntil,
        'last_sent_at' => null,
        'cooldown_until' => null,
        'cooldown_reason' => null,
    ], 'id = :id', ['id' => $accountId]);
    $dispatchJob = array_merge(
        $db->fetch('SELECT * FROM schedule_jobs WHERE id = :id', ['id' => $scheduleId]) ?? [],
        [
            'account_name' => 'Safety account ' . $suffix,
            'phone_number' => '+84000000000',
            'session_name' => 'safety_' . $suffix,
            'session_status' => 'active',
            'account_active' => 1,
            'safety_mode' => 'risk_accepted',
            'circuit_breaker_until' => null,
            'circuit_breaker_reason' => null,
            'owner_role' => 'admin',
            'owner_status' => 'active',
            'owner_subscription_expires_at' => gmdate('Y-m-d H:i:s', time() + 86400),
            'template_name' => 'Safety template',
            'body' => 'Safety test',
            'parse_mode' => 'HTML',
            'label_id' => null,
            'target_groups' => [[
                'id' => $groupId,
                'title' => 'Safety group',
                'peer_identifier' => '-100000000001',
                'topic_id' => null,
                'topic_title' => '',
            ]],
        ]
    );
    $dispatchOne = new ReflectionMethod(SchedulerService::class, 'dispatchOne');
    $delayExceeded = new ReflectionMethod(SchedulerService::class, 'occurrenceDelayExceeded');
    $delayBoundary = new DateTimeImmutable('2026-01-01 00:00:00', new DateTimeZone('UTC'));
    $assert(
        !$delayExceeded->invoke($scheduler, $delayBoundary, $delayBoundary->modify('+60 minutes')),
        'An occurrence delayed by exactly 60 minutes must still be eligible.'
    );
    $assert(
        $delayExceeded->invoke($scheduler, $delayBoundary, $delayBoundary->modify('+60 minutes 1 second')),
        'An occurrence delayed by more than 60 minutes must expire.'
    );
    $automaticResult = $dispatchOne->invoke(
        new SchedulerService($db, $successfulTelegram, new CronExpression()),
        $dispatchJob,
        new DateTimeImmutable('now', new DateTimeZone('UTC')),
        false,
        false,
        null
    );
    $assert($automaticResult['status'] === 'success', 'Synthetic overdue automatic dispatch must succeed.');
    $automaticNextRun = new DateTimeImmutable((string) $automaticResult['next_run_at'], new DateTimeZone('UTC'));
    $assert(
        $automaticNextRun > new DateTimeImmutable('now', new DateTimeZone('UTC')),
        'Automatic dispatch must calculate the next run from the current time instead of replaying older occurrences.'
    );

    $db->update('telegram_accounts', [
        'last_sent_at' => null,
        'cooldown_until' => null,
        'cooldown_reason' => null,
        'dispatch_locked_until' => $lockUntil,
    ], 'id = :id', ['id' => $accountId]);
    $staleRunAt = gmdate('Y-m-d H:i:s', time() - 61 * 60);
    $db->update('schedule_jobs', [
        'next_run_at' => $staleRunAt,
        'occurrence_due_at' => null,
        'dispatch_locked_until' => $lockUntil,
    ], 'id = :id', ['id' => $scheduleId]);
    $dispatchJob['next_run_at'] = $staleRunAt;
    $dispatchJob['occurrence_due_at'] = null;
    $staleResult = $dispatchOne->invoke(
        new SchedulerService($db, $successfulTelegram, new CronExpression()),
        $dispatchJob,
        new DateTimeImmutable('now', new DateTimeZone('UTC')),
        false,
        false,
        null
    );
    $assert($staleResult['status'] === 'skipped', 'An occurrence delayed by more than 60 minutes must be skipped.');
    $assert(
        new DateTimeImmutable((string) $staleResult['next_run_at'], new DateTimeZone('UTC'))
            > new DateTimeImmutable('now', new DateTimeZone('UTC')),
        'A skipped stale occurrence must move to a future cron run.'
    );

    $db->update('telegram_accounts', [
        'dispatch_locked_until' => null,
    ], 'id = :id', ['id' => $accountId]);
    $riskWithBreaker = array_merge($account, [
        'circuit_breaker_until' => gmdate('Y-m-d H:i:s', time() + 600),
        'circuit_breaker_reason' => 'Smoke circuit breaker',
    ]);
    $hardGuard = $policy->determineGuard($riskWithBreaker, new DateTimeImmutable('now', new DateTimeZone('UTC')), true);
    $assert(($hardGuard['code'] ?? '') === 'circuit_breaker', 'Force must not bypass circuit breaker.');

    $failingTelegram = new class extends TelegramService {
        public function sendMessage(
            array $account,
            string $peer,
            string $message,
            string $parseMode = 'HTML',
            ?int $topicId = null
        ): array {
            throw new RuntimeException('PEER_FLOOD smoke restriction');
        }
    };
    $dispatchResult = (new SchedulerService($db, $failingTelegram, new CronExpression()))
        ->dispatchScheduleNow($scheduleId, $userId);
    $assert($dispatchResult['status'] === 'error', 'Synthetic PEER_FLOOD dispatch must fail.');
    $account = $db->fetch('SELECT * FROM telegram_accounts WHERE id = :id', ['id' => $accountId]);
    $assert(!empty($account['circuit_breaker_until']), 'PEER_FLOOD must open circuit breaker immediately.');
    $assert($account['safety_mode'] === 'risk_accepted', 'Circuit breaker must not change risk accepted mode.');

    $ownerNotifications = (int) ($db->fetch(
        'SELECT COUNT(*) AS aggregate FROM user_notifications WHERE user_id = :user_id AND telegram_account_id = :account_id',
        ['user_id' => $userId, 'account_id' => $accountId]
    )['aggregate'] ?? 0);
    $assert($ownerNotifications === 1, 'Account owner must receive one deduplicated notification.');
    $activeSuperAdmins = (int) ($db->fetch(
        'SELECT COUNT(*) AS aggregate FROM users WHERE role = \'super_admin\' AND status = \'active\''
    )['aggregate'] ?? 0);
    $superAdminNotifications = (int) ($db->fetch(
        'SELECT COUNT(*) AS aggregate
         FROM user_notifications notifications
         INNER JOIN users recipients ON recipients.id = notifications.user_id
         WHERE notifications.telegram_account_id = :account_id
           AND recipients.role = \'super_admin\'
           AND recipients.status = \'active\'',
        ['account_id' => $accountId]
    )['aggregate'] ?? 0);
    $assert($superAdminNotifications === $activeSuperAdmins, 'Every active super admin must receive the Telegram restriction notification.');

    $actorId = (int) ($db->fetch('SELECT id FROM users WHERE role = \'super_admin\' ORDER BY id LIMIT 1')['id'] ?? $userId);
    $policy->updateUserPermission($userId, $actorId, false);
    $account = $db->fetch('SELECT * FROM telegram_accounts WHERE id = :id', ['id' => $accountId]);
    $assert($account['safety_mode'] === 'safe', 'Revoking permission must return account to safe mode.');
    $permissionEvents = (int) ($db->fetch(
        'SELECT COUNT(*) AS aggregate FROM user_safety_permission_events WHERE target_user_id = :user_id',
        ['user_id' => $userId]
    )['aggregate'] ?? 0);
    $assert($permissionEvents === 1, 'Permission changes must have a user-level audit event.');

    $postSendGuard = $policy->determineGuard(array_merge($account, [
        'cooldown_until' => gmdate('Y-m-d H:i:s', time() + 60),
        'cooldown_reason' => 'Giãn cách an toàn sau lần gửi gần nhất.',
        'last_sent_at' => null,
        'circuit_breaker_until' => null,
        'circuit_breaker_reason' => null,
    ]), new DateTimeImmutable('now', new DateTimeZone('UTC')), true);
    $assert($postSendGuard === null, 'Force may bypass post-send spacing but not Telegram cooldown.');

    echo "Safety policy smoke test passed.\n";
} finally {
    if ($accountId !== null) {
        $db->query('DELETE FROM user_notifications WHERE telegram_account_id = :account_id', ['account_id' => $accountId]);
    }
    if ($userId !== null) {
        $db->query('DELETE FROM users WHERE id = :id', ['id' => $userId]);
    }
}
