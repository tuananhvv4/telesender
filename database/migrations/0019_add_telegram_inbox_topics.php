<?php

declare(strict_types=1);

use App\Core\Migration;

return new class extends Migration
{
    public string $version = '19';
    public string $name = 'add_telegram_inbox_topics';

    public function up(PDO $pdo): void
    {
        if ($pdo->query("SHOW COLUMNS FROM telegram_inbox_dialogs LIKE 'is_forum'")->fetchAll() === []) {
            $pdo->exec(
                'ALTER TABLE telegram_inbox_dialogs
                 ADD COLUMN is_forum TINYINT(1) NOT NULL DEFAULT 0 AFTER peer_type'
            );
        }

        if ($pdo->query("SHOW COLUMNS FROM telegram_inbox_messages LIKE 'topic_id'")->fetchAll() === []) {
            $pdo->exec(
                'ALTER TABLE telegram_inbox_messages
                 ADD COLUMN topic_id BIGINT NULL AFTER reply_to_top_id,
                 ADD KEY idx_inbox_message_dialog_topic_id (telegram_inbox_dialog_id, topic_id, telegram_message_id)'
            );
            $pdo->exec(
                'UPDATE telegram_inbox_messages
                 SET topic_id = reply_to_top_id
                 WHERE reply_to_top_id IS NOT NULL'
            );
        }

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS telegram_inbox_topics (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT UNSIGNED NOT NULL,
                telegram_account_id BIGINT UNSIGNED NOT NULL,
                telegram_inbox_dialog_id BIGINT UNSIGNED NOT NULL,
                topic_id BIGINT NOT NULL,
                title VARCHAR(255) NOT NULL,
                icon_color INT NULL,
                icon_emoji_id VARCHAR(40) NULL,
                top_message_id BIGINT NULL,
                unread_count INT UNSIGNED NOT NULL DEFAULT 0,
                oldest_message_id BIGINT NULL,
                newest_message_id BIGINT NULL,
                history_complete TINYINT(1) NOT NULL DEFAULT 0,
                last_synced_at DATETIME NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE KEY uq_inbox_topic_dialog_topic (telegram_inbox_dialog_id, topic_id),
                KEY idx_inbox_topic_account_dialog (telegram_account_id, telegram_inbox_dialog_id),
                CONSTRAINT fk_inbox_topic_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                CONSTRAINT fk_inbox_topic_account FOREIGN KEY (telegram_account_id) REFERENCES telegram_accounts(id) ON DELETE CASCADE,
                CONSTRAINT fk_inbox_topic_dialog FOREIGN KEY (telegram_inbox_dialog_id) REFERENCES telegram_inbox_dialogs(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }
};
