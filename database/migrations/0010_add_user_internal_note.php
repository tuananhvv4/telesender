<?php

declare(strict_types=1);

use App\Core\Migration;

return new class extends Migration
{
    public string $version = '10';
    public string $name = 'add_user_internal_note';

    public function up(PDO $pdo): void
    {
        $columns = $pdo->query('SHOW COLUMNS FROM users LIKE \'internal_note\'')->fetchAll();

        if ($columns === []) {
            $pdo->exec(
                'ALTER TABLE users
                 ADD COLUMN internal_note TEXT NULL
                 AFTER max_schedule_jobs'
            );
        }
    }
};
