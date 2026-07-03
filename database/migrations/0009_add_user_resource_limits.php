<?php

declare(strict_types=1);

use App\Core\Migration;

return new class extends Migration
{
    public string $version = '9';
    public string $name = 'add_user_resource_limits';

    public function up(PDO $pdo): void
    {
        $accountLimitColumns = $pdo->query('SHOW COLUMNS FROM users LIKE \'max_telegram_accounts\'')->fetchAll();
        if ($accountLimitColumns === []) {
            $pdo->exec(
                'ALTER TABLE users
                 ADD COLUMN max_telegram_accounts INT NULL AFTER subscription_expires_at'
            );
        }

        $scheduleLimitColumns = $pdo->query('SHOW COLUMNS FROM users LIKE \'max_schedule_jobs\'')->fetchAll();
        if ($scheduleLimitColumns === []) {
            $pdo->exec(
                'ALTER TABLE users
                 ADD COLUMN max_schedule_jobs INT NULL AFTER max_telegram_accounts'
            );
        }
    }
};
