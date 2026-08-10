<?php

declare(strict_types=1);

use App\Core\Migration;

return new class extends Migration
{
    public string $version = '13';
    public string $name = 'add_schedule_job_groups';

    public function up(PDO $pdo): void
    {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS schedule_job_groups (
                schedule_job_id BIGINT UNSIGNED NOT NULL,
                telegram_group_id BIGINT UNSIGNED NOT NULL,
                sort_order INT UNSIGNED NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL,
                PRIMARY KEY (schedule_job_id, telegram_group_id),
                CONSTRAINT fk_schedule_job_groups_schedule FOREIGN KEY (schedule_job_id) REFERENCES schedule_jobs(id) ON DELETE CASCADE,
                CONSTRAINT fk_schedule_job_groups_group FOREIGN KEY (telegram_group_id) REFERENCES telegram_groups(id) ON DELETE CASCADE,
                KEY idx_schedule_job_groups_group (telegram_group_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $pdo->exec(
            'INSERT IGNORE INTO schedule_job_groups (schedule_job_id, telegram_group_id, sort_order, created_at)
             SELECT id, telegram_group_id, 0, created_at
             FROM schedule_jobs'
        );
    }
};
