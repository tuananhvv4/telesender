<?php

declare(strict_types=1);

use App\Core\Application;
use App\Core\Session;

if (!function_exists('base_path')) {
    function base_path(string $path = ''): string
    {
        $base = dirname(__DIR__);
        return $path === '' ? $base : $base . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR);
    }
}

if (!function_exists('public_path')) {
    function public_path(string $path = ''): string
    {
        return base_path('public' . ($path ? DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR) : ''));
    }
}

if (!function_exists('storage_path')) {
    function storage_path(string $path = ''): string
    {
        return base_path('storage' . ($path ? DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR) : ''));
    }
}

if (!function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        return $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key) ?: $default;
    }
}

if (!function_exists('app')) {
    function app(): Application
    {
        return Application::instance();
    }
}

if (!function_exists('config')) {
    function config(string $key, mixed $default = null): mixed
    {
        return app()->config($key, $default);
    }
}

if (!function_exists('request')) {
    function request(): App\Core\Request
    {
        return app()->request();
    }
}

if (!function_exists('redirect')) {
    function redirect(string $path): never
    {
        header('Location: ' . url($path));
        exit;
    }
}

if (!function_exists('url')) {
    function url(string $path = ''): string
    {
        $base = rtrim((string) config('app.url', ''), '/');
        $path = ltrim($path, '/');
        return $path === '' ? $base : $base . '/' . $path;
    }
}

if (!function_exists('asset')) {
    function asset(string $path): string
    {
        $normalizedPath = ltrim($path, '/');
        $assetUrl = url('assets/' . $normalizedPath);
        $forcedVersion = trim((string) env('APP_ASSET_VERSION', ''));

        if ($forcedVersion !== '') {
            return $assetUrl . '?v=' . rawurlencode($forcedVersion);
        }

        static $versionCache = [];

        if (!array_key_exists($normalizedPath, $versionCache)) {
            $fullPath = public_path('assets/' . $normalizedPath);

            if (is_file($fullPath)) {
                $hash = md5_file($fullPath);
                $versionCache[$normalizedPath] = $hash !== false ? substr($hash, 0, 10) : (string) filemtime($fullPath);
            } else {
                $versionCache[$normalizedPath] = null;
            }
        }

        $version = $versionCache[$normalizedPath];

        if ($version === null || $version === '') {
            return $assetUrl;
        }

        return $assetUrl . '?v=' . rawurlencode((string) $version);
    }
}

if (!function_exists('e')) {
    function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('old')) {
    function old(string $key, mixed $default = ''): mixed
    {
        return Session::old($key, $default);
    }
}

if (!function_exists('flash')) {
    function flash(string $key): mixed
    {
        return Session::flash($key);
    }
}

if (!function_exists('auth')) {
    function auth(): App\Core\Auth
    {
        return app()->auth();
    }
}

if (!function_exists('user_is_super_admin')) {
    function user_is_super_admin(?array $user = null): bool
    {
        $user ??= auth()->user();

        if ($user === null) {
            return false;
        }

        return auth()->access()->isSuperAdmin($user);
    }
}

if (!function_exists('user_is_expired')) {
    function user_is_expired(?array $user = null): bool
    {
        $user ??= auth()->user();

        if ($user === null) {
            return false;
        }

        return auth()->access()->isExpired($user);
    }
}

if (!function_exists('user_days_remaining')) {
    function user_days_remaining(?array $user = null): ?int
    {
        $user ??= auth()->user();

        if ($user === null) {
            return null;
        }

        return auth()->access()->daysRemaining($user);
    }
}

if (!function_exists('user_subscription_label')) {
    function user_subscription_label(?array $user = null): string
    {
        $user ??= auth()->user();

        if ($user === null) {
            return '';
        }

        return auth()->access()->subscriptionStateLabel($user);
    }
}

if (!function_exists('system_settings_map')) {
    function system_settings_map(): array
    {
        static $cache = null;

        if (is_array($cache)) {
            return $cache;
        }

        $model = new App\Models\SystemSetting();

        try {
            $cache = $model->resolvedMap();
        } catch (Throwable) {
            $cache = $model->defaults();
        }

        return $cache;
    }
}

if (!function_exists('support_contact_href')) {
    function support_contact_href(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return 'mailto:' . $value;
        }

        if (filter_var($value, FILTER_VALIDATE_URL)) {
            return $value;
        }

        if (preg_match('/^\+?[0-9][0-9\s\-\(\)]*$/', $value) === 1) {
            return 'tel:' . preg_replace('/[^0-9+]/', '', $value);
        }

        return null;
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        return Session::csrfToken();
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
    }
}

if (!function_exists('is_active_path')) {
    function is_active_path(string $path): bool
    {
        return trim(request()->path(), '/') === trim($path, '/');
    }
}

if (!function_exists('abort404')) {
    function abort404(string $message = 'Không tìm thấy tài nguyên yêu cầu.'): never
    {
        throw new App\Core\HttpException(404, $message);
    }
}

if (!function_exists('fmt_datetime')) {
    function fmt_datetime(?string $value, string $timezone = 'Asia/Ho_Chi_Minh'): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        $date = new DateTimeImmutable($value, new DateTimeZone('UTC'));
        return $date->setTimezone(new DateTimeZone($timezone))->format('d/m/Y H:i');
    }
}

if (!function_exists('pagination_url')) {
    function pagination_url(int $page, array $overrides = []): string
    {
        $query = request()->queryParams();
        unset($query['page']);

        foreach ($overrides as $key => $value) {
            if ($value === null || $value === '') {
                unset($query[$key]);
                continue;
            }

            $query[$key] = $value;
        }

        $query['page'] = max(1, $page);
        $queryString = http_build_query($query);
        $base = url(request()->path());

        return $queryString === '' ? $base : $base . '?' . $queryString;
    }
}

if (!function_exists('pagination_series')) {
    function pagination_series(array $pagination, int $side = 1): array
    {
        $current = (int) ($pagination['page'] ?? 1);
        $totalPages = max(1, (int) ($pagination['total_pages'] ?? 1));

        if ($totalPages <= 7) {
            return range(1, $totalPages);
        }

        $pages = [1];
        $start = max(2, $current - $side);
        $end = min($totalPages - 1, $current + $side);

        if ($start > 2) {
            $pages[] = '...';
        }

        for ($page = $start; $page <= $end; $page++) {
            $pages[] = $page;
        }

        if ($end < $totalPages - 1) {
            $pages[] = '...';
        }

        $pages[] = $totalPages;

        return $pages;
    }
}

if (!function_exists('pagination_per_page')) {
    function pagination_per_page(int $default = 20, array $allowed = [10, 15, 20, 30, 50, 100]): int
    {
        $requested = (int) request()->query('per_page', $default);

        if (!in_array($requested, $allowed, true)) {
            return $default;
        }

        return $requested;
    }
}
