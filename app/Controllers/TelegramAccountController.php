<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Models\TelegramAccount;
use App\Services\TelegramService;

class TelegramAccountController extends Controller
{
    public function __construct(
        private readonly TelegramAccount $accounts = new TelegramAccount(),
        private readonly TelegramService $telegram = new TelegramService()
    ) {
    }

    public function index(Request $request): void
    {
        $result = $this->accounts->paginateForUser((int) auth()->id(), (int) $request->query('page', 1), pagination_per_page(20));

        $this->render('accounts/index', [
            'title' => 'Telegram Accounts',
            'accounts' => $result['items'],
            'pagination' => $result['pagination'],
        ]);
    }

    public function store(Request $request): void
    {
        $name = trim((string) $request->input('name'));
        $phone = trim((string) $request->input('phone_number'));
        $user = auth()->user() ?? [];

        if ($name === '' || $phone === '') {
            $this->redirectWith('/accounts', error: 'Tên account và số điện thoại là bắt buộc.');
        }

        $limit = auth()->access()->accountLimit($user);
        $currentCount = $this->accounts->count('user_id = :user_id', ['user_id' => (int) auth()->id()]);

        if (auth()->access()->limitReached($limit, $currentCount)) {
            $this->redirectWith(
                '/accounts',
                error: 'Tài khoản của bạn chỉ có thể quản lý tối đa ' . auth()->access()->limitLabel($limit) . ' tài khoản Telegram. Để thêm nhiều hơn, vui lòng liên hệ quản trị viên để nâng cấp.',
            );
        }

        $sessionName = 'account_' . auth()->id() . '_' . time();

        $this->accounts->create([
            'user_id' => (int) auth()->id(),
            'name' => $name,
            'phone_number' => $phone,
            'session_name' => $sessionName,
            'session_status' => 'draft',
            'is_active' => 1,
            'meta_json' => null,
            'created_at' => gmdate('Y-m-d H:i:s'),
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ]);

        $this->redirectWith('/accounts', success: 'Đã tạo account Telegram, tiếp theo hãy gửi mã đăng nhập.');
    }

    public function sendCode(Request $request): void
    {
        $account = $this->ownedAccount((int) $request->input('account_id'));

        try {
            $result = $this->telegram->startLogin($account);
            $this->accounts->updateById((int) $account['id'], [
                'session_status' => $result['status'],
                'updated_at' => gmdate('Y-m-d H:i:s'),
            ]);
            $this->redirectWith('/accounts', success: $result['message']);
        } catch (\Throwable $exception) {
            $this->redirectWith('/accounts', error: $exception->getMessage());
        }
    }

    public function verifyCode(Request $request): void
    {
        $account = $this->ownedAccount((int) $request->input('account_id'));
        $code = trim((string) $request->input('code'));

        if ($code === '') {
            $this->redirectWith('/accounts', error: 'Bạn cần nhập mã OTP.');
        }

        try {
            $result = $this->telegram->completeCode($account, $code);
            $profile = $result['profile'] ?? [];

            $this->accounts->updateById((int) $account['id'], [
                'session_status' => $result['status'],
                'tg_user_id' => $profile['id'] ?? null,
                'tg_username' => $profile['username'] ?? null,
                'last_connected_at' => gmdate('Y-m-d H:i:s'),
                'meta_json' => $profile ? json_encode($profile, JSON_UNESCAPED_UNICODE) : null,
                'updated_at' => gmdate('Y-m-d H:i:s'),
            ]);

            $this->redirectWith('/accounts', success: $result['message']);
        } catch (\Throwable $exception) {
            $this->redirectWith('/accounts', error: $exception->getMessage());
        }
    }

    public function verifyPassword(Request $request): void
    {
        $account = $this->ownedAccount((int) $request->input('account_id'));
        $password = (string) $request->input('password');

        if ($password === '') {
            $this->redirectWith('/accounts', error: 'Bạn cần nhập mật khẩu 2FA.');
        }

        try {
            $result = $this->telegram->completePassword($account, $password);
            $profile = $result['profile'] ?? [];

            $this->accounts->updateById((int) $account['id'], [
                'session_status' => $result['status'],
                'tg_user_id' => $profile['id'] ?? null,
                'tg_username' => $profile['username'] ?? null,
                'last_connected_at' => gmdate('Y-m-d H:i:s'),
                'meta_json' => $profile ? json_encode($profile, JSON_UNESCAPED_UNICODE) : null,
                'updated_at' => gmdate('Y-m-d H:i:s'),
            ]);

            $this->redirectWith('/accounts', success: $result['message']);
        } catch (\Throwable $exception) {
            $this->redirectWith('/accounts', error: $exception->getMessage());
        }
    }

    public function toggleActive(Request $request): void
    {
        $account = $this->ownedAccount((int) $request->input('account_id'));
        $isActive = (int) ($account['is_active'] ?? 1) === 1;

        $this->accounts->updateById((int) $account['id'], [
            'is_active' => $isActive ? 0 : 1,
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ]);

        $this->redirectWith(
            '/accounts',
            success: $isActive
                ? 'Đã tạm dừng tài khoản này.'
                : 'Đã bật lại tài khoản này.'
        );
    }

    private function ownedAccount(int $accountId): array
    {
        $account = $this->accounts->findForUser($accountId, (int) auth()->id());

        if ($account === null) {
            abort404();
        }

        return $account;
    }
}
