<?php

declare(strict_types=1);

use App\Core\Migration;

return new class extends Migration
{
    public string $version = '17';
    public string $name = 'add_schedule_occurrence_due_at';

    public function up(PDO $pdo): void
    {
        $column = $pdo->query("SHOW COLUMNS FROM schedule_jobs LIKE 'occurrence_due_at'")->fetchAll();
        if ($column === []) {
            $pdo->exec(
                'ALTER TABLE schedule_jobs
                 ADD COLUMN occurrence_due_at DATETIME NULL AFTER next_run_at'
            );
        }

        $index = $pdo->query("SHOW INDEX FROM schedule_jobs WHERE Key_name = 'idx_schedule_jobs_occurrence_due_at'")->fetchAll();
        if ($index === []) {
            $pdo->exec(
                'ALTER TABLE schedule_jobs
                 ADD KEY idx_schedule_jobs_occurrence_due_at (occurrence_due_at)'
            );
        }
    }
};
