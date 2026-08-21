<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class TelegramInboxSyncJob extends Model
{
    protected string $table = 'telegram_inbox_sync_jobs';
    protected array $fillable = [
        'job_key', 'user_id', 'telegram_account_id', 'telegram_inbox_dialog_id', 'job_type',
        'priority', 'status', 'cursor_json', 'attempts', 'next_attempt_at', 'locked_until',
        'lock_token', 'last_error_code', 'last_error_message', 'started_at', 'completed_at',
        'created_at', 'updated_at',
    ];
}
