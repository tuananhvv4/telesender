<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class TelegramInboxDialog extends Model
{
    protected string $table = 'telegram_inbox_dialogs';
    protected array $fillable = [
        'user_id', 'telegram_account_id', 'peer_key', 'peer_identifier', 'peer_type', 'is_forum', 'is_bot',
        'title', 'username', 'top_message_id', 'last_message_text', 'last_message_at',
        'unread_count', 'oldest_message_id', 'newest_message_id', 'history_complete',
        'last_opened_at', 'last_synced_at', 'created_at', 'updated_at',
    ];
}
