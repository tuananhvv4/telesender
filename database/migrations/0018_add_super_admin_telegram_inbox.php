<?php

declare(strict_types=1);

use App\Core\Migration;

return new class extends Migration
{
    public string $version = '18';
    public string $name = 'add_super_admin_telegram_inbox';

    public function up(PDO $pdo): void
    {
        $columns = [
            'operation_lock_type' => "VARCHAR(30) NULL AFTER dispatch_locked_until",
            'operation_lock_token' => "VARCHAR(64) NULL AFTER operation_lock_type",
        ];

        foreach ($columns as $column => $definition) {
            $existing = $pdo->query("SHOW COLUMNS FROM telegram_accounts LIKE '{$column}'")->fetchAll();
            if ($existing === []) {
                $pdo->exec("ALTER TABLE telegram_accounts ADD COLUMN {$column} {$definition}");
            }
        }

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS telegram_inbox_dialogs (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT UNSIGNED NOT NULL,
                telegram_account_id BIGINT UNSIGNED NOT NULL,
                peer_key VARCHAR(190) NOT NULL,
                peer_identifier VARCHAR(190) NOT NULL,
                peer_type VARCHAR(30) NOT NULL,
                title VARCHAR(255) NOT NULL,
                username VARCHAR(190) NULL,
                top_message_id BIGINT NULL,
                last_message_text TEXT NULL,
                last_message_at DATETIME NULL,
                unread_count INT UNSIGNED NOT NULL DEFAULT 0,
                oldest_message_id BIGINT NULL,
                newest_message_id BIGINT NULL,
                history_complete TINYINT(1) NOT NULL DEFAULT 0,
                last_opened_at DATETIME NULL,
                last_synced_at DATETIME NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE KEY uq_inbox_dialog_account_peer (telegram_account_id, peer_key),
                KEY idx_inbox_dialog_user_account (user_id, telegram_account_id),
                KEY idx_inbox_dialog_account_last (telegram_account_id, last_message_at),
                CONSTRAINT fk_inbox_dialog_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                CONSTRAINT fk_inbox_dialog_account FOREIGN KEY (telegram_account_id) REFERENCES telegram_accounts(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS telegram_inbox_messages (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT UNSIGNED NOT NULL,
                telegram_account_id BIGINT UNSIGNED NOT NULL,
                telegram_inbox_dialog_id BIGINT UNSIGNED NOT NULL,
                telegram_message_id BIGINT NOT NULL,
                sender_peer_key VARCHAR(190) NULL,
                sender_name VARCHAR(255) NULL,
                is_outgoing TINYINT(1) NOT NULL DEFAULT 0,
                message_text MEDIUMTEXT NULL,
                reply_to_message_id BIGINT NULL,
                reply_to_top_id BIGINT NULL,
                reply_quote_text TEXT NULL,
                grouped_id VARCHAR(40) NULL,
                media_type VARCHAR(40) NULL,
                media_file_name VARCHAR(255) NULL,
                media_mime_type VARCHAR(190) NULL,
                media_size BIGINT UNSIGNED NULL,
                media_source_json LONGTEXT NULL,
                telegram_created_at DATETIME NOT NULL,
                edited_at DATETIME NULL,
                raw_type VARCHAR(80) NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE KEY uq_inbox_message_dialog_tg_id (telegram_inbox_dialog_id, telegram_message_id),
                KEY idx_inbox_message_dialog_id (telegram_inbox_dialog_id, telegram_message_id),
                KEY idx_inbox_message_dialog_date (telegram_inbox_dialog_id, telegram_created_at),
                CONSTRAINT fk_inbox_message_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                CONSTRAINT fk_inbox_message_account FOREIGN KEY (telegram_account_id) REFERENCES telegram_accounts(id) ON DELETE CASCADE,
                CONSTRAINT fk_inbox_message_dialog FOREIGN KEY (telegram_inbox_dialog_id) REFERENCES telegram_inbox_dialogs(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS telegram_inbox_sync_jobs (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                job_key VARCHAR(255) NOT NULL,
                user_id BIGINT UNSIGNED NOT NULL,
                telegram_account_id BIGINT UNSIGNED NOT NULL,
                telegram_inbox_dialog_id BIGINT UNSIGNED NULL,
                job_type VARCHAR(40) NOT NULL,
                priority SMALLINT NOT NULL DEFAULT 0,
                status VARCHAR(20) NOT NULL DEFAULT \'pending\',
                cursor_json LONGTEXT NULL,
                attempts INT UNSIGNED NOT NULL DEFAULT 0,
                next_attempt_at DATETIME NULL,
                locked_until DATETIME NULL,
                lock_token VARCHAR(64) NULL,
                last_error_code VARCHAR(80) NULL,
                last_error_message TEXT NULL,
                started_at DATETIME NULL,
                completed_at DATETIME NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE KEY uq_inbox_sync_job_key (job_key),
                KEY idx_inbox_sync_pick (status, next_attempt_at, priority, locked_until),
                KEY idx_inbox_sync_account (telegram_account_id, status),
                CONSTRAINT fk_inbox_sync_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                CONSTRAINT fk_inbox_sync_account FOREIGN KEY (telegram_account_id) REFERENCES telegram_accounts(id) ON DELETE CASCADE,
                CONSTRAINT fk_inbox_sync_dialog FOREIGN KEY (telegram_inbox_dialog_id) REFERENCES telegram_inbox_dialogs(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }
};
