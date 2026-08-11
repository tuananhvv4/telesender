<?php

declare(strict_types=1);

use App\Core\Migration;

return new class extends Migration
{
    public string $version = '16';
    public string $name = 'add_user_safety_permission_audit';

    public function up(PDO $pdo): void
    {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS user_safety_permission_events (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                target_user_id BIGINT UNSIGNED NOT NULL,
                actor_user_id BIGINT UNSIGNED NULL,
                previous_allowed TINYINT(1) NOT NULL,
                new_allowed TINYINT(1) NOT NULL,
                reason TEXT NULL,
                created_at DATETIME NOT NULL,
                CONSTRAINT fk_safety_permission_target FOREIGN KEY (target_user_id) REFERENCES users(id) ON DELETE CASCADE,
                CONSTRAINT fk_safety_permission_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL,
                KEY idx_safety_permission_target_created (target_user_id, created_at),
                KEY idx_safety_permission_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }
};
