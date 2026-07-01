<?php

declare(strict_types=1);

namespace App\Core;

class View
{
    public static function make(string $view, array $data = [], string $layout = 'app'): string
    {
        $viewPath = base_path('views/' . $view . '.php');
        $layoutPath = base_path('views/layouts/' . $layout . '.php');

        if (!is_file($viewPath)) {
            throw new HttpException(500, 'View không tồn tại: ' . $view);
        }

        extract($data, EXTR_SKIP);

        ob_start();
        require $viewPath;
        $content = ob_get_clean();

        if (!is_file($layoutPath)) {
            return $content;
        }

        ob_start();
        require $layoutPath;
        return (string) ob_get_clean();
    }
}
