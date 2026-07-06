<?php

declare(strict_types=1);

namespace App\Core;

abstract class Controller
{
    protected function render(string $view, array $data = [], string $layout = 'app'): void
    {
        echo View::make($view, $data, $layout);
    }

    protected function redirectWith(
        string $path,
        ?string $success = null,
        ?string $error = null,
        int $status = 200,
        array $payload = []
    ): never
    {
        if (request()->expectsJson()) {
            $httpStatus = $error !== null ? max(400, $status === 200 ? 422 : $status) : $status;

            Response::json(array_merge([
                'ok' => $error === null,
                'message' => $error ?? $success ?? '',
                'redirect' => url($path),
                'status' => $httpStatus,
            ], $payload), $httpStatus);
        }

        if ($success !== null) {
            Session::flash('success', $success);
        }

        if ($error !== null) {
            Session::flash('error', $error);
        }

        redirect($path);
    }

    protected function jsonSuccess(string $message, array $payload = [], int $status = 200): never
    {
        Response::json(array_merge([
            'ok' => true,
            'message' => $message,
            'status' => $status,
        ], $payload), $status);
    }

    protected function jsonError(string $message, int $status = 422, array $payload = []): never
    {
        Response::json(array_merge([
            'ok' => false,
            'message' => $message,
            'status' => $status,
        ], $payload), $status);
    }
}
