<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\CronExpression;
use App\Core\Request;
use App\Core\Response;
use App\Models\MessageTemplate;
use App\Models\ScheduleJob;
use App\Models\TelegramAccount;
use App\Models\TelegramGroup;
use App\Services\PresetService;
use App\Services\ScheduleBuilderService;
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
        $scheduler = new SchedulerService(app()->db(), new TelegramService(), new CronExpression());
        $builder = new ScheduleBuilderService(new CronExpression());
        $schedules = $this->schedules->listForUser($userId);
        $scheduleAnalyses = [];
        $scheduleSummaries = [];
        $accountScheduleAnalyses = [];

        if ($editId > 0) {
            $editSchedule = $this->schedules->findForUser($editId, $userId);
        }

        foreach ($schedules as $schedule) {
            $scheduleAnalyses[(int) $schedule['id']] = $scheduler->analyzeScheduleRisk(
                (string) $schedule['cron_expression'],
                (string) $schedule['timezone']
            );
            $scheduleSummaries[(int) $schedule['id']] = $builder->summaryFromSchedule($schedule);
            $accountId = (int) ($schedule['telegram_account_id'] ?? 0);

            if ($accountId > 0) {
                $accountScheduleAnalyses[$accountId]['account_id'] = $accountId;
                $accountScheduleAnalyses[$accountId]['account_name'] = (string) ($schedule['account_name'] ?? ('Account #' . $accountId));
                $accountScheduleAnalyses[$accountId]['schedules'][] = $schedule;
            }
        }

        foreach ($accountScheduleAnalyses as $accountId => $accountData) {
            $accountScheduleAnalyses[$accountId] = array_merge(
                [
                    'account_id' => $accountId,
                    'account_name' => (string) ($accountData['account_name'] ?? ('Account #' . $accountId)),
                ],
                $scheduler->analyzeAccountScheduleRisk((array) ($accountData['schedules'] ?? []))
            );
        }

        $this->render('schedules/index', [
            'title' => 'Schedules',
            'schedules' => $schedules,
            'accounts' => $this->accounts->listForUser($userId),
            'groups' => $this->groups->listForUser($userId),
            'templates' => $this->templates->listForUser($userId),
            'editSchedule' => $editSchedule,
            'defaultTimezone' => config('app.timezone', 'Asia/Ho_Chi_Minh'),
            'schedulePresets' => (new PresetService(app()->db()))->schedulePresets(),
            'scheduleAnalyses' => $scheduleAnalyses,
            'scheduleSummaries' => $scheduleSummaries,
            'accountScheduleAnalyses' => array_values($accountScheduleAnalyses),
            'safetyRules' => config('safety'),
            'scheduleModes' => $builder->modeOptions(),
            'formScheduleState' => $builder->formDataFromSchedule($editSchedule, (string) config('app.timezone', 'Asia/Ho_Chi_Minh')),
        ]);
    }

    public function preview(Request $request): void
    {
        $timezone = trim((string) $request->query('timezone', (string) config('app.timezone', 'Asia/Ho_Chi_Minh')));

        try {
            new DateTimeZone($timezone);

            $builder = new ScheduleBuilderService(new CronExpression());
            $preview = $builder->preview($request->all(), $timezone);
            $risk = (new SchedulerService(app()->db(), new TelegramService(), new CronExpression()))
                ->analyzeScheduleRisk($preview['cron_expression'], $timezone);

            Response::json([
                'ok' => true,
                'cron_expression' => $preview['cron_expression'],
                'summary' => $preview['summary'],
                'next_runs' => $preview['next_runs'],
                'risk' => $risk,
            ]);
        } catch (\Throwable $exception) {
            Response::json([
                'ok' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }
    }

    public function store(Request $request): void
    {
        $scheduler = new SchedulerService(app()->db(), new TelegramService(), new CronExpression());
        $data = $this->validatedData($request, $scheduler);
        $analysis = $scheduler->analyzeScheduleRisk($data['cron_expression'], $data['timezone']);
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

        $message = $analysis['risk'] === 'high'
            ? 'Đã tạo lịch gửi. Lưu ý lịch này khá dày, hệ thống sẽ tự giãn cách và giới hạn theo account.'
            : 'Đã tạo lịch gửi tin nhắn.';

        $this->redirectWith('/schedules', success: $message);
    }

    public function update(Request $request): void
    {
        $schedule = $this->schedules->findForUser((int) $request->input('id'), (int) auth()->id());

        if ($schedule === null) {
            abort404();
        }

        $scheduler = new SchedulerService(app()->db(), new TelegramService(), new CronExpression());
        $data = $this->validatedData($request, $scheduler);
        $analysis = $scheduler->analyzeScheduleRisk($data['cron_expression'], $data['timezone']);
        $nextRunAt = $scheduler->calculateNextRun(
            $data['cron_expression'],
            $data['timezone'],
            new DateTimeImmutable('now', new DateTimeZone('UTC'))
        );

        $this->schedules->updateById((int) $schedule['id'], array_merge($data, [
            'next_run_at' => $nextRunAt,
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ]));

        $message = $analysis['risk'] === 'high'
            ? 'Đã cập nhật lịch gửi. Lưu ý lịch này khá dày, hệ thống sẽ tự giãn cách và giới hạn theo account.'
            : 'Đã cập nhật lịch gửi.';

        $this->redirectWith('/schedules', success: $message);
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

    public function sendNow(Request $request): void
    {
        $schedule = $this->schedules->findForUser((int) $request->input('id'), (int) auth()->id());

        if ($schedule === null) {
            abort404();
        }

        $scheduler = new SchedulerService(app()->db(), new TelegramService(), new CronExpression());

        try {
            $result = $scheduler->dispatchScheduleNow((int) $schedule['id'], (int) auth()->id());
        } catch (Exception $exception) {
            $this->redirectWith('/schedules', error: $exception->getMessage());
        }

        if (($result['status'] ?? '') === 'success') {
            $this->redirectWith('/schedules', success: 'Đã gửi ngay schedule này thành công.');
        }

        if (($result['status'] ?? '') === 'guarded') {
            $this->redirectWith('/schedules', error: (string) ($result['error'] ?? 'Schedule đang bị chặn tạm thời bởi cơ chế an toàn.'));
        }

        if (($result['status'] ?? '') === 'locked') {
            $this->redirectWith('/schedules', error: (string) ($result['error'] ?? 'Schedule đang được xử lý bởi tiến trình khác.'));
        }

        $this->redirectWith('/schedules', error: (string) ($result['error'] ?? 'Gửi ngay thất bại, vui lòng kiểm tra lại account hoặc Telegram response.'));
    }

    private function validatedData(Request $request, ?SchedulerService $scheduler = null): array
    {
        $userId = (int) auth()->id();
        $accountId = (int) $request->input('telegram_account_id');
        $groupId = (int) $request->input('telegram_group_id');
        $templateId = (int) $request->input('message_template_id');
        $timezone = trim((string) $request->input('timezone'));

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

        if ($timezone === '') {
            $this->redirectWith('/schedules', error: 'Timezone là bắt buộc.');
        }

        try {
            new DateTimeZone($timezone);
        } catch (Exception $exception) {
            $this->redirectWith('/schedules', error: 'Timezone không hợp lệ.');
        }

        try {
            $built = (new ScheduleBuilderService(new CronExpression()))->buildFromPayload($request->all());
        } catch (Exception $exception) {
            $this->redirectWith('/schedules', error: $exception->getMessage());
        }

        $scheduler ??= new SchedulerService(app()->db(), new TelegramService(), new CronExpression());
        $analysis = $scheduler->analyzeScheduleRisk($built['cron_expression'], $timezone);

        if ($analysis['risk'] === 'blocked') {
            $this->redirectWith('/schedules', error: $analysis['message']);
        }

        return [
            'telegram_account_id' => $accountId,
            'telegram_group_id' => $groupId,
            'message_template_id' => $templateId,
            'timezone' => $timezone,
            'cron_expression' => $built['cron_expression'],
            'schedule_type' => $built['schedule_type'],
            'schedule_config_json' => $built['schedule_config_json'],
        ];
    }
}
