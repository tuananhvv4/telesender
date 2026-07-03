<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Models\User;
use App\Services\UserAccessService;

class AuthController extends Controller
{
    public function showLogin(Request $request): void
    {
        $access = new UserAccessService(app()->db());

        $this->render('auth/login', [
            'title' => 'Đăng nhập',
            'showRegisterLink' => $access->canShowRegisterLink(),
            'registerLinkLabel' => $access->bootstrapPending() ? 'Khởi tạo super admin' : 'Đăng ký',
        ], 'guest');
    }

    public function login(Request $request): void
    {
        $email = trim((string) $request->input('email'));
        $password = (string) $request->input('password');

        Session::putOldInput(['email' => $email]);

        if ($email === '' || $password === '') {
            $this->redirectWith('/login', error: 'Email và mật khẩu là bắt buộc.');
        }

        $result = auth()->attemptDetailed($email, $password);

        if (!$result['ok']) {
            $message = match ($result['reason'] ?? 'invalid') {
                'inactive' => 'Tài khoản của bạn hiện đang bị khóa.',
                default => 'Thông tin đăng nhập không hợp lệ.',
            };

            $this->redirectWith('/login', error: $message);
        }

        $user = (array) ($result['user'] ?? []);

        if (auth()->access()->isExpired($user)) {
            $this->redirectWith('/expired', error: 'Gói sử dụng của bạn đã hết hạn. Vui lòng liên hệ quản trị viên để được gia hạn.');
        }

        $this->redirectWith('/', success: 'Đăng nhập thành công.');
    }

    public function showRegister(Request $request): void
    {
        $access = new UserAccessService(app()->db());

        if (!$access->canShowRegisterLink()) {
            $this->redirectWith('/login', error: 'Chức năng đăng ký đang bị tắt.');
        }

        $this->render('auth/register', [
            'title' => $access->bootstrapPending() ? 'Khởi tạo super admin' : 'Đăng ký',
            'registerHeading' => $access->bootstrapPending() ? 'Khởi tạo super admin' : 'Đăng ký tài khoản',
        ], 'guest');
    }

    public function register(Request $request): void
    {
        $access = new UserAccessService(app()->db());

        if (!$access->canShowRegisterLink()) {
            $this->redirectWith('/login', error: 'Chức năng đăng ký đang bị tắt.');
        }

        $name = trim((string) $request->input('name'));
        $email = trim((string) $request->input('email'));
        $password = (string) $request->input('password');
        $passwordConfirmation = (string) $request->input('password_confirmation');

        Session::putOldInput(['name' => $name, 'email' => $email]);

        if ($name === '' || $email === '' || $password === '') {
            $this->redirectWith('/register', error: 'Vui lòng điền đầy đủ thông tin.');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->redirectWith('/register', error: 'Email không đúng định dạng.');
        }

        if ($password !== $passwordConfirmation) {
            $this->redirectWith('/register', error: 'Mật khẩu xác nhận không khớp.');
        }

        $userModel = new User();

        if ($userModel->count('email = :email', ['email' => $email]) > 0) {
            $this->redirectWith('/register', error: 'Email này đã tồn tại.');
        }

        if (!$access->canSelfRegister($email)) {
            $this->redirectWith('/register', error: 'Email này không được phép tự đăng ký trên hệ thống.');
        }

        $userModel->create([
            'name' => $name,
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'role' => $access->roleForNewRegistration($email),
            'status' => 'active',
            'subscription_expires_at' => null,
            'created_at' => gmdate('Y-m-d H:i:s'),
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ]);

        auth()->attemptDetailed($email, $password);
        $this->redirectWith('/', success: 'Tạo tài khoản thành công.');
    }

    public function logout(Request $request): void
    {
        auth()->logout();
        redirect('/login');
    }
}
