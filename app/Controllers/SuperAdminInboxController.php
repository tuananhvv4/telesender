<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Services\TelegramAccountLockService;
use App\Services\TelegramInboxMediaService;
use App\Services\TelegramInboxService;
use App\Services\TelegramInboxSyncService;
use App\Services\TelegramMessageNormalizer;
use App\Services\TelegramService;

class SuperAdminInboxController extends Controller
{
    private TelegramInboxService $inbox;

    public function __construct()
    {
        $this->inbox = new TelegramInboxService(app()->db());
    }

    public function index(Request $request): void
    {
        $this->render('admin/inbox', [
            'title' => 'Hội thoại Telegram',
            'admins' => $this->inbox->admins(),
        ]);
    }

    public function accounts(Request $request): void
    {
        $this->jsonSuccess('Đã tải Telegram account.', [
            'items' => $this->inbox->accountsForAdmin((int) $request->query('user_id', 0)),
        ]);
    }

    public function dialogs(Request $request): void
    {
        $payload = $this->inbox->dialogs(
            (int) $request->query('account_id', 0),
            trim((string) $request->query('q', '')),
            trim((string) $request->query('type', 'all'))
        );
        $this->jsonSuccess('Đã tải danh sách hội thoại.', $payload);
    }

    public function messages(Request $request): void
    {
        $before = (int) $request->query('before_message_id', 0);
        $topicId = (int) $request->query('topic_id', 0);
        $payload = $this->inbox->messages(
            (int) $request->query('dialog_id', 0),
            $before > 0 ? $before : null,
            (int) $request->query('limit', 40),
            $topicId > 0 ? $topicId : null
        );
        $this->jsonSuccess('Đã tải tin nhắn.', $payload);
    }

    public function topics(Request $request): void
    {
        $this->jsonSuccess('Đã tải danh sách topic.', $this->inbox->topics(
            (int) $request->query('dialog_id', 0)
        ));
    }

    public function syncAccount(Request $request): void
    {
        $jobKey = $this->inbox->enqueueAccountSync((int) $request->input('account_id'));
        $result = $this->syncRunner()->runJob($jobKey);
        $job = $this->inbox->syncJobStatus($jobKey);
        $this->jsonSuccess($this->syncMessage($result, $job), ['sync' => $result, 'job' => $job]);
    }

    public function syncDialog(Request $request): void
    {
        $topicId = (int) $request->input('topic_id', 0);
        $jobKey = $this->inbox->enqueueDialogSync(
            (int) $request->input('dialog_id'),
            $topicId > 0 ? $topicId : null
        );
        $result = $this->syncRunner()->runJob($jobKey);
        $job = $this->inbox->syncJobStatus($jobKey);
        $this->jsonSuccess($this->syncMessage($result, $job), ['sync' => $result, 'job' => $job]);
    }

    public function loadOlder(Request $request): void
    {
        $jobKey = $this->inbox->enqueueOlder(
            (int) $request->input('dialog_id'),
            (int) $request->input('before_message_id'),
            (int) $request->input('topic_id', 0) ?: null
        );
        $result = $jobKey !== null ? $this->syncRunner()->runJob($jobKey) : ['processed' => 0, 'completed' => 0];
        $job = $jobKey !== null ? $this->inbox->syncJobStatus($jobKey) : ['status' => 'completed'];
        $this->jsonSuccess($this->syncMessage($result, $job), ['sync' => $result, 'job' => $job]);
    }

    public function media(Request $request): void
    {
        (new TelegramInboxMediaService(
            $this->inbox,
            new TelegramService(),
            new TelegramAccountLockService(app()->db())
        ))->stream((int) $request->query('message_id', 0));
    }

    private function syncRunner(): TelegramInboxSyncService
    {
        return new TelegramInboxSyncService(
            app()->db(),
            new TelegramService(),
            new TelegramMessageNormalizer(),
            new TelegramAccountLockService(app()->db())
        );
    }

    private function syncMessage(array $result, array $job): string
    {
        if ((int) ($result['completed'] ?? 0) > 0) {
            if ((string) ($job['last_error_code'] ?? '') === 'empty_dialogs') {
                return (string) ($job['last_error_message'] ?? 'Telegram trả về 0 hội thoại cho account này.');
            }
            return 'Đồng bộ Telegram ưu tiên cao đã hoàn tất.';
        }
        if ((int) ($result['busy_accounts'] ?? 0) > 0) {
            return 'Account đang ưu tiên gửi tin; cron inbox sẽ tự thử lại.';
        }
        if ((int) ($result['rescheduled'] ?? 0) > 0) {
            $error = trim((string) ($job['last_error_message'] ?? ''));
            return $error !== ''
                ? 'Chưa thể đồng bộ: ' . $error
                : 'Chưa thể đồng bộ ngay; cron inbox sẽ tự thử lại.';
        }

        $error = trim((string) ($job['last_error_message'] ?? ''));
        if ($error !== '') {
            return 'Đồng bộ lỗi: ' . $error;
        }

        return 'Không có dữ liệu mới cần đồng bộ.';
    }
}
