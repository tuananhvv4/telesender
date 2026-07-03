<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;

class UserAccessService
{
    public function __construct(private readonly Database $db)
    {
    }

    public function syncSuperAdminRole(): void
    {
        $email = $this->superAdminEmail();

        if ($email === '') {
            return;
        }

        $this->db->update('users', [
            'role' => 'super_admin',
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ], 'email = :email', ['email' => $email]);
    }

    public function superAdminEmail(): string
    {
        return mb_strtolower(trim((string) config('app.super_admin_email', '')));
    }

    public function isSuperAdmin(array $user): bool
    {
        return (string) ($user['role'] ?? 'admin') === 'super_admin';
    }

    public function isActive(array $user): bool
    {
        return (string) ($user['status'] ?? 'inactive') === 'active';
    }

    public function isExpired(array $user, ?DateTimeImmutable $now = null): bool
    {
        if ($this->isSuperAdmin($user)) {
            return false;
        }

        $expiresAt = $this->expiresAt($user);
        if ($expiresAt === null) {
            return false;
        }

        $now ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));
        return $expiresAt < $now;
    }

    public function canAccessApp(array $user, ?DateTimeImmutable $now = null): bool
    {
        if (!$this->isActive($user)) {
            return false;
        }

        return !$this->isExpired($user, $now);
    }

    public function daysRemaining(array $user, ?DateTimeImmutable $now = null): ?int
    {
        if ($this->isSuperAdmin($user)) {
            return null;
        }

        $expiresAt = $this->expiresAt($user);
        if ($expiresAt === null) {
            return null;
        }

        $now ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $diffSeconds = $expiresAt->getTimestamp() - $now->getTimestamp();

        if ($diffSeconds <= 0) {
            return 0;
        }

        return (int) ceil($diffSeconds / 86400);
    }

    public function subscriptionState(array $user, ?DateTimeImmutable $now = null): string
    {
        if ($this->isSuperAdmin($user)) {
            return 'super_admin';
        }

        if (!$this->isActive($user)) {
            return 'inactive';
        }

        $expiresAt = $this->expiresAt($user);
        if ($expiresAt === null) {
            return 'unlimited';
        }

        return $this->isExpired($user, $now) ? 'expired' : 'active';
    }

    public function subscriptionStateLabel(array $user, ?DateTimeImmutable $now = null): string
    {
        return match ($this->subscriptionState($user, $now)) {
            'super_admin' => 'Super admin',
            'inactive' => 'Đã khóa',
            'unlimited' => 'Không giới hạn',
            'expired' => 'Đã hết hạn',
            default => 'Còn hạn',
        };
    }

    public function canShowRegisterLink(): bool
    {
        if ((bool) config('app.allow_registration', false)) {
            return true;
        }

        return $this->bootstrapPending();
    }

    public function bootstrapPending(): bool
    {
        $email = $this->superAdminEmail();

        if ($email === '') {
            return false;
        }

        $superAdmin = $this->db->fetch(
            'SELECT id FROM users WHERE role = :role LIMIT 1',
            ['role' => 'super_admin']
        );

        if ($superAdmin !== null) {
            return false;
        }

        return true;
    }

    public function canSelfRegister(string $email = ''): bool
    {
        if ((bool) config('app.allow_registration', false)) {
            return true;
        }

        if (!$this->bootstrapPending()) {
            return false;
        }

        $email = mb_strtolower(trim($email));
        return $email !== '' && $email === $this->superAdminEmail();
    }

    public function roleForNewRegistration(string $email): string
    {
        if ($this->canSelfRegister($email) && mb_strtolower(trim($email)) === $this->superAdminEmail()) {
            return 'super_admin';
        }

        return 'admin';
    }

    public function defaultSubscriptionUntilFromDays(int $days, ?DateTimeImmutable $now = null): ?string
    {
        if ($days <= 0) {
            return null;
        }

        $now ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));
        return $now->add(new DateInterval('P' . $days . 'D'))->format('Y-m-d H:i:s');
    }

    public function adjustSubscription(?string $currentExpiresAt, int $deltaDays, ?DateTimeImmutable $now = null): array
    {
        $now ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $current = $this->nullableUtcDate($currentExpiresAt);

        if ($deltaDays < 0 && $current === null) {
            throw new \RuntimeException('Admin này đang ở trạng thái không giới hạn, không thể trừ ngày trực tiếp. Hãy gia hạn dương trước hoặc đặt hạn cụ thể.');
        }

        if ($deltaDays >= 0) {
            $base = $current !== null && $current > $now ? $current : $now;
            $next = $base->add(new DateInterval('P' . $deltaDays . 'D'));
        } else {
            $next = $current->sub(new DateInterval('P' . abs($deltaDays) . 'D'));
        }

        return [
            'previous' => $current?->format('Y-m-d H:i:s'),
            'next' => $next->format('Y-m-d H:i:s'),
        ];
    }

    public function expiresAt(array $user): ?DateTimeImmutable
    {
        return $this->nullableUtcDate((string) ($user['subscription_expires_at'] ?? ''));
    }

    public function accountLimit(array $user): ?int
    {
        if ($this->isSuperAdmin($user)) {
            return null;
        }

        return $this->nullableLimit($user['max_telegram_accounts'] ?? null);
    }

    public function scheduleLimit(array $user): ?int
    {
        if ($this->isSuperAdmin($user)) {
            return null;
        }

        return $this->nullableLimit($user['max_schedule_jobs'] ?? null);
    }

    public function limitLabel(?int $limit): string
    {
        return $limit === null ? 'Không giới hạn' : (string) $limit;
    }

    public function limitReached(?int $limit, int $currentCount): bool
    {
        return $limit !== null && $currentCount >= $limit;
    }

    public function normalizeLimit(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        $stringValue = trim((string) $value);
        if ($stringValue === '') {
            return null;
        }

        $limit = (int) $stringValue;

        if ($limit < 0) {
            throw new \RuntimeException('Giới hạn không được nhỏ hơn 0.');
        }

        return $limit;
    }

    public function nullableUtcDate(?string $value): ?DateTimeImmutable
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        return new DateTimeImmutable($value, new DateTimeZone('UTC'));
    }

    private function nullableLimit(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return max(0, (int) $value);
    }
}
