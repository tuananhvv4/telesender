<?php

declare(strict_types=1);

use App\Core\Migration;

return new class extends Migration
{
    public string $version = '20';
    public string $name = 'add_telegram_inbox_dialog_bot_flag';

    public function up(PDO $pdo): void
    {
        if ($pdo->query("SHOW COLUMNS FROM telegram_inbox_dialogs LIKE 'is_bot'")->fetchAll() === []) {
            $pdo->exec(
                'ALTER TABLE telegram_inbox_dialogs
                 ADD COLUMN is_bot TINYINT(1) NOT NULL DEFAULT 0 AFTER is_forum,
                 ADD KEY idx_inbox_dialog_account_type (telegram_account_id, peer_type, is_bot)'
            );
        }
    }
};
