<?php

declare(strict_types=1);

use App\Core\Migration;

return new class extends Migration
{
    public string $version = '14';
    public string $name = 'add_dispatch_run_keys';

    public function up(PDO $pdo): void
    {
        $accountLockColumn = $pdo->query("SHOW COLUMNS FROM telegram_accounts LIKE 'dispatch_locked_until'")->fetchAll();
        if ($accountLockColumn === []) {
            $pdo->exec(
                'ALTER TABLE telegram_accounts
                 ADD COLUMN dispatch_locked_until DATETIME NULL AFTER cooldown_reason'
            );
        }

        $accountLockIndex = $pdo->query("SHOW INDEX FROM telegram_accounts WHERE Key_name = 'idx_telegram_accounts_dispatch_lock'")->fetchAll();
        if ($accountLockIndex === []) {
            $pdo->exec(
                'ALTER TABLE telegram_accounts
                 ADD KEY idx_telegram_accounts_dispatch_lock (dispatch_locked_until)'
            );
        }

        $column = $pdo->query("SHOW COLUMNS FROM dispatch_logs LIKE 'schedule_run_key'")->fetchAll();
        if ($column === []) {
            $pdo->exec(
                'ALTER TABLE dispatch_logs
                 ADD COLUMN schedule_run_key VARCHAR(80) NULL AFTER schedule_job_id'
            );
        }

        $index = $pdo->query("SHOW INDEX FROM dispatch_logs WHERE Key_name = 'uq_dispatch_schedule_run_group'")->fetchAll();
        if ($index === []) {
            $pdo->exec(
                'ALTER TABLE dispatch_logs
                 ADD UNIQUE KEY uq_dispatch_schedule_run_group (schedule_job_id, schedule_run_key, telegram_group_id)'
            );
        }
    }
};
