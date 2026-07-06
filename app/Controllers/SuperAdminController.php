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

        $admins = array_map(fn (array $admin): array => $this->decorateAdmin($admin, $access), $result['items']);

        $this->render('admin/users', [
            'title' => 'Quản lý admin',
            'systemSummary' => $this->systemSummary(),
            'admins' => $admins,
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
            '/admin/users',
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
            $this->redirectWith('/admin/users', error: $exception->getMessage());
        }

        $internalNote = trim((string) $request->input('internal_note'));

        $this->users->updateById((int) $user['id'], [
            'max_telegram_accounts' => $maxTelegramAccounts,
            'max_schedule_jobs' => $maxScheduleJobs,
            'internal_note' => $internalNote === '' ? null : $internalNote,
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ]);

        $this->redirectWith('/admin/users', success: 'Đã cập nhật giới hạn và ghi chú nội bộ.');
    }

    public function userDetails(Request $request): void
    {
        $userId = (int) $request->query('user_id', 0);
        $user = $this->users->findAdminWithStats($userId);

        if ($user === null) {
            abort404();
        }

        $access = new UserAccessService(app()->db());
        $user = $this->decorateAdmin($user, $access);

        $recentLogs = array_map(function (array $log): array {
            return [
                'template_name' => (string) ($log['template_name'] ?? 'Không rõ mẫu'),
                'group_title' => (string) ($log['group_title'] ?? 'Không rõ nhóm'),
                'sent_at_label' => fmt_datetime((string) ($log['sent_at'] ?? '')),
                'status' => (string) ($log['status'] ?? ''),
            ];
        }, $this->logs->recentForUser($userId, 6));

        $recentAdjustments = array_map(function (array $adjustment): array {
            $deltaDays = (int) ($adjustment['delta_days'] ?? 0);

            return [
                'delta_label' => ($deltaDays > 0 ? '+' : '') . $deltaDays . ' ngày',
                'actor_name' => (string) ($adjustment['actor_name'] ?? 'Super admin'),
                'created_at_label' => fmt_datetime((string) ($adjustment['created_at'] ?? '')),
                'new_expires_at_label' => !empty($adjustment['new_expires_at'])
                    ? fmt_datetime((string) $adjustment['new_expires_at'])
                    : 'Không có',
            ];
        }, $this->adjustments->recentForTargetUser($userId, 8));

        $this->jsonSuccess('Đã tải thông tin admin.', [
            'user' => [
                'id' => (int) $user['id'],
                'name' => (string) $user['name'],
                'email' => (string) $user['email'],
                'status' => (string) ($user['status'] ?? 'inactive'),
                'subscription_state' => (string) ($user['subscription_state'] ?? 'inactive'),
                'subscription_label' => (string) ($user['subscription_label'] ?? '-'),
                'remaining_label' => $this->remainingLabel($user),
                'created_at_label' => fmt_datetime((string) ($user['created_at'] ?? '')),
                'telegram_accounts_active' => (int) ($user['telegram_accounts_active'] ?? 0),
                'telegram_accounts_total' => (int) ($user['telegram_accounts_total'] ?? 0),
                'account_limit' => $user['account_limit'],
                'account_limit_label' => (string) ($user['account_limit_label'] ?? 'Không giới hạn'),
                'groups_active' => (int) ($user['groups_active'] ?? 0),
                'groups_total' => (int) ($user['groups_total'] ?? 0),
                'schedules_active' => (int) ($user['schedules_active'] ?? 0),
                'schedules_total' => (int) ($user['schedules_total'] ?? 0),
                'schedule_limit' => $user['schedule_limit'],
                'schedule_limit_label' => (string) ($user['schedule_limit_label'] ?? 'Không giới hạn'),
                'templates_total' => (int) ($user['templates_total'] ?? 0),
                'logs_success_recent' => (int) ($user['logs_success_recent'] ?? 0),
                'logs_error_recent' => (int) ($user['logs_error_recent'] ?? 0),
                'last_dispatch_at_label' => fmt_datetime((string) ($user['last_dispatch_at'] ?? '')),
                'internal_note' => (string) ($user['internal_note'] ?? ''),
            ],
            'recent_logs' => $recentLogs,
            'recent_adjustments' => $recentAdjustments,
        ]);
    }

    public function subscriptions(Request $request): void
    {
        $searchQuery = trim((string) $request->query('q', ''));
        $perPage = pagination_per_page(15, [10, 15, 20, 30, 50, 100]);
        $result = $this->users->paginateAdminsBySubscription((int) $request->query('page', 1), $perPage, $searchQuery);
        $access = new UserAccessService(app()->db());

        $admins = array_map(fn (array $admin): array => $this->decorateAdmin($admin, $access), $result['items']);

        $this->render('admin/subscriptions', [
            'title' => 'Quản lý hạn dùng',
            'admins' => $admins,
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
            $this->redirectWith('/admin/subscriptions', error: 'Số ngày điều chỉnh phải lớn hơn 0.');
        }

        $delta = $direction === 'subtract' ? -$days : $days;
        $access = new UserAccessService(app()->db());

        try {
            $result = $access->adjustSubscription((string) ($user['subscription_expires_at'] ?? ''), $delta);
        } catch (\Throwable $exception) {
            $this->redirectWith('/admin/subscriptions', error: $exception->getMessage());
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

        $this->redirectWith('/admin/subscriptions', success: 'Đã cập nhật hạn dùng cho admin này.');
    }

    public function subscriptionDetails(Request $request): void
    {
        $userId = (int) $request->query('user_id', 0);
        $user = $this->users->findAdminWithStats($userId);

        if ($user === null) {
            abort404();
        }

        $access = new UserAccessService(app()->db());
        $user = $this->decorateAdmin($user, $access);

        $adjustments = array_map(function (array $adjustment): array {
            $deltaDays = (int) ($adjustment['delta_days'] ?? 0);

            return [
                'delta_label' => ($deltaDays > 0 ? '+' : '') . $deltaDays . ' ngày',
                'actor_name' => (string) ($adjustment['actor_name'] ?? 'Super admin'),
                'created_at_label' => fmt_datetime((string) ($adjustment['created_at'] ?? '')),
                'note' => (string) ($adjustment['note'] ?? ''),
                'previous_expires_at_label' => !empty($adjustment['previous_expires_at'])
                    ? fmt_datetime((string) $adjustment['previous_expires_at'])
                    : 'Không có',
                'new_expires_at_label' => !empty($adjustment['new_expires_at'])
                    ? fmt_datetime((string) $adjustment['new_expires_at'])
                    : 'Không có',
            ];
        }, $this->adjustments->recentForTargetUser($userId, 20));

        $this->jsonSuccess('Đã tải thông tin hạn dùng.', [
            'user' => [
                'id' => (int) $user['id'],
                'name' => (string) $user['name'],
                'email' => (string) $user['email'],
                'subscription_state' => (string) ($user['subscription_state'] ?? 'inactive'),
                'subscription_label' => (string) ($user['subscription_label'] ?? '-'),
                'remaining_label' => $this->remainingLabel($user),
                'expires_at_label' => !empty($user['subscription_expires_at'])
                    ? fmt_datetime((string) $user['subscription_expires_at'])
                    : 'Không giới hạn',
            ],
            'adjustments' => $adjustments,
        ]);
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

    private function remainingLabel(array $user): string
    {
        if (($user['subscription_state'] ?? '') === 'unlimited') {
            return 'Không giới hạn';
        }

        $days = $user['days_remaining'] ?? null;

        if ($days === null) {
            return '-';
        }

        if ((int) $days <= 0) {
            return 'Đã hết hạn';
        }

        return (int) $days . ' ngày';
    }
}
