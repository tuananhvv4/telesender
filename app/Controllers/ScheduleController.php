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
        $scheduler = new SchedulerService(app()->db(), new TelegramService(), new CronExpression());
        $builder = new ScheduleBuilderService(new CronExpression());
        $searchQuery = trim((string) $request->query('q', ''));
        $selectedAccountId = (int) $request->query('telegram_account_id', 0);
        $selectedTemplateId = (int) $request->query('message_template_id', 0);
        $selectedStatus = trim((string) $request->query('status', ''));
        $filters = [
            'query' => $searchQuery,
            'telegram_account_id' => $selectedAccountId,
            'message_template_id' => $selectedTemplateId,
            'status' => $selectedStatus,
        ];
        $allSchedules = $this->schedules->listForUser($userId, $filters);
        $pageResult = $this->schedules->paginateForUser(
            $userId,
            (int) $request->query('page', 1),
            pagination_per_page(15, [10, 15, 20, 30, 50]),
            $filters
        );
        $schedules = $pageResult['items'];
        $scheduleAnalyses = [];
        $scheduleSummaries = [];
        $scheduleManualGuards = [];
        $accountScheduleAnalyses = [];
        $defaultTimezone = (string) config('app.timezone', 'Asia/Ho_Chi_Minh');

        foreach ($schedules as $schedule) {
            $scheduleAnalyses[(int) $schedule['id']] = $scheduler->analyzeScheduleRisk(
                (string) $schedule['cron_expression'],
                (string) $schedule['timezone'],
                max(1, (int) ($schedule['group_count'] ?? 1)),
                $schedule
            );
            $scheduleSummaries[(int) $schedule['id']] = $builder->summaryFromSchedule($schedule);
            $scheduleManualGuards[(int) $schedule['id']] = $scheduler->explainManualDispatchGuard($schedule);
            $scheduleFormStates[(int) $schedule['id']] = $builder->formDataFromSchedule($schedule, $defaultTimezone);
        }

        foreach ($allSchedules as $schedule) {
            $accountId = (int) ($schedule['telegram_account_id'] ?? 0);

            if ($accountId > 0) {
                $accountScheduleAnalyses[$accountId]['account_id'] = $accountId;
                $accountScheduleAnalyses[$accountId]['account_name'] = (string) ($schedule['account_name'] ?? ('Account #' . $accountId));
                $accountScheduleAnalyses[$accountId]['safety_mode'] = (string) ($schedule['safety_mode'] ?? 'safe');
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
            'title' => 'Lịch gửi',
            'schedules' => $schedules,
            'accounts' => $this->accounts->listForUser($userId),
            'groups' => array_values(array_filter(
                $this->groups->listForUser($userId),
                static fn (array $group): bool => (bool) ($group['is_active'] ?? false)
            )),
            'templates' => $this->templates->listForUser($userId),
            'searchQuery' => $searchQuery,
            'selectedAccountId' => $selectedAccountId,
            'selectedTemplateId' => $selectedTemplateId,
            'selectedStatus' => $selectedStatus,
            'defaultTimezone' => $defaultTimezone,
            'schedulePresets' => (new PresetService(app()->db()))->schedulePresets(),
            'scheduleAnalyses' => $scheduleAnalyses,
            'scheduleSummaries' => $scheduleSummaries,
            'scheduleManualGuards' => $scheduleManualGuards,
            'accountScheduleAnalyses' => array_values($accountScheduleAnalyses),
            'safetyRules' => config('safety'),
            'scheduleModes' => $builder->modeOptions(),
            'defaultFormScheduleState' => $builder->defaultFormData($defaultTimezone),
            'scheduleFormStates' => $scheduleFormStates ?? [],
            'pagination' => $pageResult['pagination'],
        ]);
    }

    public function preview(Request $request): void
    {
        $timezone = trim((string) $request->query('timezone', (string) config('app.timezone', 'Asia/Ho_Chi_Minh')));

        try {
            new DateTimeZone($timezone);

            $builder = new ScheduleBuilderService(new CronExpression());
            $preview = $builder->preview($request->all(), $timezone);
            $groupCount = count(array_filter((array) $request->query('telegram_group_ids', [])));
            $accountId = (int) $request->query('telegram_account_id', 0);
            $account = $accountId > 0
                ? $this->accounts->findForUser($accountId, (int) auth()->id())
                : null;

            if ($accountId > 0 && $account === null) {
                throw new Exception('Telegram account không hợp lệ.');
            }

            $risk = (new SchedulerService(app()->db(), new TelegramService(), new CronExpression()))
                ->analyzeScheduleRisk($preview['cron_expression'], $timezone, max(1, $groupCount), $account);

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
        $user = auth()->user() ?? [];
        $limit = auth()->access()->scheduleLimit($user);
        $currentCount = $this->schedules->count('user_id = :user_id', ['user_id' => (int) auth()->id()]);

        if (auth()->access()->limitReached($limit, $currentCount)) {
            $this->redirectWith(
                '/schedules',
                error: 'Bạn đã chạm giới hạn tối đa ' . auth()->access()->limitLabel($limit) . ' schedule.'
            );
        }

        $scheduler = new SchedulerService(app()->db(), new TelegramService(), new CronExpression());
        $data = $this->validatedData($request, $scheduler);
        $groupIds = $data['telegram_group_ids'];
        unset($data['telegram_group_ids']);
        $analysis = $scheduler->analyzeScheduleRisk(
            $data['cron_expression'],
            $data['timezone'],
            count($groupIds),
            $this->accounts->findForUser((int) $data['telegram_account_id'], (int) auth()->id())
        );
        $nextRunAt = $scheduler->calculateNextRun(
            $data['cron_expression'],
            $data['timezone'],
            new DateTimeImmutable('now', new DateTimeZone('UTC'))
        );

        $scheduleId = 0;
        app()->db()->transaction(function () use ($data, $groupIds, $nextRunAt, &$scheduleId): void {
            $scheduleId = $this->schedules->create(array_merge($data, [
                'user_id' => (int) auth()->id(),
                'next_run_at' => $nextRunAt,
                'occurrence_due_at' => null,
                'last_run_at' => null,
                'last_error' => null,
                'status' => 'active',
                'dispatch_locked_until' => null,
                'created_at' => gmdate('Y-m-d H:i:s'),
                'updated_at' => gmdate('Y-m-d H:i:s'),
            ]));
            $this->schedules->syncGroups($scheduleId, $groupIds);
        });

        $message = $analysis['risk'] === 'high'
            ? 'Đã tạo lịch gửi. Lưu ý lịch này khá dày, hệ thống sẽ tự giãn cách và giới hạn theo account.'
            : 'Đã tạo lịch gửi tin nhắn.';

        $this->redirectWith('/schedules', success: $message, payload: [
            'schedule_id' => $scheduleId,
        ]);
    }

    public function update(Request $request): void
    {
        $schedule = $this->schedules->findForUser((int) $request->input('id'), (int) auth()->id());

        if ($schedule === null) {
            abort404();
        }

        if ($this->scheduleIsDispatching($schedule)) {
            $this->redirectWith('/schedules', error: 'Lịch này đang trong một lượt gửi. Hãy thử cập nhật lại sau vài phút.');
        }

        $scheduler = new SchedulerService(app()->db(), new TelegramService(), new CronExpression());
        $data = $this->validatedData($request, $scheduler);
        $groupIds = $data['telegram_group_ids'];
        unset($data['telegram_group_ids']);
        $analysis = $scheduler->analyzeScheduleRisk(
            $data['cron_expression'],
            $data['timezone'],
            count($groupIds),
            $this->accounts->findForUser((int) $data['telegram_account_id'], (int) auth()->id())
        );
        $nextRunAt = $scheduler->calculateNextRun(
            $data['cron_expression'],
            $data['timezone'],
            new DateTimeImmutable('now', new DateTimeZone('UTC'))
        );

        app()->db()->transaction(function () use ($schedule, $data, $groupIds, $nextRunAt): void {
            $scheduleId = (int) $schedule['id'];
            $this->schedules->updateById($scheduleId, array_merge($data, [
                'next_run_at' => $nextRunAt,
                'occurrence_due_at' => null,
                'updated_at' => gmdate('Y-m-d H:i:s'),
            ]));
            $this->schedules->syncGroups($scheduleId, $groupIds);
        });

        $message = $analysis['risk'] === 'high'
            ? 'Đã cập nhật lịch gửi. Lưu ý lịch này khá dày, hệ thống sẽ tự giãn cách và giới hạn theo account.'
            : 'Đã cập nhật lịch gửi.';

        $this->redirectWith('/schedules', success: $message, payload: [
            'schedule_id' => (int) $schedule['id'],
        ]);
    }

    public function toggle(Request $request): void
    {
        $schedule = $this->schedules->findForUser((int) $request->input('id'), (int) auth()->id());

        if ($schedule === null) {
            abort404();
        }

        if ($this->scheduleIsDispatching($schedule)) {
            $this->redirectWith('/schedules', error: 'Lịch này đang trong một lượt gửi. Hãy thử đổi trạng thái lại sau vài phút.');
        }

        $newStatus = $schedule['status'] === 'active' ? 'paused' : 'active';
        $updates = [
            'status' => $newStatus,
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ];

        if ($newStatus === 'active') {
            $updates['next_run_at'] = (new SchedulerService(app()->db(), new TelegramService(), new CronExpression()))
                ->calculateNextRun(
                    (string) $schedule['cron_expression'],
                    (string) $schedule['timezone'],
                    new DateTimeImmutable('now', new DateTimeZone('UTC'))
                );
            $updates['last_error'] = null;
            $updates['queue_reason_code'] = null;
            $updates['occurrence_due_at'] = null;
            $updates['dispatch_locked_until'] = null;
        }

        $this->schedules->updateById((int) $schedule['id'], $updates);

        $message = $newStatus === 'active'
            ? 'Đã tiếp tục lịch gửi.'
            : 'Đã tạm dừng lịch gửi.';

        $this->redirectWith('/schedules', success: $message, payload: [
            'schedule' => [
                'id' => (int) $schedule['id'],
                'status' => $newStatus,
                'status_label' => $newStatus === 'active' ? 'Đang chạy' : 'Tạm dừng',
                'next_run_at' => (string) ($updates['next_run_at'] ?? $schedule['next_run_at'] ?? ''),
                'next_run_at_label' => fmt_datetime((string) ($updates['next_run_at'] ?? $schedule['next_run_at'] ?? '')),
                'queue_cleared' => $newStatus === 'active',
            ],
        ]);
    }

    public function delete(Request $request): void
    {
        $schedule = $this->schedules->findForUser((int) $request->input('id'), (int) auth()->id());

        if ($schedule === null) {
            abort404();
        }

        if ($this->scheduleIsDispatching($schedule)) {
            $this->redirectWith('/schedules', error: 'Lịch này đang trong một lượt gửi. Hãy thử xóa lại sau vài phút.');
        }

        $this->schedules->deleteById((int) $schedule['id']);
        $this->redirectWith('/schedules', success: 'Đã xóa lịch gửi.', payload: [
            'schedule_id' => (int) $schedule['id'],
            'deleted' => true,
        ]);
    }

    public function sendNow(Request $request): void
    {
        $schedule = $this->schedules->findForUser((int) $request->input('id'), (int) auth()->id());
        $forceSend = in_array((string) $request->input('force_send', '0'), ['1', 'true', 'on'], true);

        if ($schedule === null) {
            abort404();
        }

        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }

        $scheduler = new SchedulerService(app()->db(), new TelegramService(), new CronExpression());

        try {
            $result = $scheduler->dispatchScheduleNow((int) $schedule['id'], (int) auth()->id(), $forceSend);
        } catch (Exception $exception) {
            $this->redirectWith('/schedules', error: dispatch_error_message($exception->getMessage()), payload: [
                'schedule_id' => (int) $schedule['id'],
            ]);
        }

        if (($result['status'] ?? '') === 'success') {
            $message = $forceSend
                ? 'Đã ép gửi ngay schedule này thành công. Hãy theo dõi rủi ro cooldown / spam ở các lần gửi tiếp theo.'
                : 'Đã gửi ngay schedule này thành công.';
            $this->redirectWith('/schedules', success: $message, payload: [
                'schedule_id' => (int) $schedule['id'],
            ]);
        }

        if (($result['status'] ?? '') === 'partial') {
            $this->redirectWith(
                '/schedules',
                error: dispatch_error_message((string) ($result['error'] ?? 'Lịch đã gửi được một số nhóm nhưng có nhóm thất bại. Hãy kiểm tra nhật ký gửi tin.')),
                payload: ['schedule_id' => (int) $schedule['id']]
            );
        }

        if (($result['status'] ?? '') === 'guarded') {
            $message = (string) ($result['error'] ?? 'Schedule đang bị chặn tạm thời bởi cơ chế an toàn.');
            if (str_starts_with((string) ($result['guard_category'] ?? ''), 'soft_')) {
                $message .= ' Hãy bấm lại "Gửi ngay" và xác nhận rủi ro nếu bạn muốn ép gửi.';
            } else {
                $message .= ' Guard này là bắt buộc và không thể ép gửi vượt qua.';
            }
            $this->redirectWith('/schedules', error: $message, payload: [
                'schedule_id' => (int) $schedule['id'],
            ]);
        }

        if (($result['status'] ?? '') === 'locked') {
            $this->redirectWith(
                '/schedules',
                error: (string) ($result['error'] ?? 'Schedule đang được xử lý bởi tiến trình khác.'),
                payload: ['schedule_id' => (int) $schedule['id']]
            );
        }

        $this->redirectWith(
            '/schedules',
            error: dispatch_error_message((string) ($result['error'] ?? 'Gửi ngay thất bại, vui lòng kiểm tra lại account hoặc Telegram response.')),
            payload: ['schedule_id' => (int) $schedule['id']]
        );
    }

    private function validatedData(Request $request, ?SchedulerService $scheduler = null): array
    {
        $userId = (int) auth()->id();
        $accountId = (int) $request->input('telegram_account_id');
        $rawGroupIds = $request->input('telegram_group_ids', []);
        $rawGroupIds = is_array($rawGroupIds) ? $rawGroupIds : [$rawGroupIds];
        $legacyGroupId = (int) $request->input('telegram_group_id');

        if ($rawGroupIds === [] && $legacyGroupId > 0) {
            $rawGroupIds = [$legacyGroupId];
        }

        $groupIds = array_values(array_unique(array_filter(array_map('intval', $rawGroupIds), static fn (int $id): bool => $id > 0)));
        $templateId = (int) $request->input('message_template_id');
        $timezone = trim((string) $request->input('timezone'));

        $account = $this->accounts->findForUser($accountId, $userId);
        if ($account === null) {
            abort404();
        }

        if ($groupIds === []) {
            $this->redirectWith('/schedules', error: 'Bạn cần chọn ít nhất một nhóm Telegram.');
        }

        $groupsById = [];
        foreach ($this->groups->listForUser($userId) as $group) {
            $groupsById[(int) $group['id']] = $group;
        }

        foreach ($groupIds as $groupId) {
            $group = $groupsById[$groupId] ?? null;

            if ($group === null) {
                abort404();
            }

            if (!(bool) ($group['is_active'] ?? false)) {
                $this->redirectWith('/schedules', error: 'Nhóm "' . (string) $group['title'] . '" đang tạm dừng. Hãy kích hoạt nhóm trước khi thêm vào lịch.');
            }

            if ((int) ($group['telegram_account_id'] ?? 0) !== $accountId) {
                $this->redirectWith('/schedules', error: 'Tất cả group phải thuộc đúng Telegram account đã chọn.');
            }
        }

        if ($this->templates->findForUser($templateId, $userId) === null) {
            abort404();
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
        $analysis = $scheduler->analyzeScheduleRisk($built['cron_expression'], $timezone, count($groupIds), $account);

        if ($analysis['risk'] === 'blocked') {
            $this->redirectWith('/schedules', error: $analysis['message']);
        }

        return [
            'telegram_account_id' => $accountId,
            'telegram_group_id' => $groupIds[0],
            'telegram_group_ids' => $groupIds,
            'message_template_id' => $templateId,
            'timezone' => $timezone,
            'cron_expression' => $built['cron_expression'],
            'schedule_type' => $built['schedule_type'],
            'schedule_config_json' => $built['schedule_config_json'],
        ];
    }

    private function scheduleIsDispatching(array $schedule): bool
    {
        $lockedUntil = trim((string) ($schedule['dispatch_locked_until'] ?? ''));
        if ($lockedUntil === '') {
            return false;
        }

        return new DateTimeImmutable($lockedUntil, new DateTimeZone('UTC'))
            >= new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }
}
