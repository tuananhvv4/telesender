<?php

declare(strict_types=1);

return [
    'account_limits' => [
        'max_success_per_hour' => 8,
        'max_success_per_day' => 40,
        'min_minutes_between_sends' => 8,
        'max_occurrence_delay_minutes' => 60,
        'dispatch_lock_minutes' => 5,
        'inter_group_delay_seconds_min' => 3,
        'inter_group_delay_seconds_max' => 8,
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
