<?php

declare(strict_types=1);

namespace App\Core;

class Response
{
    public static function json(array $payload, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function file(string $path, string $contentType, int $maxAge = 604800): never
    {
        if (!is_file($path)) {
            http_response_code(404);
            exit;
        }

        header('Content-Type: ' . $contentType);
        header('Content-Length: ' . (string) filesize($path));
        header('Cache-Control: private, max-age=' . max(0, $maxAge) . ', immutable');
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        exit;
    }
}
