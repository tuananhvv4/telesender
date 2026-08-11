<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\CronExpression;
use App\Core\Database;
use App\Models\SystemSetting;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

class AccountSafetyPolicyService
{
    public const MODE_SAFE = 'safe';
    public const MODE_ELEVATED = 'elevated';
    public const MODE_RISK_ACCEPTED = 'risk_accepted';

    private ?array $settings = null;

    public function __construct(
        private readonly Database $db,
        private readonly CronExpression $cron = new CronExpression()
    ) {
    }

    public function modes(): array
    {
        return [self::MODE_SAFE, self::MODE_ELEVATED, self::MODE_RISK_ACCEPTED];
    }

    public function modeLabel(string $mode): string
    {
        return match ($mode) {
            self::MODE_ELEVATED => 'Mở rộng giới hạn',
            self::MODE_RISK_ACCEPTED => 'Chấp nhận rủi ro',
            default => 'An toàn',
        };
    }

    public function resolvedPolicy(array $account): array
    {
        $mode = in_array((string) ($account['safety_mode'] ?? ''), $this->modes(), true)
            ? (string) $account['safety_mode']
            : self::MODE_SAFE;

        return match ($mode) {
            self::MODE_ELEVATED => [
                'mode' => $mode,
                'hourly_limit' => $this->settingInt('safety_elevated_hourly_limit', 10),
                'daily_limit' => $this->settingInt('safety_elevated_daily_limit', 80),
                'min_gap_minutes' => $this->settingInt('safety_elevated_min_gap_minutes', 5),
                'volume_unlimited' => false,
            ],
            self::MODE_RISK_ACCEPTED => [
                'mode' => $mode,
                'hourly_limit' => null,
                'daily_limit' => null,
                'min_gap_minutes' => $this->settingInt('safety_risk_min_gap_minutes', 1),
                'volume_unlimited' => true,
            ],
            default => [
                'mode' => self::MODE_SAFE,
                'hourly_limit' => $this->settingInt('safety_safe_hourly_limit', 8),
                'daily_limit' => $this->settingInt('safety_safe_daily_limit', 40),
                'min_gap_minutes' => $this->settingInt('safety_safe_min_gap_minutes', 8),
                'volume_unlimited' => false,
            ],
        };
    }

    public function statusForAccount(array $account, ?DateTimeImmutable $now = null): array
    {
        $now ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $policy = $this->resolvedPolicy($account);
        $modePolicies = [];
        foreach ($this->modes() as $mode) {
            $modePolicies[$mode] = $this->resolvedPolicy(['safety_mode' => $mode]);
        }
        $usage = $this->usage((int) $account['id']);
        $breakerUntil = $this->nullableDate((string) ($account['circuit_breaker_until'] ?? ''));

        return array_merge($policy, $usage, [
            'mode_label' => $this->modeLabel((string) $policy['mode']),
            'mode_policies' => $modePolicies,
            'safe_hourly_limit' => $this->settingInt('safety_safe_hourly_limit', 8),
            'safe_daily_limit' => $this->settingInt('safety_safe_daily_limit', 40),
            'circuit_breaker_active' => $breakerUntil !== null && $breakerUntil > $now,
            'circuit_breaker_until' => $breakerUntil?->format('Y-m-d H:i:s'),
            'circuit_breaker_reason' => (string) ($account['circuit_breaker_reason'] ?? ''),
        ]);
    }

    public function usage(int $accountId): array
    {
        $hourly = $this->successWindow($accountId, '1 HOUR');
        $daily = $this->successWindow($accountId, '1 DAY');

        return [
            'hourly_count' => $hourly['count'],
            'hourly_oldest_at' => $hourly['oldest_at'],
            'daily_count' => $daily['count'],
            'daily_oldest_at' => $daily['oldest_at'],
        ];
    }

    public function usageSnapshot(array $account): array
    {
        $policy = $this->resolvedPolicy($account);
        $usage = $this->usage((int) ($account['telegram_account_id'] ?? $account['id']));
        $safeHourly = $this->settingInt('safety_safe_hourly_limit', 8);
        $safeDaily = $this->settingInt('safety_safe_daily_limit', 40);
        $overrideReasons = [];

        if ($usage['hourly_count'] >= $safeHourly && $policy['mode'] !== self::MODE_SAFE) {
            $overrideReasons[] = 'hourly_success_limit';
        }
        if ($usage['daily_count'] >= $safeDaily && $policy['mode'] !== self::MODE_SAFE) {
            $overrideReasons[] = 'daily_success_limit';
        }

        return [
            'mode' => $policy['mode'],
            'hourly_count_before' => $usage['hourly_count'],
            'hourly_limit' => $policy['hourly_limit'],
            'daily_count_before' => $usage['daily_count'],
            'daily_limit' => $policy['daily_limit'],
            'safe_hourly_limit' => $safeHourly,
            'safe_daily_limit' => $safeDaily,
            'override_reasons' => $overrideReasons,
            'override_used' => $overrideReasons !== [],
        ];
    }

