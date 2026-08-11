<?php

declare(strict_types=1);

use App\Core\Migration;

return new class extends Migration
{
    public string $version = '15';
    public string $name = 'add_safety_policy_override';

    public function up(PDO $pdo): void
    {
        $this->addColumn($pdo, 'users', 'can_override_safety_limits',
            "ALTER TABLE users ADD COLUMN can_override_safety_limits TINYINT(1) NOT NULL DEFAULT 0 AFTER max_schedule_jobs"
        );

        $this->addColumn($pdo, 'telegram_accounts', 'safety_mode',
            "ALTER TABLE telegram_accounts ADD COLUMN safety_mode VARCHAR(30) NOT NULL DEFAULT 'safe' AFTER dispatch_locked_until"
        );
        $this->addColumn($pdo, 'telegram_accounts', 'safety_mode_changed_at',
            'ALTER TABLE telegram_accounts ADD COLUMN safety_mode_changed_at DATETIME NULL AFTER safety_mode'
        );
        $this->addColumn($pdo, 'telegram_accounts', 'safety_mode_changed_by',
            'ALTER TABLE telegram_accounts ADD COLUMN safety_mode_changed_by BIGINT UNSIGNED NULL AFTER safety_mode_changed_at'
        );
        $this->addColumn($pdo, 'telegram_accounts', 'risk_acknowledged_at',
            'ALTER TABLE telegram_accounts ADD COLUMN risk_acknowledged_at DATETIME NULL AFTER safety_mode_changed_by'
        );
        $this->addColumn($pdo, 'telegram_accounts', 'risk_acknowledged_by',
            'ALTER TABLE telegram_accounts ADD COLUMN risk_acknowledged_by BIGINT UNSIGNED NULL AFTER risk_acknowledged_at'
        );
        $this->addColumn($pdo, 'telegram_accounts', 'circuit_breaker_until',
            'ALTER TABLE telegram_accounts ADD COLUMN circuit_breaker_until DATETIME NULL AFTER risk_acknowledged_by'
        );
        $this->addColumn($pdo, 'telegram_accounts', 'circuit_breaker_reason',
            'ALTER TABLE telegram_accounts ADD COLUMN circuit_breaker_reason VARCHAR(255) NULL AFTER circuit_breaker_until'
        );

        $this->addColumn($pdo, 'schedule_jobs', 'queue_reason_code',
            'ALTER TABLE schedule_jobs ADD COLUMN queue_reason_code VARCHAR(60) NULL AFTER last_error'
        );

        $this->addColumn($pdo, 'dispatch_logs', 'safety_mode_snapshot',
            'ALTER TABLE dispatch_logs ADD COLUMN safety_mode_snapshot VARCHAR(30) NULL AFTER status'
        );
        $this->addColumn($pdo, 'dispatch_logs', 'safety_override_used',
            'ALTER TABLE dispatch_logs ADD COLUMN safety_override_used TINYINT(1) NOT NULL DEFAULT 0 AFTER safety_mode_snapshot'
        );
        $this->addColumn($pdo, 'dispatch_logs', 'safety_usage_snapshot_json',
            'ALTER TABLE dispatch_logs ADD COLUMN safety_usage_snapshot_json LONGTEXT NULL AFTER safety_override_used'
        );

        $this->addIndex($pdo, 'dispatch_logs', 'idx_dispatch_logs_account_status_sent',
            'ALTER TABLE dispatch_logs ADD KEY idx_dispatch_logs_account_status_sent (telegram_account_id, status, sent_at)'
        );
        $this->addIndex($pdo, 'telegram_accounts', 'idx_telegram_accounts_safety_mode',
            'ALTER TABLE telegram_accounts ADD KEY idx_telegram_accounts_safety_mode (safety_mode)'
        );
        $this->addIndex($pdo, 'telegram_accounts', 'idx_telegram_accounts_circuit_breaker',
            'ALTER TABLE telegram_accounts ADD KEY idx_telegram_accounts_circuit_breaker (circuit_breaker_until)'
        );
        $this->addIndex($pdo, 'schedule_jobs', 'idx_schedule_jobs_queue_reason',
            'ALTER TABLE schedule_jobs ADD KEY idx_schedule_jobs_queue_reason (queue_reason_code)'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS account_safety_policy_events (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT UNSIGNED NOT NULL,
                telegram_account_id BIGINT UNSIGNED NOT NULL,
                actor_user_id BIGINT UNSIGNED NULL,
                event_type VARCHAR(60) NOT NULL,
                previous_mode VARCHAR(30) NULL,
                new_mode VARCHAR(30) NULL,
                reason TEXT NULL,
                metadata_json LONGTEXT NULL,
                created_at DATETIME NOT NULL,
                CONSTRAINT fk_safety_events_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                CONSTRAINT fk_safety_events_account FOREIGN KEY (telegram_account_id) REFERENCES telegram_accounts(id) ON DELETE CASCADE,
                CONSTRAINT fk_safety_events_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL,
                KEY idx_safety_events_user_created (user_id, created_at),
                KEY idx_safety_events_account_created (telegram_account_id, created_at),
                KEY idx_safety_events_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS user_notifications (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT UNSIGNED NOT NULL,
                type VARCHAR(60) NOT NULL,
                title VARCHAR(190) NOT NULL,
                message TEXT NOT NULL,
                severity VARCHAR(20) NOT NULL DEFAULT \'warning\',
                telegram_account_id BIGINT UNSIGNED NULL,
                dispatch_log_id BIGINT UNSIGNED NULL,
                dedupe_key VARCHAR(190) NOT NULL,
                metadata_json LONGTEXT NULL,
                read_at DATETIME NULL,
                created_at DATETIME NOT NULL,
                CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                CONSTRAINT fk_notifications_account FOREIGN KEY (telegram_account_id) REFERENCES telegram_accounts(id) ON DELETE SET NULL,
                CONSTRAINT fk_notifications_dispatch FOREIGN KEY (dispatch_log_id) REFERENCES dispatch_logs(id) ON DELETE SET NULL,
                UNIQUE KEY uq_notifications_user_dedupe (user_id, dedupe_key),
                KEY idx_notifications_user_read_created (user_id, read_at, created_at),
                KEY idx_notifications_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $defaults = [
            'safety_safe_hourly_limit' => '8',
            'safety_safe_daily_limit' => '40',
            'safety_safe_min_gap_minutes' => '8',
            'safety_elevated_hourly_limit' => '10',
            'safety_elevated_daily_limit' => '80',
            'safety_elevated_min_gap_minutes' => '5',
            'safety_risk_min_gap_minutes' => '1',
            'safety_admin_self_override_enabled' => '1',
            'safety_circuit_breaker_error_count' => '3',
            'safety_circuit_breaker_window_minutes' => '15',
            'safety_circuit_breaker_cooldown_minutes' => '180',
            'safety_audit_retention_days' => '30',
        ];
        $insert = $pdo->prepare(
            'INSERT INTO system_settings (key_name, value_text, created_at, updated_at)
             VALUES (:key_name, :value_text, UTC_TIMESTAMP(), UTC_TIMESTAMP())'
        );
        $exists = $pdo->prepare('SELECT id FROM system_settings WHERE key_name = :key_name LIMIT 1');

        foreach ($defaults as $key => $value) {
            $exists->execute(['key_name' => $key]);
            if ($exists->fetch() !== false) {
                continue;
            }

            $insert->execute(['key_name' => $key, 'value_text' => $value]);
        }
    }

    private function addColumn(PDO $pdo, string $table, string $column, string $sql): void
    {
        $statement = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = :table_name AND column_name = :column_name'
        );
        $statement->execute(['table_name' => $table, 'column_name' => $column]);

        if ((int) $statement->fetchColumn() === 0) {
            $pdo->exec($sql);
        }
    }

    private function addIndex(PDO $pdo, string $table, string $index, string $sql): void
    {
        $statement = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.statistics
             WHERE table_schema = DATABASE() AND table_name = :table_name AND index_name = :index_name'
        );
        $statement->execute(['table_name' => $table, 'index_name' => $index]);

        if ((int) $statement->fetchColumn() === 0) {
            $pdo->exec($sql);
        }
    }
};
