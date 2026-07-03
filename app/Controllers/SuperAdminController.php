<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Models\DispatchLog;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\UserSubscriptionAdjustment;
use App\Services\UserAccessService;

class SuperAdminController extends Controller
{
    public function __construct(
        private readonly User $users = new User(),
        private readonly DispatchLog $logs = new DispatchLog(),
        private readonly SystemSetting $settings = new SystemSetting(),
        private readonly UserSubscriptionAdjustment $adjustments = new UserSubscriptionAdjustment()
    ) {
    }

    public function users(Request $request): void
    {
        $searchQuery = trim((string) $request->query('q', ''));
        $perPage = pagination_per_page(15, [10, 15, 20, 30, 50, 100]);
        $result = $this->users->paginateAdmins((int) $request->query('page', 1), $perPage, $searchQuery);
        $access = new UserAccessService(app()->db());
        $viewId = (int) $request->query('view', 0);
        $viewUser = $viewId > 0 ? $this->users->findAdminWithStats($viewId) : null;

        $admins = array_map(fn (array $admin): array => $this->decorateAdmin($admin, $access), $result['items']);
        $viewUser = $viewUser !== null ? $this->decorateAdmin($viewUser, $access) : null;

        $recentLogs = $viewUser !== null ? $this->logs->recentForUser((int) $viewUser['id'], 6) : [];
        $recentAdjustments = $viewUser !== null ? $this->adjustments->recentForTargetUser((int) $viewUser['id'], 8) : [];

        $this->render('admin/users', [
            'title' => 'Quản lý admin',
            'systemSummary' => $this->systemSummary(),
            'admins' => $admins,
            'viewUser' => $viewUser,
            'recentLogs' => $recentLogs,
            'recentAdjustments' => $recentAdjustments,
            'pagination' => $result['pagination'],
            'searchQuery' => $searchQuery,
        ]);
    }

    public function storeUser(Request $request): void
    {
        $name = trim((string) $request->input('name'));
        $email = mb_strtolower(trim((string) $request->input('email')));
        $password = (string) $request->input('password');
        $initialDays = (int) $request->input('initial_days', 30);
        $status = $request->input('is_active') ? 'active' : 'inactive';
        $access = new UserAccessService(app()->db());
        $maxTelegramAccounts = null;
        $maxScheduleJobs = null;
        $internalNote = trim((string) $request->input('internal_note'));

        if ($name === '' || $email === '' || $password === '') {
            $this->redirectWith('/admin/users', error: 'Tên, email và mật khẩu là bắt buộc.');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->redirectWith('/admin/users', error: 'Email không đúng định dạng.');
        }

        if ($initialDays <= 0) {
            $this->redirectWith('/admin/users', error: 'Số ngày sử dụng ban đầu phải lớn hơn 0.');
        }

        if ($this->users->findByEmail($email) !== null) {
            $this->redirectWith('/admin/users', error: 'Email này đã tồn tại.');
        }

        try {
            $maxTelegramAccounts = $access->normalizeLimit($request->input('max_telegram_accounts'));
            $maxScheduleJobs = $access->normalizeLimit($request->input('max_schedule_jobs'));
        } catch (\Throwable $exception) {
            $this->redirectWith('/admin/users', error: $exception->getMessage());
        }

        $this->users->create([
            'name' => $name,
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'role' => 'admin',
            'status' => $status,
            'subscription_expires_at' => $access->defaultSubscriptionUntilFromDays($initialDays),
            'max_telegram_accounts' => $maxTelegramAccounts,
            'max_schedule_jobs' => $maxScheduleJobs,
            'internal_note' => $internalNote === '' ? null : $internalNote,
            'created_at' => gmdate('Y-m-d H:i:s'),
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ]);

        $this->redirectWith('/admin/users', success: 'Đã tạo admin con mới thành công.');
    }

    public function toggleUserStatus(Request $request): void
    {
        $user = $this->adminOrFail((int) $request->input('user_id'));
        $isActive = (string) ($user['status'] ?? 'inactive') === 'active';

        $this->users->updateById((int) $user['id'], [
            'status' => $isActive ? 'inactive' : 'active',
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ]);

        $this->redirectWith(
            '/admin/users?view=' . $user['id'],
            success: $isActive ? 'Đã khóa admin này.' : 'Đã mở khóa admin này.'
        );
    }

