<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $file = base_path('app/' . str_replace('\\', '/', $relative) . '.php');

    if (is_file($file)) {
        require_once $file;
    }
});
