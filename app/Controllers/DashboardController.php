<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Models\DispatchLog;
use App\Models\MessageLabel;
use App\Models\MessageTemplate;
use App\Models\ScheduleJob;
use App\Models\TelegramAccount;
use App\Models\TelegramGroup;
use App\Services\MigrationService;

class DashboardController extends Controller
{
    public function index(Request $request): void
    {
        $userId = (int) auth()->id();
        $accounts = new TelegramAccount();
        $groups = new TelegramGroup();
        $labels = new MessageLabel();
        $templates = new MessageTemplate();
        $schedules = new ScheduleJob();
        $logs = new DispatchLog();
        $isSuperAdmin = user_is_super_admin();

        $data = [
            'title' => 'Tổng quan',
            'isSuperAdmin' => $isSuperAdmin,
        ];

        if ($isSuperAdmin) {
            $data['stats'] = $this->superAdminStats();
            $data['systemTokens'] = [
                'cron' => (string) config('services.tokens.cron', ''),
                'migrate' => (string) config('services.tokens.migrate', ''),
            ];
            $data['systemEndpoints'] = [
                'cron' => url('/cron/run?token=' . rawurlencode((string) config('services.tokens.cron', ''))),
                'migrate_base' => url('/system/migrate?token=' . rawurlencode((string) config('services.tokens.migrate', '')) . '&version='),
            ];
            $data['migrationReport'] = (new MigrationService(app()->db()->pdo()))->report();
            $data['recentLogs'] = $logs->recentForUser($userId, 6);
            $this->render('dashboard/index', $data);
            return;
        }

        $this->render('dashboard/index', array_merge($data, [
            'stats' => [
                'accounts' => $accounts->count('user_id = :user_id', ['user_id' => $userId]),
                'groups' => $groups->count('user_id = :user_id', ['user_id' => $userId]),
                'labels' => $labels->count('user_id = :user_id', ['user_id' => $userId]),
                'templates' => $templates->count('user_id = :user_id', ['user_id' => $userId]),
                'schedules' => $schedules->count('user_id = :user_id', ['user_id' => $userId]),
            ],
            'recentLogs' => $logs->recentForUser($userId, 8),
            'nextSchedules' => $schedules->listForUser($userId),
        ]));
    }

    private function superAdminStats(): array
    {
        $row = app()->db()->fetch(
            "SELECT
                (SELECT COUNT(*) FROM users WHERE role = 'admin') AS admins_total,
                (SELECT COUNT(*) FROM users WHERE role = 'admin' AND status = 'active' AND (subscription_expires_at IS NULL OR subscription_expires_at >= UTC_TIMESTAMP())) AS admins_active,
                (SELECT COUNT(*) FROM users WHERE role = 'admin' AND subscription_expires_at IS NOT NULL AND subscription_expires_at < UTC_TIMESTAMP()) AS admins_expired,
                (SELECT COUNT(*) FROM users WHERE role = 'admin' AND status <> 'active') AS admins_inactive,
                (SELECT COUNT(*) FROM telegram_accounts ta INNER JOIN users u ON u.id = ta.user_id WHERE u.role = 'admin') AS accounts,
                (SELECT COUNT(*) FROM telegram_groups tg INNER JOIN users u ON u.id = tg.user_id WHERE u.role = 'admin') AS groups,
                (SELECT COUNT(*) FROM message_templates mt INNER JOIN users u ON u.id = mt.user_id WHERE u.role = 'admin') AS templates,
                (SELECT COUNT(*) FROM schedule_jobs sj INNER JOIN users u ON u.id = sj.user_id WHERE u.role = 'admin') AS schedules
             LIMIT 1"
        ) ?? [];

        return [
            'admins_total' => (int) ($row['admins_total'] ?? 0),
            'admins_active' => (int) ($row['admins_active'] ?? 0),
            'admins_expired' => (int) ($row['admins_expired'] ?? 0),
            'admins_inactive' => (int) ($row['admins_inactive'] ?? 0),
            'accounts' => (int) ($row['accounts'] ?? 0),
            'groups' => (int) ($row['groups'] ?? 0),
            'templates' => (int) ($row['templates'] ?? 0),
            'schedules' => (int) ($row['schedules'] ?? 0),
        ];
    }
}
