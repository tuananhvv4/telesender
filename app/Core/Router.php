<?php

declare(strict_types=1);

namespace App\Core;

class Router
{
    private array $routes = [];

    public function __construct(private readonly Application $app)
    {
    }

    public function get(string $path, callable|array $handler, array $middleware = []): void
    {
        $this->add('GET', $path, $handler, $middleware);
    }

    public function post(string $path, callable|array $handler, array $middleware = []): void
    {
        $this->add('POST', $path, $handler, $middleware);
    }

    public function dispatch(Request $request): void
    {
        $method = $request->method();
        $path = $request->path();
        $route = $this->routes[$method][$path] ?? null;

        if ($route === null) {
            throw new HttpException(404, 'Không tìm thấy trang yêu cầu.');
        }

        $this->runMiddleware($route['middleware']);

        if ($method === 'POST') {
            $this->verifyCsrf($request);
        }

        $handler = $route['handler'];

        if (is_array($handler)) {
            [$controller, $action] = $handler;
            $instance = new $controller();
            $instance->{$action}($request);
            return;
        }

        $handler($request);
    }

    private function add(string $method, string $path, callable|array $handler, array $middleware): void
    {
        $normalized = rtrim($path, '/') ?: '/';
        $this->routes[$method][$normalized] = [
            'handler' => $handler,
            'middleware' => $middleware,
        ];
    }

    private function verifyCsrf(Request $request): void
    {
        $token = (string) $request->input('_token', '');

        if (!Session::verifyCsrf($token)) {
            if ($request->expectsJson()) {
                Response::json([
                    'ok' => false,
                    'message' => 'Phiên làm việc đã hết hạn hoặc CSRF token không hợp lệ.',
                    'redirect' => url('/login'),
                    'status' => 419,
                ], 419);
            }

            throw new HttpException(419, 'Phiên làm việc đã hết hạn hoặc CSRF token không hợp lệ.');
        }
    }

    private function runMiddleware(array $middleware): void
    {
        foreach ($middleware as $item) {
            if ($item === 'auth') {
                if (!$this->app->auth()->check()) {
                    $this->interruptRequest(401, 'Vui lòng đăng nhập để tiếp tục.', '/login');
                }

                $user = $this->app->auth()->user();

                if ($user === null) {
                    $this->app->auth()->logout();
                    $this->interruptRequest(401, 'Phiên đăng nhập không còn hợp lệ.', '/login');
                }

                if ((string) ($user['status'] ?? 'inactive') !== 'active') {
                    $this->app->auth()->logout();
                    $this->interruptRequest(403, 'Tài khoản của bạn hiện đang bị khóa.', '/login');
                }
            }

            if ($item === 'guest' && $this->app->auth()->check()) {
                if (request()->expectsJson()) {
                    Response::json([
                        'ok' => false,
                        'message' => 'Bạn đã đăng nhập rồi.',
                        'redirect' => url('/'),
                        'status' => 409,
                    ], 409);
                }

                redirect('/');
            }

            if ($item === 'subscription_active') {
                $user = $this->app->auth()->user();

                if ($user === null) {
                    $this->app->auth()->logout();
                    $this->interruptRequest(401, 'Phiên đăng nhập không còn hợp lệ.', '/login');
                }

                if ($this->app->auth()->access()->isSuperAdmin($user)) {
                    continue;
                }

                if ($this->app->auth()->access()->isExpired($user)) {
                    $this->interruptRequest(403, 'Gói sử dụng của bạn đã hết hạn.', '/expired', null);
                }
            }

            if ($item === 'super_admin') {
                $user = $this->app->auth()->user();

                if ($user === null) {
                    $this->app->auth()->logout();
                    $this->interruptRequest(401, 'Phiên đăng nhập không còn hợp lệ.', '/login');
                }

                if (!$this->app->auth()->access()->isSuperAdmin($user)) {
                    $this->interruptRequest(403, 'Bạn không có quyền truy cập khu vực này.', '/');
                }
            }
        }
    }

    private function interruptRequest(int $status, string $message, string $redirectPath, ?string $flashKey = 'error'): never
    {
        if (request()->expectsJson()) {
            Response::json([
                'ok' => false,
                'message' => $message,
                'redirect' => url($redirectPath),
                'status' => $status,
            ], $status);
        }

        if ($flashKey !== null) {
            Session::flash($flashKey, $message);
        }

        redirect($redirectPath);
    }
}
