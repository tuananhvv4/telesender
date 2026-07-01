<?php

declare(strict_types=1);

namespace App\Core;

class Session
{
    public static function start(string $sessionName): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        session_name($sessionName);
        session_save_path(storage_path('sessions'));
        session_start();
    }

    public static function put(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function flash(string $key, mixed $value = null): mixed
    {
        if (func_num_args() === 2) {
            $_SESSION['_flash'][$key] = $value;
            return null;
        }

        $value = $_SESSION['_flash'][$key] ?? null;
        unset($_SESSION['_flash'][$key]);
        return $value;
    }

    public static function putOldInput(array $data): void
    {
        $_SESSION['_old'] = $data;
    }

    public static function old(string $key, mixed $default = null): mixed
    {
        $value = $_SESSION['_old'][$key] ?? $default;
        unset($_SESSION['_old'][$key]);
        return $value;
    }

    public static function csrfToken(): string
    {
        if (!isset($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['_csrf'];
    }

    public static function verifyCsrf(string $token): bool
    {
        return hash_equals((string) ($_SESSION['_csrf'] ?? ''), $token);
    }

    public static function regenerate(): void
    {
        session_regenerate_id(true);
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        session_destroy();
    }
}
