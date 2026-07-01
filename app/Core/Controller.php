<?php

declare(strict_types=1);

namespace App\Core;

abstract class Controller
{
    protected function render(string $view, array $data = [], string $layout = 'app'): void
    {
        echo View::make($view, $data, $layout);
    }

    protected function redirectWith(string $path, ?string $success = null, ?string $error = null): never
    {
        if ($success !== null) {
            Session::flash('success', $success);
        }

        if ($error !== null) {
            Session::flash('error', $error);
        }

        redirect($path);
    }
}
