<?php

declare(strict_types=1);

namespace App\Core;

use App\Services\UserAccessService;
use Throwable;

class Application
{
    private static self $instance;

    private array $config = [];
    private Router $router;
    private Request $request;
    private Database $database;
    private Auth $auth;
    private string $basePath;

    private function __construct(string $basePath)
    {
        $this->basePath = $basePath;
        self::$instance = $this;

        $this->loadEnvironment();
        $this->loadConfig();

        date_default_timezone_set((string) $this->config('app.timezone', 'UTC'));

        Session::start((string) env('SESSION_NAME', 'tele_sender_session'));

        $this->request = Request::capture();
        $this->database = new Database($this->config('database'));
        (new UserAccessService($this->database))->syncSuperAdminRole();
        $this->auth = new Auth($this->database);
        $this->router = new Router($this);

        require $this->basePath . '/routes/web.php';
    }

    public static function boot(string $basePath): self
    {
        return new self($basePath);
    }

    public static function instance(): self
    {
        return self::$instance;
    }

    public function run(): void
    {
        try {
            $this->router->dispatch($this->request);
        } catch (HttpException $exception) {
            if ($this->request->expectsJson()) {
                Response::json([
                    'ok' => false,
                    'message' => $exception->getMessage(),
                    'status' => $exception->status(),
                ], $exception->status());
            }

            http_response_code($exception->status());
            echo View::make('errors/http', ['exception' => $exception], 'guest');
        } catch (Throwable $exception) {
            if ($this->request->expectsJson()) {
                $payload = [
                    'ok' => false,
                    'message' => (bool) $this->config('app.debug', false)
                        ? $exception->getMessage()
                        : 'Đã có lỗi nội bộ xảy ra.',
                    'status' => 500,
                ];

                if ((bool) $this->config('app.debug', false)) {
                    $payload['trace'] = $exception->getTraceAsString();
                }

                Response::json($payload, 500);
            }

            http_response_code(500);

            if ((bool) $this->config('app.debug', false)) {
                echo '<pre>' . e($exception->getMessage()) . "\n\n" . e($exception->getTraceAsString()) . '</pre>';
                return;
            }

            echo View::make('errors/http', [
                'exception' => new HttpException(500, 'Đã có lỗi nội bộ xảy ra.'),
            ], 'guest');
        }
    }

    public function config(string $key, mixed $default = null): mixed
    {
        $segments = explode('.', $key);
        $value = $this->config;

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    public function request(): Request
    {
        return $this->request;
    }

    public function db(): Database
    {
        return $this->database;
    }

    public function router(): Router
    {
        return $this->router;
    }

    public function auth(): Auth
    {
        return $this->auth;
    }

    private function loadConfig(): void
    {
        foreach (glob($this->basePath . '/config/*.php') ?: [] as $file) {
            $this->config[basename($file, '.php')] = require $file;
        }
    }

    private function loadEnvironment(): void
    {
        $envFile = $this->basePath . '/.env';
        $exampleFile = $this->basePath . '/.env.example';
        $file = is_file($envFile) ? $envFile : $exampleFile;

        if (!is_file($file)) {
            return;
        }

        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            [$name, $value] = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);

            if ($value !== '' && (
                (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                (str_starts_with($value, '\'') && str_ends_with($value, '\''))
            )) {
                $value = substr($value, 1, -1);
            }

            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
            putenv($name . '=' . $value);
        }
    }
}
