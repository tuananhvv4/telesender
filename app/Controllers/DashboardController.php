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

        $this->render('dashboard/index', [
            'title' => 'Dashboard',
            'stats' => [
                'accounts' => $accounts->count('user_id = :user_id', ['user_id' => $userId]),
                'groups' => $groups->count('user_id = :user_id', ['user_id' => $userId]),
                'labels' => $labels->count('user_id = :user_id', ['user_id' => $userId]),
                'templates' => $templates->count('user_id = :user_id', ['user_id' => $userId]),
                'schedules' => $schedules->count('user_id = :user_id', ['user_id' => $userId]),
            ],
            'recentLogs' => $logs->recentForUser($userId, 8),
            'nextSchedules' => $schedules->listForUser($userId),
        ]);
    }
}
