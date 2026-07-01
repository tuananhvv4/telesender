<?php

declare(strict_types=1);

use App\Core\Migration;

return new class extends Migration
{
    public string $version = '3';
    public array $legacyVersions = ['202607010002'];
    public string $name = 'add_group_topics';

    public function up(PDO $pdo): void
    {
        $this->addColumnIfMissing(
            $pdo,
            'telegram_groups',
            'topic_id',
            'ALTER TABLE telegram_groups ADD COLUMN topic_id BIGINT NULL AFTER peer_identifier'
        );
        $this->addColumnIfMissing(
            $pdo,
            'telegram_groups',
            'topic_title',
            'ALTER TABLE telegram_groups ADD COLUMN topic_title VARCHAR(190) NULL AFTER topic_id'
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