    public function updateLimits(Request $request): void
    {
        $user = $this->adminOrFail((int) $request->input('user_id'));
        $access = new UserAccessService(app()->db());

        try {
            $maxTelegramAccounts = $access->normalizeLimit($request->input('max_telegram_accounts'));
            $maxScheduleJobs = $access->normalizeLimit($request->input('max_schedule_jobs'));
        } catch (\Throwable $exception) {
            $this->redirectWith('/admin/users?view=' . $user['id'], error: $exception->getMessage());
        }

        $internalNote = trim((string) $request->input('internal_note'));

        $this->users->updateById((int) $user['id'], [
            'max_telegram_accounts' => $maxTelegramAccounts,
            'max_schedule_jobs' => $maxScheduleJobs,
            'internal_note' => $internalNote === '' ? null : $internalNote,
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ]);

        $this->redirectWith('/admin/users?view=' . $user['id'], success: 'Đã cập nhật giới hạn và ghi chú nội bộ.');
    }

    public function subscriptions(Request $request): void
    {
        $searchQuery = trim((string) $request->query('q', ''));
        $perPage = pagination_per_page(15, [10, 15, 20, 30, 50, 100]);
        $result = $this->users->paginateAdminsBySubscription((int) $request->query('page', 1), $perPage, $searchQuery);
        $access = new UserAccessService(app()->db());
        $focusUserId = (int) $request->query('user', 0);
        $focusUser = $focusUserId > 0 ? $this->users->findAdminWithStats($focusUserId) : null;

        $admins = array_map(fn (array $admin): array => $this->decorateAdmin($admin, $access), $result['items']);
        $focusUser = $focusUser !== null ? $this->decorateAdmin($focusUser, $access) : null;

        $this->render('admin/subscriptions', [
            'title' => 'Quản lý hạn dùng',
            'admins' => $admins,
            'focusUser' => $focusUser,
            'adjustmentLogs' => $focusUser !== null ? $this->adjustments->recentForTargetUser((int) $focusUser['id'], 20) : [],
            'pagination' => $result['pagination'],
            'searchQuery' => $searchQuery,
        ]);
    }

    public function adjustSubscription(Request $request): void
    {
        $user = $this->adminOrFail((int) $request->input('user_id'));
        $direction = (string) $request->input('direction', 'add');
        $days = (int) $request->input('days', 0);
        $note = trim((string) $request->input('note'));

        if ($days <= 0) {
            $this->redirectWith('/admin/subscriptions?user=' . $user['id'], error: 'Số ngày điều chỉnh phải lớn hơn 0.');
        }

        $delta = $direction === 'subtract' ? -$days : $days;
        $access = new UserAccessService(app()->db());

        try {
            $result = $access->adjustSubscription((string) ($user['subscription_expires_at'] ?? ''), $delta);
        } catch (\Throwable $exception) {
            $this->redirectWith('/admin/subscriptions?user=' . $user['id'], error: $exception->getMessage());
        }

        app()->db()->transaction(function () use ($user, $delta, $note, $result): void {
            $this->users->updateById((int) $user['id'], [
                'subscription_expires_at' => $result['next'],
                'updated_at' => gmdate('Y-m-d H:i:s'),
            ]);

            $this->adjustments->create([
                'target_user_id' => (int) $user['id'],
                'actor_user_id' => (int) auth()->id(),
                'delta_days' => $delta,
                'previous_expires_at' => $result['previous'],
                'new_expires_at' => $result['next'],
                'note' => $note === '' ? null : $note,
                'created_at' => gmdate('Y-m-d H:i:s'),
                'updated_at' => gmdate('Y-m-d H:i:s'),
            ]);
        });

        $this->redirectWith('/admin/subscriptions?user=' . $user['id'], success: 'Đã cập nhật hạn dùng cho admin này.');
    }

    public function settings(Request $request): void
    {
        $this->render('admin/settings', [
            'title' => 'Cấu hình hệ thống',
            'settingsMap' => $this->settings->resolvedMap(),
        ]);
    }

    public function updateSettings(Request $request): void
    {
        $payload = [
            'expired_notice_title' => trim((string) $request->input('expired_notice_title')),
            'expired_notice_message' => trim((string) $request->input('expired_notice_message')),
            'support_contact_name' => trim((string) $request->input('support_contact_name')),
            'support_contact_value' => trim((string) $request->input('support_contact_value')),
            'support_contact_extra' => trim((string) $request->input('support_contact_extra')),
            'footer_text' => trim((string) $request->input('footer_text')),
        ];

        if ($payload['expired_notice_title'] === '' || $payload['expired_notice_message'] === '') {
            $this->redirectWith('/admin/settings', error: 'Tiêu đề và nội dung hết hạn là bắt buộc.');
        }

        $this->settings->saveMany($payload);

        $this->redirectWith('/admin/settings', success: 'Đã lưu cấu hình hệ thống.');
    }