    public function determineGuard(array $account, DateTimeImmutable $now, bool $bypassSoft = false): ?array
    {
        $guards = [];
        $policy = $this->resolvedPolicy($account);
        $breakerUntil = $this->nullableDate((string) ($account['circuit_breaker_until'] ?? ''));

        if ($breakerUntil !== null && $breakerUntil > $now) {
            $guards[] = $this->guard(
                'circuit_breaker',
                'hard_telegram',
                $breakerUntil,
                (string) ($account['circuit_breaker_reason'] ?? 'Telegram account đang được circuit breaker bảo vệ.')
            );
        }

        $cooldownUntil = $this->nullableDate((string) ($account['cooldown_until'] ?? ''));
        $cooldownReason = (string) ($account['cooldown_reason'] ?? '');
        $softLegacyCooldown = $this->isSoftLimitReason($cooldownReason);
        $postSendCooldown = $this->isPostSendCooldownReason($cooldownReason);
        if (
            $cooldownUntil !== null
            && $cooldownUntil > $now
            && !(($softLegacyCooldown && ($bypassSoft || $policy['mode'] !== self::MODE_SAFE))
                || ($postSendCooldown && $bypassSoft))
        ) {
            $guards[] = $this->guard(
                $softLegacyCooldown ? 'legacy_volume_cooldown' : ($postSendCooldown ? 'post_send_cooldown' : 'account_cooldown'),
                $softLegacyCooldown ? 'soft_volume' : ($postSendCooldown ? 'soft_spacing' : 'hard_telegram'),
                $cooldownUntil,
                $cooldownReason !== '' ? $cooldownReason : 'Account đang trong thời gian cooldown.'
            );
        }

        if (!$bypassSoft) {
            $lastSentAt = $this->nullableDate((string) ($account['last_sent_at'] ?? ''));
            if ($lastSentAt !== null) {
                $nextAllowedAt = $lastSentAt->modify('+' . (int) $policy['min_gap_minutes'] . ' minutes');
                if ($nextAllowedAt > $now) {
                    $guards[] = $this->guard(
                        'minimum_gap',
                        'soft_spacing',
                        $nextAllowedAt,
                        'Account vừa gửi gần đây, cần giãn cách tối thiểu ' . $policy['min_gap_minutes'] . ' phút.'
                    );
                }
            }

            $volumeGuard = $this->determineVolumeGuard($account, $now);
            if ($volumeGuard !== null) {
                $guards[] = $volumeGuard;
            }
        }

        if ($guards === []) {
            return null;
        }

        usort($guards, static fn (array $left, array $right): int => $left['retry_at'] <=> $right['retry_at']);
        return $guards[count($guards) - 1];
    }

    public function determineVolumeGuard(array $account, DateTimeImmutable $now): ?array
    {
        $policy = $this->resolvedPolicy($account);
        if ((bool) $policy['volume_unlimited']) {
            return null;
        }

        $usage = $this->usage((int) ($account['telegram_account_id'] ?? $account['id']));
        $guards = [];

        if (
            $policy['hourly_limit'] !== null
            && $usage['hourly_count'] >= $policy['hourly_limit']
            && $usage['hourly_oldest_at'] instanceof DateTimeImmutable
        ) {
            $guards[] = $this->guard(
                'hourly_success_limit',
                'soft_volume',
                $usage['hourly_oldest_at']->modify('+1 hour'),
                'Account đã chạm giới hạn ' . $policy['hourly_limit'] . ' lượt thành công trong một giờ.'
            );
        }

        if (
            $policy['daily_limit'] !== null
            && $usage['daily_count'] >= $policy['daily_limit']
            && $usage['daily_oldest_at'] instanceof DateTimeImmutable
        ) {
            $guards[] = $this->guard(
                'daily_success_limit',
                'soft_volume',
                $usage['daily_oldest_at']->modify('+1 day'),
                'Account đã chạm giới hạn ' . $policy['daily_limit'] . ' lượt thành công trong 24 giờ.'
            );
        }

        if ($guards === []) {
            return null;
        }

        usort($guards, static fn (array $left, array $right): int => $left['retry_at'] <=> $right['retry_at']);
        return $guards[count($guards) - 1];
    }

