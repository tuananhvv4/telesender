<?php

declare(strict_types=1);

use App\Core\Migration;

return new class extends Migration
{
    public string $version = '4';
    public array $legacyVersions = ['202607020001'];
    public string $name = 'add_schedule_builder_fields';

    public function up(PDO $pdo): void
    {
        $this->addColumnIfMissing(
            $pdo,
            'schedule_jobs',
            'schedule_type',
            "ALTER TABLE schedule_jobs
             ADD COLUMN schedule_type VARCHAR(40) NOT NULL DEFAULT 'advanced' AFTER cron_expression"
        );

        $this->addColumnIfMissing(
            $pdo,
            'schedule_jobs',
            'schedule_config_json',
            "ALTER TABLE schedule_jobs
             ADD COLUMN schedule_config_json LONGTEXT NULL AFTER schedule_type"
        );
    }

    private function addColumnIfMissing(PDO $pdo, string $table, string $column, string $statement): void
    {
        $query = $pdo->prepare(
            'SELECT COUNT(*) AS aggregate
             FROM information_schema.columns
             WHERE table_schema = DATABASE()
               AND table_name = :table_name
               AND column_name = :column_name'
        );
        $query->execute([
            'table_name' => $table,
            'column_name' => $column,
        ]);

        $exists = (int) ($query->fetch(PDO::FETCH_ASSOC)['aggregate'] ?? 0) > 0;

        if (!$exists) {
            $pdo->exec($statement);
        }
    }
};
