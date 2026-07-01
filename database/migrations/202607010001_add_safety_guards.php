<?php

declare(strict_types=1);

use App\Core\Migration;

return new class extends Migration
{
    public string $version = '202607010001';
    public string $name = 'add_safety_guards';

    public function up(PDO $pdo): void
    {
        $this->addColumnIfMissing(
            $pdo,
            'telegram_accounts',
            'last_sent_at',
            'ALTER TABLE telegram_accounts ADD COLUMN last_sent_at DATETIME NULL AFTER last_connected_at'
        );
        $this->addColumnIfMissing(
            $pdo,
            'telegram_accounts',
            'cooldown_until',
            'ALTER TABLE telegram_accounts ADD COLUMN cooldown_until DATETIME NULL AFTER last_sent_at'
        );
        $this->addColumnIfMissing(
            $pdo,
            'telegram_accounts',
            'cooldown_reason',
            'ALTER TABLE telegram_accounts ADD COLUMN cooldown_reason VARCHAR(190) NULL AFTER cooldown_until'
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
