<?php

declare(strict_types=1);

return [
    'account_limits' => [
        'max_success_per_hour' => 6,
        'max_success_per_day' => 30,
        'min_minutes_between_sends' => 8,
        'post_send_jitter_seconds_min' => 20,
        'post_send_jitter_seconds_max' => 75,
        'spam_cooldown_minutes' => 180,
    ],
    'schedule_limits' => [
        'warn_runs_per_day' => 12,
        'high_runs_per_day' => 24,
        'block_runs_per_day' => 48,
        'warn_min_gap_minutes' => 60,
        'high_min_gap_minutes' => 30,
    ],
];
