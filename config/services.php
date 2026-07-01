<?php

declare(strict_types=1);

return [
    'telegram' => [
        'api_id' => env('TELEGRAM_API_ID'),
        'api_hash' => env('TELEGRAM_API_HASH'),
    ],
    'tokens' => [
        'cron' => env('CRON_TOKEN'),
        'migrate' => env('MIGRATE_TOKEN'),
    ],
];
