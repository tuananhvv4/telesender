<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class TelegramInboxMessage extends Model
{
    protected string $table = 'telegram_inbox_messages';
    protected array $fillable = [
        'user_id', 'telegram_account_id', 'telegram_inbox_dialog_id', 'telegram_message_id',
        'sender_peer_key', 'sender_name', 'is_outgoing', 'message_text', 'reply_to_message_id',
        'reply_to_top_id', 'reply_quote_text', 'grouped_id', 'media_type', 'media_file_name',
        'media_mime_type', 'media_size', 'media_source_json', 'telegram_created_at',
        'edited_at', 'raw_type', 'created_at', 'updated_at',
    ];
}