    public function changeMode(
        array $account,
        array $actor,
        string $mode,
        string $queueAction,
        bool $acknowledged,
        string $reason = ''
    ): array {
        if (!in_array($mode, $this->modes(), true)) {
            throw new RuntimeException('Chế độ an toàn không hợp lệ.');
        }

        $this->authorizeModeChange($account, $actor, $mode);
        if ($mode === self::MODE_RISK_ACCEPTED && !$acknowledged) {
            throw new RuntimeException('Bạn cần xác nhận đã hiểu và chấp nhận rủi ro.');
        }

        if ($mode !== self::MODE_SAFE && !in_array($queueAction, ['recalculate_from_now', 'release_now'], true)) {
            throw new RuntimeException('Bạn cần chọn cách xử lý hàng đợi.');
        }

        $previousMode = (string) ($account['safety_mode'] ?? self::MODE_SAFE);
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $updates = [
            'safety_mode' => $mode,
            'safety_mode_changed_at' => $now->format('Y-m-d H:i:s'),
            'safety_mode_changed_by' => (int) $actor['id'],
            'updated_at' => $now->format('Y-m-d H:i:s'),
        ];

        if ($mode === self::MODE_RISK_ACCEPTED) {
            $updates['risk_acknowledged_at'] = $now->format('Y-m-d H:i:s');
            $updates['risk_acknowledged_by'] = (int) $actor['id'];
        }

        $accountAfter = array_merge($account, $updates);
        $queueResult = ['updated_count' => 0];

        $this->db->transaction(function (Database $db) use (
            $account,
            $accountAfter,
            $actor,
            $mode,
            $previousMode,
            $reason,
            $queueAction,
            $now,
            &$queueResult
        ): void {
            $db->update('telegram_accounts', array_intersect_key($accountAfter, array_flip([
                'safety_mode', 'safety_mode_changed_at', 'safety_mode_changed_by',
                'risk_acknowledged_at', 'risk_acknowledged_by', 'updated_at',
            ])), 'id = :id', ['id' => (int) $account['id']]);

            if ($mode !== self::MODE_SAFE) {
                $queueResult = $this->releaseQueue($accountAfter, $queueAction, $now);
                if ($this->isSoftLimitReason((string) ($account['cooldown_reason'] ?? ''))) {
                    $db->update('telegram_accounts', [
                        'cooldown_until' => null,
                        'cooldown_reason' => null,
                    ], 'id = :id', ['id' => (int) $account['id']]);
                }
            }

            $db->insert('account_safety_policy_events', [
                'user_id' => (int) $account['user_id'],
                'telegram_account_id' => (int) $account['id'],
                'actor_user_id' => (int) $actor['id'],
                'event_type' => 'mode_changed',
                'previous_mode' => $previousMode,
                'new_mode' => $mode,
                'reason' => $reason !== '' ? $reason : null,
                'metadata_json' => json_encode([
                    'queue_action' => $mode === self::MODE_SAFE ? null : $queueAction,
                    'queue_updated_count' => $queueResult['updated_count'],
                ], JSON_UNESCAPED_UNICODE),
                'created_at' => $now->format('Y-m-d H:i:s'),
            ]);
        });

        return [
            'mode' => $mode,
            'mode_label' => $this->modeLabel($mode),
            'queue_updated_count' => $queueResult['updated_count'],
            'queue_blocked_reason' => $queueResult['blocked_reason'] ?? null,
        ];
    }

