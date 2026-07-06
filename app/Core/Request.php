<?php

declare(strict_types=1);

namespace App\Core;

class Request
{
    public function __construct(
        private readonly array $get,
        private readonly array $post,
        private readonly array $server
    ) {
    }

    public static function capture(): self
    {
        return new self($_GET, $_POST, $_SERVER);
    }

    public function method(): string
    {
        return strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');
    }

    public function path(): string
    {
        $path = parse_url($this->server['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        return rtrim($path, '/') ?: '/';
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->post[$key] ?? $this->get[$key] ?? $default;
    }

    public function only(array $keys): array
    {
        $data = [];
        foreach ($keys as $key) {
            $data[$key] = $this->input($key);
        }
        return $data;
    }

    public function all(): array
    {
        return array_merge($this->get, $this->post);
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->get[$key] ?? $default;
    }

    public function queryParams(): array
    {
        return $this->get;
    }

    public function server(string $key, mixed $default = null): mixed
    {
        return $this->server[$key] ?? $default;
    }

    public function header(string $key, mixed $default = null): mixed
    {
        $normalized = strtoupper(str_replace('-', '_', $key));
        $serverKey = 'HTTP_' . $normalized;

        if (in_array($normalized, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true)) {
            $serverKey = $normalized;
        }

        return $this->server[$serverKey] ?? $default;
    }

    public function isAjax(): bool
    {
        return strcasecmp((string) $this->header('X-Requested-With', ''), 'XMLHttpRequest') === 0;
    }

    public function acceptsJson(): bool
    {
        $accept = strtolower((string) $this->header('Accept', ''));

        return str_contains($accept, 'application/json')
            || str_contains($accept, 'text/json')
            || str_contains($accept, 'application/problem+json');
    }

    public function expectsJson(): bool
    {
        return $this->isAjax() || $this->acceptsJson();
    }
}
