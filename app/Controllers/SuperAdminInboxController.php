<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Services\TelegramAccountLockService;
use App\Services\TelegramInboxMediaService;
use App\Services\TelegramInboxService;
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
            trim((string) $request->query('q', ''))
        );
        $this->jsonSuccess('Đã tải danh sách hội thoại.', $payload);
    }

    public function messages(Request $request): void
    {
        $before = (int) $request->query('before_message_id', 0);
        $payload = $this->inbox->messages(
            (int) $request->query('dialog_id', 0),
            $before > 0 ? $before : null,
            (int) $request->query('limit', 40)
        );
        $this->jsonSuccess('Đã tải tin nhắn.', $payload);
    }

    public function syncAccount(Request $request): void
    {
        $this->inbox->enqueueAccountSync((int) $request->input('account_id'));
        $this->jsonSuccess('Đã đưa account vào hàng chờ đồng bộ.');
    }

    public function syncDialog(Request $request): void
    {
        $this->inbox->enqueueDialogSync((int) $request->input('dialog_id'));
        $this->jsonSuccess('Đã đưa hội thoại vào hàng chờ đồng bộ.');
    }

    public function loadOlder(Request $request): void
    {
        $this->inbox->enqueueOlder(
            (int) $request->input('dialog_id'),
            (int) $request->input('before_message_id')
        );
        $this->jsonSuccess('Đã đưa lịch sử cũ vào hàng chờ đồng bộ.');
    }

    public function media(Request $request): void
    {
        (new TelegramInboxMediaService(
            $this->inbox,
            new TelegramService(),
            new TelegramAccountLockService(app()->db())
        ))->stream((int) $request->query('message_id', 0));
    }
}
