<?php

declare(strict_types=1);

use App\Core\Migration;
return new class extends Migration
{
    public string $version = '1';
    public array $legacyVersions = ['202606270001'];
    public string $name = 'initial_schema';

    public function up(PDO $pdo): void
    {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS users (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(120) NOT NULL,
                email VARCHAR(190) NOT NULL UNIQUE,
                password_hash VARCHAR(255) NOT NULL,
                role VARCHAR(40) NOT NULL DEFAULT \'manager\',
                status VARCHAR(40) NOT NULL DEFAULT \'active\',
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS telegram_accounts (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT UNSIGNED NOT NULL,
                name VARCHAR(120) NOT NULL,
                phone_number VARCHAR(40) NOT NULL,
                session_name VARCHAR(190) NOT NULL UNIQUE,
                session_status VARCHAR(40) NOT NULL DEFAULT \'draft\',
                tg_user_id BIGINT NULL,
                tg_username VARCHAR(190) NULL,
                last_connected_at DATETIME NULL,
                meta_json JSON NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                CONSTRAINT fk_telegram_accounts_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS telegram_groups (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT UNSIGNED NOT NULL,
                telegram_account_id BIGINT UNSIGNED NOT NULL,
                title VARCHAR(190) NOT NULL,
                peer_identifier VARCHAR(190) NOT NULL,
                topic_id BIGINT NULL,
                topic_title VARCHAR(190) NULL,
                notes TEXT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                CONSTRAINT fk_telegram_groups_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                CONSTRAINT fk_telegram_groups_account FOREIGN KEY (telegram_account_id) REFERENCES telegram_accounts(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS message_labels (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT UNSIGNED NOT NULL,
                name VARCHAR(120) NOT NULL,
                slug VARCHAR(190) NOT NULL,
                color VARCHAR(20) NOT NULL DEFAULT \'#111827\',
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                CONSTRAINT fk_message_labels_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                UNIQUE KEY uq_message_label_user_slug (user_id, slug)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS message_templates (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT UNSIGNED NOT NULL,
                label_id BIGINT UNSIGNED NULL,
                name VARCHAR(190) NOT NULL,
                body MEDIUMTEXT NOT NULL,
                parse_mode VARCHAR(20) NOT NULL DEFAULT \'HTML\',
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                CONSTRAINT fk_message_templates_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                CONSTRAINT fk_message_templates_label FOREIGN KEY (label_id) REFERENCES message_labels(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS schedule_jobs (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT UNSIGNED NOT NULL,
                telegram_account_id BIGINT UNSIGNED NOT NULL,
                telegram_group_id BIGINT UNSIGNED NOT NULL,
                message_template_id BIGINT UNSIGNED NOT NULL,
                timezone VARCHAR(80) NOT NULL,
                cron_expression TEXT NOT NULL,
                schedule_type VARCHAR(40) NOT NULL DEFAULT \'advanced\',
                schedule_config_json LONGTEXT NULL,
                next_run_at DATETIME NULL,
                last_run_at DATETIME NULL,
                last_error TEXT NULL,
                status VARCHAR(20) NOT NULL DEFAULT \'active\',
                dispatch_locked_until DATETIME NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                CONSTRAINT fk_schedule_jobs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                CONSTRAINT fk_schedule_jobs_account FOREIGN KEY (telegram_account_id) REFERENCES telegram_accounts(id) ON DELETE CASCADE,
                CONSTRAINT fk_schedule_jobs_group FOREIGN KEY (telegram_group_id) REFERENCES telegram_groups(id) ON DELETE CASCADE,
                CONSTRAINT fk_schedule_jobs_template FOREIGN KEY (message_template_id) REFERENCES message_templates(id) ON DELETE CASCADE,
                KEY idx_schedule_jobs_next_run (next_run_at),
                KEY idx_schedule_jobs_status (status),
                KEY idx_schedule_jobs_lock (dispatch_locked_until)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS dispatch_logs (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT UNSIGNED NOT NULL,
                schedule_job_id BIGINT UNSIGNED NULL,
                telegram_account_id BIGINT UNSIGNED NULL,
                telegram_group_id BIGINT UNSIGNED NULL,
                message_template_id BIGINT UNSIGNED NULL,
                label_id BIGINT UNSIGNED NULL,
                request_id VARCHAR(80) NOT NULL,
                status VARCHAR(20) NOT NULL,
                message_preview TEXT NOT NULL,
                response_payload LONGTEXT NULL,
                error_message TEXT NULL,
                sent_at DATETIME NOT NULL,
                created_at DATETIME NOT NULL,
                CONSTRAINT fk_dispatch_logs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                CONSTRAINT fk_dispatch_logs_schedule FOREIGN KEY (schedule_job_id) REFERENCES schedule_jobs(id) ON DELETE SET NULL,
                CONSTRAINT fk_dispatch_logs_account FOREIGN KEY (telegram_account_id) REFERENCES telegram_accounts(id) ON DELETE SET NULL,
                CONSTRAINT fk_dispatch_logs_group FOREIGN KEY (telegram_group_id) REFERENCES telegram_groups(id) ON DELETE SET NULL,
                CONSTRAINT fk_dispatch_logs_template FOREIGN KEY (message_template_id) REFERENCES message_templates(id) ON DELETE SET NULL,
                CONSTRAINT fk_dispatch_logs_label FOREIGN KEY (label_id) REFERENCES message_labels(id) ON DELETE SET NULL,
                KEY idx_dispatch_logs_user_sent (user_id, sent_at),
                KEY idx_dispatch_logs_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }
};
