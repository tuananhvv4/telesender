<?php

declare(strict_types=1);

use App\Core\Migration;

return new class extends Migration
{
    public string $version = '7';
    public string $name = 'add_account_active_flag';

    public function up(PDO $pdo): void
    {
        $columns = $pdo->query('SHOW COLUMNS FROM telegram_accounts LIKE \'is_active\'')->fetchAll();

        if ($columns === []) {
            $pdo->exec(
                'ALTER TABLE telegram_accounts
                 ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1
                 AFTER session_status'
            );
        }
    }
};
