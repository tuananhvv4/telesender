<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Models\TelegramAccount;
use App\Models\TelegramGroup;

class TelegramGroupController extends Controller
{
    public function __construct(
        private readonly TelegramGroup $groups = new TelegramGroup(),
        private readonly TelegramAccount $accounts = new TelegramAccount()
    ) {
    }

    public function index(Request $request): void
    {
        $userId = (int) auth()->id();
        $editGroup = null;
        $editId = (int) $request->query('edit', 0);

        if ($editId > 0) {
            $editGroup = $this->groups->findForUser($editId, $userId);
        }

        $this->render('groups/index', [
            'title' => 'Telegram Groups',
            'groups' => $this->groups->listForUser($userId),
            'accounts' => $this->accounts->listForUser($userId),
            'editGroup' => $editGroup,
        ]);
    }

    public function store(Request $request): void
    {
        $userId = (int) auth()->id();
        $title = trim((string) $request->input('title'));
        $peer = trim((string) $request->input('peer_identifier'));
        $accountId = (int) $request->input('telegram_account_id');

        if ($title === '' || $peer === '' || $accountId <= 0) {
            $this->redirectWith('/groups', error: 'Bạn cần chọn account, tên nhóm và peer identifier.');
        }

        $this->ensureOwnedAccount($accountId, $userId);

        $this->groups->create([
            'user_id' => $userId,
            'telegram_account_id' => $accountId,
            'title' => $title,
            'peer_identifier' => $peer,
            'notes' => trim((string) $request->input('notes')),
            'is_active' => $request->input('is_active') ? 1 : 0,
            'created_at' => gmdate('Y-m-d H:i:s'),
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ]);

        $this->redirectWith('/groups', success: 'Đã thêm nhóm Telegram.');
    }

    public function update(Request $request): void
    {
        $userId = (int) auth()->id();
        $groupId = (int) $request->input('id');
        $group = $this->groups->findForUser($groupId, $userId);

        if ($group === null) {
            abort404();
        }

        $title = trim((string) $request->input('title'));
        $peer = trim((string) $request->input('peer_identifier'));

        $accountId = (int) $request->input('telegram_account_id');
        $this->ensureOwnedAccount($accountId, $userId);

        if ($title === '' || $peer === '') {
            $this->redirectWith('/groups?edit=' . $groupId, error: 'Tên nhóm và peer identifier là bắt buộc.');
        }

        $this->groups->updateById($groupId, [
            'telegram_account_id' => $accountId,
            'title' => $title,
            'peer_identifier' => $peer,
            'notes' => trim((string) $request->input('notes')),
            'is_active' => $request->input('is_active') ? 1 : 0,
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ]);

        $this->redirectWith('/groups', success: 'Đã cập nhật nhóm Telegram.');
    }

    public function delete(Request $request): void
    {
        $group = $this->groups->findForUser((int) $request->input('id'), (int) auth()->id());

        if ($group === null) {
            abort404();
        }

        $this->groups->deleteById((int) $group['id']);
        $this->redirectWith('/groups', success: 'Đã xóa nhóm Telegram.');
    }

    private function ensureOwnedAccount(int $accountId, int $userId): void
    {
        if ($this->accounts->findForUser($accountId, $userId) === null) {
            abort404();
        }
    }
}
