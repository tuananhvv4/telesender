<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

class TelegramAccountLockService
{
    public const TYPE_DISPATCH = 'dispatch';
    public const TYPE_INBOX_SYNC = 'inbox_sync';
    public const TYPE_MEDIA = 'media';

    private ?bool $supportsOwnedLocks = null;
    private array $legacyLocks = [];

    public function __construct(private readonly Database $db)
    {
    }

    public function acquireDispatch(int $accountId, int $leaseSeconds): ?string
    {
        return $this->acquire($accountId, self::TYPE_DISPATCH, $leaseSeconds, 0);
    }

    public function acquireInbox(int $accountId, int $leaseSeconds, int $lookaheadSeconds): ?string
    {
        if (!$this->supportsOwnedLocks()) {
            return null;
        }

        return $this->acquire($accountId, self::TYPE_INBOX_SYNC, $leaseSeconds, $lookaheadSeconds);
    }

    public function acquireMedia(int $accountId, int $leaseSeconds, int $lookaheadSeconds): ?string
    {
        if (!$this->supportsOwnedLocks()) {
            return null;
        }

        return $this->acquire($accountId, self::TYPE_MEDIA, $leaseSeconds, $lookaheadSeconds);
    }

    public function refresh(int $accountId, string $token, int $leaseSeconds): bool
    {
        $until = gmdate('Y-m-d H:i:s', time() + max(1, $leaseSeconds));
        if (!$this->supportsOwnedLocks()) {
            $previousUntil = $this->legacyLocks[$token]['locked_until'] ?? null;
            if ($previousUntil === null || (int) ($this->legacyLocks[$token]['account_id'] ?? 0) !== $accountId) {
                return false;
            }

            $statement = $this->db->query(
                'UPDATE telegram_accounts
                 SET dispatch_locked_until = :locked_until
                 WHERE id = :id
                   AND dispatch_locked_until = :previous_locked_until
                   AND dispatch_locked_until >= UTC_TIMESTAMP()',
                [
                    'locked_until' => $until,
                    'id' => $accountId,
                    'previous_locked_until' => $previousUntil,
                ]
            );
            if ($statement->rowCount() === 1) {
                $this->legacyLocks[$token]['locked_until'] = $until;
                return true;
            }

            $current = $this->db->fetch(
                'SELECT id FROM telegram_accounts
                 WHERE id = :id
                   AND dispatch_locked_until = :locked_until
                   AND dispatch_locked_until >= UTC_TIMESTAMP()
                 LIMIT 1',
                ['id' => $accountId, 'locked_until' => $previousUntil]
            );
            if ($current !== null) {
                return true;
            }

            unset($this->legacyLocks[$token]);
            return false;
        }

        $statement = $this->db->query(
            'UPDATE telegram_accounts
             SET dispatch_locked_until = :locked_until
             WHERE id = :id
               AND operation_lock_token = :lock_token
               AND dispatch_locked_until >= UTC_TIMESTAMP()',
            [
                'locked_until' => $until,
                'id' => $accountId,
                'lock_token' => $token,
            ]
        );

        if ($statement->rowCount() === 1) {
            return true;
        }

        return $this->db->fetch(
            'SELECT id FROM telegram_accounts
             WHERE id = :id
               AND operation_lock_token = :lock_token
               AND dispatch_locked_until >= UTC_TIMESTAMP()
             LIMIT 1',
            ['id' => $accountId, 'lock_token' => $token]
        ) !== null;
    }

    public function release(int $accountId, string $token): void
    {
        if (!$this->supportsOwnedLocks()) {
            $lockedUntil = $this->legacyLocks[$token]['locked_until'] ?? null;
            if ($lockedUntil !== null && (int) ($this->legacyLocks[$token]['account_id'] ?? 0) === $accountId) {
                $this->db->query(
                    'UPDATE telegram_accounts
                     SET dispatch_locked_until = NULL
                     WHERE id = :id AND dispatch_locked_until = :locked_until',
                    ['id' => $accountId, 'locked_until' => $lockedUntil]
                );
            }
            unset($this->legacyLocks[$token]);
            return;
        }

        $this->db->query(
            'UPDATE telegram_accounts
             SET dispatch_locked_until = NULL,
                 operation_lock_type = NULL,
                 operation_lock_token = NULL
             WHERE id = :id AND operation_lock_token = :lock_token',
            ['id' => $accountId, 'lock_token' => $token]
        );
    }

    private function acquire(int $accountId, string $type, int $leaseSeconds, int $lookaheadSeconds): ?string
    {
        if ($lookaheadSeconds > 0 && $this->hasUpcomingDispatch($accountId, $lookaheadSeconds)) {
            return null;
        }

        $token = bin2hex(random_bytes(16));
        $until = gmdate('Y-m-d H:i:s', time() + max(1, $leaseSeconds));
        if (!$this->supportsOwnedLocks()) {
            $statement = $this->db->query(
                'UPDATE telegram_accounts
                 SET dispatch_locked_until = :locked_until
                 WHERE id = :id
                   AND (dispatch_locked_until IS NULL OR dispatch_locked_until < UTC_TIMESTAMP())',
                ['locked_until' => $until, 'id' => $accountId]
            );
            if ($statement->rowCount() !== 1) {
                return null;
            }

            $this->legacyLocks[$token] = ['account_id' => $accountId, 'locked_until' => $until];
            return $token;
        }

        $statement = $this->db->query(
            'UPDATE telegram_accounts
             SET dispatch_locked_until = :locked_until,
                 operation_lock_type = :lock_type,
                 operation_lock_token = :lock_token
             WHERE id = :id
               AND (dispatch_locked_until IS NULL OR dispatch_locked_until < UTC_TIMESTAMP())',
            [
                'locked_until' => $until,
                'lock_type' => $type,
                'lock_token' => $token,
                'id' => $accountId,
            ]
        );

        return $statement->rowCount() === 1 ? $token : null;
    }

    private function hasUpcomingDispatch(int $accountId, int $lookaheadSeconds): bool
    {
        $threshold = gmdate('Y-m-d H:i:s', time() + max(1, $lookaheadSeconds));
        $row = $this->db->fetch(
            'SELECT sj.id
             FROM schedule_jobs sj
             INNER JOIN users u ON u.id = sj.user_id
             INNER JOIN telegram_accounts ta ON ta.id = sj.telegram_account_id
             WHERE sj.telegram_account_id = :account_id
               AND sj.status = \'active\'
               AND u.status = \'active\'
               AND (u.role = \'super_admin\' OR u.subscription_expires_at IS NULL OR u.subscription_expires_at >= UTC_TIMESTAMP())
               AND ta.is_active = 1
               AND sj.next_run_at IS NOT NULL
               AND sj.next_run_at <= :threshold
             LIMIT 1',
            ['account_id' => $accountId, 'threshold' => $threshold]
        );

        return $row !== null;
    }

    private function supportsOwnedLocks(): bool
    {
        if ($this->supportsOwnedLocks !== null) {
            return $this->supportsOwnedLocks;
        }

        $typeColumn = $this->db->fetch("SHOW COLUMNS FROM telegram_accounts LIKE 'operation_lock_type'");
        $tokenColumn = $this->db->fetch("SHOW COLUMNS FROM telegram_accounts LIKE 'operation_lock_token'");

        return $this->supportsOwnedLocks = $typeColumn !== null && $tokenColumn !== null;
    }
}
