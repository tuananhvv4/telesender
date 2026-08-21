<?php

declare(strict_types=1);

return [
    'cron_runtime_seconds' => 40,
    'cron_shutdown_reserve_seconds' => 5,
    'jobs_per_run' => 8,
    'dialogs_page_size' => 100,
    'history_page_size' => 40,
    'history_refresh_overlap' => 40,
    'flood_wait_limit_seconds' => 3,
    'sync_lock_seconds' => 120,
    'media_lock_seconds' => 120,
    'dispatch_lookahead_seconds' => 180,
    'media_dispatch_lookahead_seconds' => 300,
    'fresh_dialog_seconds' => 120,
    'fresh_history_seconds' => 60,
];