    public function updateUserPermission(int $userId, int $actorId, bool $allowed): void
    {
        $now = gmdate('Y-m-d H:i:s');
        $this->db->transaction(function (Database $db) use ($userId, $actorId, $allowed, $now): void {
            $user = $db->fetch(
                'SELECT id, can_override_safety_limits FROM users WHERE id = :id AND role = :role LIMIT 1',
                ['id' => $userId, 'role' => 'admin']
            );
            if ($user === null) {
                throw new RuntimeException('Không tìm thấy admin cần cập nhật quyền.');
            }

            $previousAllowed = (bool) ($user['can_override_safety_limits'] ?? false);
            if ($previousAllowed === $allowed) {
                return;
            }

            $db->update('users', [
                'can_override_safety_limits' => $allowed ? 1 : 0,
                'updated_at' => $now,
            ], 'id = :id AND role = :role', ['id' => $userId, 'role' => 'admin']);

            $db->insert('user_safety_permission_events', [
                'target_user_id' => $userId,
                'actor_user_id' => $actorId,
                'previous_allowed' => $previousAllowed ? 1 : 0,
                'new_allowed' => $allowed ? 1 : 0,
                'reason' => $allowed
                    ? 'Super admin đã cấp quyền chấp nhận rủi ro.'
                    : 'Super admin đã thu hồi quyền chấp nhận rủi ro.',
                'created_at' => $now,
            ]);

            $accounts = $db->fetchAll(
                'SELECT * FROM telegram_accounts WHERE user_id = :user_id',
                ['user_id' => $userId]
            );

            foreach ($accounts as $account) {
                if (!$allowed && (string) $account['safety_mode'] !== self::MODE_SAFE) {
                    $db->update('telegram_accounts', [
                        'safety_mode' => self::MODE_SAFE,
                        'safety_mode_changed_at' => $now,
                        'safety_mode_changed_by' => $actorId,
                        'updated_at' => $now,
                    ], 'id = :id', ['id' => (int) $account['id']]);
                    $db->insert('account_safety_policy_events', [
                        'user_id' => $userId,
                        'telegram_account_id' => (int) $account['id'],
                        'actor_user_id' => $actorId,
                        'event_type' => 'permission_revoked',
                        'previous_mode' => (string) $account['safety_mode'],
                        'new_mode' => self::MODE_SAFE,
                        'reason' => 'Super admin đã thu hồi quyền chấp nhận rủi ro.',
                        'metadata_json' => null,
                        'created_at' => $now,
                    ]);
                }
            }
        });
    }

    public function openCircuitBreaker(array $account, string $code, string $reason, DateTimeImmutable $retryAt): void
    {
        $now = gmdate('Y-m-d H:i:s');
        $this->db->transaction(function (Database $db) use ($account, $code, $reason, $retryAt, $now): void {
            $db->update('telegram_accounts', [
                'cooldown_until' => $retryAt->format('Y-m-d H:i:s'),
                'cooldown_reason' => $reason,
                'circuit_breaker_until' => $retryAt->format('Y-m-d H:i:s'),
                'circuit_breaker_reason' => $reason,
                'updated_at' => $now,
            ], 'id = :id', ['id' => (int) ($account['telegram_account_id'] ?? $account['id'])]);

            $db->insert('account_safety_policy_events', [
                'user_id' => (int) $account['user_id'],
                'telegram_account_id' => (int) ($account['telegram_account_id'] ?? $account['id']),
                'actor_user_id' => null,
                'event_type' => 'circuit_breaker_opened',
                'previous_mode' => (string) ($account['safety_mode'] ?? self::MODE_SAFE),
                'new_mode' => (string) ($account['safety_mode'] ?? self::MODE_SAFE),
                'reason' => $reason,
                'metadata_json' => json_encode([
                    'code' => $code,
                    'retry_at' => $retryAt->format('Y-m-d H:i:s'),
                ], JSON_UNESCAPED_UNICODE),
                'created_at' => $now,
            ]);
        });
    }

    public function shouldOpenCircuitBreaker(array $account, string $code): bool
    {
        if (in_array($code, [
            'telegram_peer_flood',
            'telegram_flood_wait',
            'telegram_rate_limit',
            'telegram_spam_signal',
        ], true)) {
            return true;
        }

        $windowMinutes = $this->settingInt('safety_circuit_breaker_window_minutes', 15);
        $threshold = $this->settingInt('safety_circuit_breaker_error_count', 3);
        $row = $this->db->fetch(
            'SELECT COUNT(*) AS aggregate
             FROM dispatch_logs
             WHERE telegram_account_id = :account_id
               AND status = \'error\'
               AND sent_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL ' . $windowMinutes . ' MINUTE)
               AND (UPPER(error_message) LIKE \'%SPAM%\' OR UPPER(error_message) LIKE \'%RATE LIMIT%\')',
            ['account_id' => (int) ($account['telegram_account_id'] ?? $account['id'])]
        );

        return (int) ($row['aggregate'] ?? 0) >= $threshold;
    }

    public function cleanupExpired(): void
    {
        $days = max(1, $this->settingInt('safety_audit_retention_days', 30));
        $this->db->query(
            'DELETE FROM account_safety_policy_events WHERE created_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL ' . $days . ' DAY)'
        );
        $this->db->query(
            'DELETE FROM user_safety_permission_events WHERE created_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL ' . $days . ' DAY)'
        );
    }

    public function adminSelfOverrideEnabled(): bool
    {
        return (int) ($this->settings()['safety_admin_self_override_enabled'] ?? 1) === 1;
    }

