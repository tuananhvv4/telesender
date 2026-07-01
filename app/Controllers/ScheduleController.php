<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\CronExpression;
use App\Core\Request;
use App\Models\MessageTemplate;
use App\Models\ScheduleJob;
use App\Models\TelegramAccount;
use App\Models\TelegramGroup;
use App\Services\PresetService;
use App\Services\SchedulerService;
use App\Services\TelegramService;
use DateTimeImmutable;
use DateTimeZone;
use Exception;

class ScheduleController extends Controller
{
    public function __construct(
        private readonly ScheduleJob $schedules = new ScheduleJob(),
        private readonly TelegramAccount $accounts = new TelegramAccount(),
        private readonly TelegramGroup $groups = new TelegramGroup(),
        private readonly MessageTemplate $templates = new MessageTemplate()
    ) {
    }

    public function index(Request $request): void
    {
        $userId = (int) auth()->id();
        $editSchedule = null;
        $editId = (int) $request->query('edit', 0);

        if ($editId > 0) {
            $editSchedule = $this->schedules->findForUser($editId, $userId);
        }

        $this->render('schedules/index', [
            'title' => 'Schedules',
            'schedules' => $this->schedules->listForUser($userId),
            'accounts' => $this->accounts->listForUser($userId),
            'groups' => $this->groups->listForUser($userId),
            'templates' => $this->templates->listForUser($userId),
            'editSchedule' => $editSchedule,
            'defaultTimezone' => config('app.timezone', 'Asia/Ho_Chi_Minh'),
            'schedulePresets' => (new PresetService(app()->db()))->schedulePresets(),
        ]);
    }

    public function store(Request $request): void
    {
        $data = $this->validatedData($request);
        $scheduler = new SchedulerService(app()->db(), new TelegramService(), new CronExpression());
        $nextRunAt = $scheduler->calculateNextRun(
            $data['cron_expression'],
            $data['timezone'],
            new DateTimeImmutable('now', new DateTimeZone('UTC'))
        );

        $this->schedules->create(array_merge($data, [
            'user_id' => (int) auth()->id(),
            'next_run_at' => $nextRunAt,
            'last_run_at' => null,
            'last_error' => null,
            'status' => 'active',
            'dispatch_locked_until' => null,
            'created_at' => gmdate('Y-m-d H:i:s'),
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ]));

        $this->redirectWith('/schedules', success: 'Đã tạo lịch gửi tin nhắn.');
    }

    public function update(Request $request): void
    {
        $schedule = $this->schedules->findForUser((int) $request->input('id'), (int) auth()->id());

        if ($schedule === null) {
            abort404();
        }

        $data = $this->validatedData($request);
        $scheduler = new SchedulerService(app()->db(), new TelegramService(), new CronExpression());
        $nextRunAt = $scheduler->calculateNextRun(
            $data['cron_expression'],
            $data['timezone'],
            new DateTimeImmutable('now', new DateTimeZone('UTC'))
        );

        $this->schedules->updateById((int) $schedule['id'], array_merge($data, [
            'next_run_at' => $nextRunAt,
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ]));

        $this->redirectWith('/schedules', success: 'Đã cập nhật lịch gửi.');
    }

    public function toggle(Request $request): void
    {
        $schedule = $this->schedules->findForUser((int) $request->input('id'), (int) auth()->id());

        if ($schedule === null) {
            abort404();
        }

        $newStatus = $schedule['status'] === 'active' ? 'paused' : 'active';

        $this->schedules->updateById((int) $schedule['id'], [
            'status' => $newStatus,
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ]);

        $this->redirectWith('/schedules', success: 'Đã cập nhật trạng thái schedule.');
    }

    public function delete(Request $request): void
    {
        $schedule = $this->schedules->findForUser((int) $request->input('id'), (int) auth()->id());

        if ($schedule === null) {
            abort404();
        }

        $this->schedules->deleteById((int) $schedule['id']);
        $this->redirectWith('/schedules', success: 'Đã xóa schedule.');
    }

    private function validatedData(Request $request): array
    {
        $userId = (int) auth()->id();
        $accountId = (int) $request->input('telegram_account_id');
        $groupId = (int) $request->input('telegram_group_id');
        $templateId = (int) $request->input('message_template_id');
        $timezone = trim((string) $request->input('timezone'));
        $cron = trim((string) $request->input('cron_expression'));

        if ($this->accounts->findForUser($accountId, $userId) === null) {
            abort404();
        }

        if ($this->groups->findForUser($groupId, $userId) === null) {
            abort404();
        }

        $group = $this->groups->findForUser($groupId, $userId);
        if ($this->templates->findForUser($templateId, $userId) === null) {
            abort404();
        }

        if ((int) ($group['telegram_account_id'] ?? 0) !== $accountId) {
            $this->redirectWith('/schedules', error: 'Group phải thuộc đúng Telegram account đã chọn.');
        }

        if ($timezone === '' || $cron === '') {
            $this->redirectWith('/schedules', error: 'Timezone và cron expression là bắt buộc.');
        }

        try {
            new DateTimeZone($timezone);
            (new CronExpression())->validate($cron);
        } catch (Exception $exception) {
            $this->redirectWith('/schedules', error: 'Timezone hoặc cron expression không hợp lệ.');
        }

        return [
            'telegram_account_id' => $accountId,
            'telegram_group_id' => $groupId,
            'message_template_id' => $templateId,
            'timezone' => $timezone,
            'cron_expression' => $cron,
        ];
    }
}
