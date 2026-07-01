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

class SystemController extends Controller
{
    public function cron(Request $request): void
    {
        $this->guardToken((string) $request->query('token'), (string) config('services.tokens.cron'));

        $service = new SchedulerService(app()->db(), new TelegramService(), new CronExpression());
        Response::json([
            'ok' => true,
            'executed_at' => gmdate(DATE_ATOM),
            'results' => $service->dispatchDueJobs(),
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