    public function circuitBreakerCooldownMinutes(): int
    {
        return $this->settingInt('safety_circuit_breaker_cooldown_minutes', 180);
    }

    public function auditRetentionDays(): int
    {
        return $this->settingInt('safety_audit_retention_days', 30);
    }

    private function authorizeModeChange(array $account, array $actor, string $mode): void
    {
        if ((string) ($actor['role'] ?? '') === 'super_admin') {
            return;
        }

        if ((int) ($account['user_id'] ?? 0) !== (int) ($actor['id'] ?? 0)) {
            throw new RuntimeException('Bạn không có quyền thay đổi account này.');
        }

        if ($mode === self::MODE_SAFE) {
            return;
        }

        if (!$this->adminSelfOverrideEnabled() || !(bool) ($actor['can_override_safety_limits'] ?? false)) {
            throw new RuntimeException('Super admin chưa cấp quyền chấp nhận rủi ro cho tài khoản của bạn.');
        }
    }

    private function releaseQueue(array $account, string $action, DateTimeImmutable $now): array
    {
        $hardGuard = $this->determineGuard($account, $now, true);
        if ($hardGuard !== null) {
            return [
                'updated_count' => 0,
                'blocked_reason' => (string) $hardGuard['reason'],
            ];
        }

        $jobs = $this->db->fetchAll(
            'SELECT id, cron_expression, timezone, next_run_at
             FROM schedule_jobs
             WHERE telegram_account_id = :account_id
               AND status = :status
               AND (queue_reason_code IN (\'hourly_success_limit\', \'daily_success_limit\', \'minimum_gap\', \'account_queue\')
                    OR (queue_reason_code IS NULL AND last_error LIKE \'Queue:%\'))
             ORDER BY next_run_at ASC, id ASC',
            ['account_id' => (int) $account['id'], 'status' => 'active']
        );

        if ($jobs === []) {
            return ['updated_count' => 0];
        }

        $gapMinutes = (int) $this->resolvedPolicy($account)['min_gap_minutes'];
        $slots = [];

        foreach ($jobs as $index => $job) {
            if ($action === 'release_now') {
                $slot = $now->modify('+' . ($gapMinutes * $index) . ' minutes');
            } else {
                $slot = $this->cron->nextRun(
                    (string) $job['cron_expression'],
                    $now,
                    (string) $job['timezone']
                )->setTimezone(new DateTimeZone('UTC'));
            }

            $slots[] = ['job' => $job, 'slot' => $slot];
        }

        usort($slots, static fn (array $left, array $right): int => $left['slot'] <=> $right['slot']);
        $previous = null;
        foreach ($slots as $item) {
            $slot = $item['slot'];
            if ($previous instanceof DateTimeImmutable) {
                $minimum = $previous->modify('+' . $gapMinutes . ' minutes');
                if ($slot < $minimum) {
                    $slot = $minimum;
                }
            }

            $this->db->update('schedule_jobs', [
                'next_run_at' => $slot->format('Y-m-d H:i:s'),
                'last_error' => null,
                'queue_reason_code' => null,
                'updated_at' => $now->format('Y-m-d H:i:s'),
            ], 'id = :id', ['id' => (int) $item['job']['id']]);
            $previous = $slot;
        }

        return ['updated_count' => count($slots)];
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

    private function settings(): array
    {
        return $this->settings ??= (new SystemSetting())->resolvedMap();
    }

    private function settingInt(string $key, int $default): int
    {
        return max(1, (int) ($this->settings()[$key] ?? $default));
    }

    private function guard(string $code, string $category, DateTimeImmutable $retryAt, string $reason): array
    {
        return [
            'code' => $code,
            'category' => $category,
            'retry_at' => $retryAt,
            'reason' => $reason,
        ];
    }

    private function isSoftLimitReason(string $reason): bool
    {
        $normalized = mb_strtolower($reason);
        return str_contains($normalized, 'giới hạn an toàn theo giờ')
            || str_contains($normalized, 'giới hạn an toàn theo ngày')
            || str_contains($normalized, 'giới hạn') && str_contains($normalized, 'lượt thành công');
    }

    private function isPostSendCooldownReason(string $reason): bool
    {
        return str_contains(mb_strtolower($reason), 'giãn cách an toàn sau lần gửi');
    }

    private function nullableDate(string $value): ?DateTimeImmutable
    {
        return $value === '' ? null : new DateTimeImmutable($value, new DateTimeZone('UTC'));
    }
}
