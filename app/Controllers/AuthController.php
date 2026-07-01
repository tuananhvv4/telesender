<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Models\User;

class AuthController extends Controller
{
    public function showLogin(Request $request): void
    {
        $this->render('auth/login', ['title' => 'Đăng nhập'], 'guest');
    }

    public function login(Request $request): void
    {
        $email = trim((string) $request->input('email'));
        $password = (string) $request->input('password');

        Session::putOldInput(['email' => $email]);

        if ($email === '' || $password === '') {
            $this->redirectWith('/login', error: 'Email và mật khẩu là bắt buộc.');
        }

        if (!auth()->attempt($email, $password)) {
            $this->redirectWith('/login', error: 'Thông tin đăng nhập không hợp lệ.');
        }

        $this->redirectWith('/', success: 'Đăng nhập thành công.');
    }

    public function showRegister(Request $request): void
    {
        if (!config('app.allow_registration', true)) {
            $this->redirectWith('/login', error: 'Chức năng đăng ký đang bị tắt.');
        }

        $this->render('auth/register', ['title' => 'Đăng ký'], 'guest');
    }

    public function register(Request $request): void
    {
        if (!config('app.allow_registration', true)) {
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

        $userModel->create([
            'name' => $name,
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'role' => 'manager',
            'status' => 'active',
            'created_at' => gmdate('Y-m-d H:i:s'),
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ]);

        auth()->attempt($email, $password);
        $this->redirectWith('/', success: 'Tạo tài khoản thành công.');
    }

    public function logout(Request $request): void
    {
        auth()->logout();
        redirect('/login');
    }
}
