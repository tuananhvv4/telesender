<?php

declare(strict_types=1);

return [
    'name' => env('APP_NAME', 'TeleSender'),
    'env' => env('APP_ENV', 'production'),
    'debug' => filter_var(env('APP_DEBUG', false), FILTER_VALIDATE_BOOL),
    'url' => env('APP_URL', 'http://localhost:8000'),
    'key' => env('APP_KEY', 'change-me'),
    'timezone' => env('APP_TIMEZONE', 'UTC'),
    'allow_registration' => filter_var(env('ALLOW_REGISTRATION', false), FILTER_VALIDATE_BOOL),
    'super_admin_email' => env('SUPER_ADMIN_EMAIL', ''),
];
