<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\CronExpression;
use App\Core\Request;
use App\Core\Response;
use App\Services\MigrationService;
use App\Services\SchedulerService;
use App\Services\TelegramService;
use App\Services\AccountSafetyPolicyService;
use App\Services\NotificationService;
use App\Services\TelegramAccountLockService;
use App\Services\TelegramInboxSyncService;
use App\Services\TelegramMessageNormalizer;

class SystemController extends Controller
{
    public function cron(Request $request): void
    {
        $this->guardToken((string) $request->query('token'), (string) config('services.tokens.cron'));

        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }

        $service = new SchedulerService(app()->db(), new TelegramService(), new CronExpression());
        $results = $service->dispatchDueJobs();
        $policy = new AccountSafetyPolicyService(app()->db(), new CronExpression());
        $policy->cleanupExpired();
        (new NotificationService(app()->db()))->cleanupExpired($policy->auditRetentionDays());
        Response::json([
            'ok' => true,
            'executed_at' => gmdate(DATE_ATOM),
            'results' => $results,
        ]);
    }

    public function migrate(Request $request): void
    {
        $this->guardToken((string) $request->query('token'), (string) config('services.tokens.migrate'));
        $version = $request->query('version');

        $service = new MigrationService(app()->db()->pdo());
        Response::json([
            'ok' => true,
            'executed_at' => gmdate(DATE_ATOM),
            'migration' => $service->migrate($version ? (string) $version : null),
        ]);
    }

    public function inboxCron(Request $request): void
    {
        $this->guardToken((string) $request->query('token'), (string) config('services.tokens.cron'));

        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }

        $service = new TelegramInboxSyncService(
            app()->db(),
            new TelegramService(),
            new TelegramMessageNormalizer(),
            new TelegramAccountLockService(app()->db())
        );

        Response::json([
            'ok' => true,
            'executed_at' => gmdate(DATE_ATOM),
            'results' => $service->run(),
        ]);
    }

    public function health(Request $request): void
    {
        Response::json([
            'ok' => true,
            'app' => config('app.name'),
            'time' => gmdate(DATE_ATOM),
        ]);
    }

    private function guardToken(string $provided, string $expected): void
    {
        if ($expected === '' || !hash_equals($expected, $provided)) {
            Response::json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }
    }
}