    private function adminOrFail(int $userId): array
    {
        $user = $this->users->find($userId);

        if ($user === null || (string) ($user['role'] ?? 'admin') !== 'admin') {
            abort404();
        }

        return $user;
    }

    private function decorateAdmin(array $admin, UserAccessService $access): array
    {
        $admin['subscription_state'] = $access->subscriptionState($admin);
        $admin['subscription_label'] = $access->subscriptionStateLabel($admin);
        $admin['days_remaining'] = $access->daysRemaining($admin);
        $admin['account_limit'] = $access->accountLimit($admin);
        $admin['account_limit_label'] = $access->limitLabel($admin['account_limit']);
        $admin['schedule_limit'] = $access->scheduleLimit($admin);
        $admin['schedule_limit_label'] = $access->limitLabel($admin['schedule_limit']);

        return $admin;
    }

    private function systemSummary(): array
    {
        $row = app()->db()->fetch(
            "SELECT
                (SELECT COUNT(*) FROM users u WHERE u.role = 'admin') AS admins_total,
                (SELECT COUNT(*) FROM users u WHERE u.role = 'admin' AND u.status = 'active') AS admins_active,
                (SELECT COUNT(*) FROM users u WHERE u.role = 'admin' AND u.status <> 'active') AS admins_inactive,
                (SELECT COUNT(*) FROM users u WHERE u.role = 'admin' AND u.status = 'active' AND u.subscription_expires_at IS NOT NULL AND u.subscription_expires_at < UTC_TIMESTAMP()) AS admins_expired,
                (SELECT COUNT(*) FROM telegram_accounts ta INNER JOIN users u ON u.id = ta.user_id WHERE u.role = 'admin') AS telegram_accounts_total,
                (SELECT COUNT(*) FROM telegram_accounts ta INNER JOIN users u ON u.id = ta.user_id WHERE u.role = 'admin' AND ta.is_active = 1) AS telegram_accounts_active,
                (SELECT COUNT(*) FROM telegram_groups tg INNER JOIN users u ON u.id = tg.user_id WHERE u.role = 'admin') AS groups_total,
                (SELECT COUNT(*) FROM schedule_jobs sj INNER JOIN users u ON u.id = sj.user_id WHERE u.role = 'admin') AS schedules_total,
                (SELECT COUNT(*) FROM schedule_jobs sj INNER JOIN users u ON u.id = sj.user_id WHERE u.role = 'admin' AND sj.status = 'active') AS schedules_active,
                (SELECT COUNT(*) FROM message_templates mt INNER JOIN users u ON u.id = mt.user_id WHERE u.role = 'admin') AS templates_total,
                (SELECT COUNT(*) FROM dispatch_logs dl INNER JOIN users u ON u.id = dl.user_id WHERE u.role = 'admin') AS logs_total,
                (SELECT COUNT(*) FROM dispatch_logs dl INNER JOIN users u ON u.id = dl.user_id WHERE u.role = 'admin' AND dl.sent_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 DAY)) AS logs_last_24h
             LIMIT 1"
        ) ?? [];

        return [
            'admins_total' => (int) ($row['admins_total'] ?? 0),
            'admins_active' => (int) ($row['admins_active'] ?? 0),
            'admins_inactive' => (int) ($row['admins_inactive'] ?? 0),
            'admins_expired' => (int) ($row['admins_expired'] ?? 0),
            'telegram_accounts_total' => (int) ($row['telegram_accounts_total'] ?? 0),
            'telegram_accounts_active' => (int) ($row['telegram_accounts_active'] ?? 0),
            'groups_total' => (int) ($row['groups_total'] ?? 0),
            'schedules_total' => (int) ($row['schedules_total'] ?? 0),
            'schedules_active' => (int) ($row['schedules_active'] ?? 0),
            'templates_total' => (int) ($row['templates_total'] ?? 0),
            'logs_total' => (int) ($row['logs_total'] ?? 0),
            'logs_last_24h' => (int) ($row['logs_last_24h'] ?? 0),
        ];
    }
}
