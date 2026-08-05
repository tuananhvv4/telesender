<?php

declare(strict_types=1);

use App\Core\Migration;

return new class extends Migration
{
    public string $version = '12';
    public string $name = 'add_dispatch_log_message_snapshots';

    public function up(PDO $pdo): void
    {
        $columns = [
            'template_name_snapshot' => 'VARCHAR(190) NULL AFTER message_template_id',
            'message_body_snapshot' => 'MEDIUMTEXT NULL AFTER template_name_snapshot',
            'parse_mode_snapshot' => 'VARCHAR(20) NULL AFTER message_body_snapshot',
        ];

        foreach ($columns as $column => $definition) {
            $existing = $pdo->query("SHOW COLUMNS FROM dispatch_logs LIKE '{$column}'")->fetchAll();

            if ($existing === []) {
                $pdo->exec("ALTER TABLE dispatch_logs ADD COLUMN {$column} {$definition}");
            }
        }

        $pdo->exec(
            'UPDATE dispatch_logs dl
             INNER JOIN message_templates mt ON mt.id = dl.message_template_id
             SET dl.template_name_snapshot = COALESCE(dl.template_name_snapshot, mt.name),
                 dl.message_body_snapshot = COALESCE(dl.message_body_snapshot, mt.body),
                 dl.parse_mode_snapshot = COALESCE(dl.parse_mode_snapshot, mt.parse_mode)'
        );
    }
};
