<?php

declare(strict_types=1);

use App\Core\Migration;

return new class extends Migration
{
    public string $version = '5';
    public array $legacyVersions = ['202607020002'];
    public string $name = 'expand_schedule_cron_expression';

    public function up(PDO $pdo): void
    {
        $query = $pdo->prepare(
            'SELECT DATA_TYPE AS data_type
             FROM information_schema.columns
             WHERE table_schema = DATABASE()
               AND table_name = :table_name
               AND column_name = :column_name
             LIMIT 1'
        );
        $query->execute([
            'table_name' => 'schedule_jobs',
            'column_name' => 'cron_expression',
        ]);

        $dataType = strtolower((string) ($query->fetch(PDO::FETCH_ASSOC)['data_type'] ?? ''));

        if ($dataType !== 'text') {
            $pdo->exec(
                "ALTER TABLE schedule_jobs
                 MODIFY cron_expression TEXT NOT NULL"
            );
        }
    }
};
