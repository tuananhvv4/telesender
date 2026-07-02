<?php

declare(strict_types=1);

use App\Core\Migration;

return new class extends Migration
{
    public string $version = '6';
    public string $name = 'add_custom_emojis_table';

    public function up(PDO $pdo): void
    {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS custom_emojis (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT UNSIGNED NOT NULL,
                name VARCHAR(120) NOT NULL,
                slug VARCHAR(120) NOT NULL,
                emoji_identifier VARCHAR(40) NOT NULL,
                fallback_emoji VARCHAR(32) NOT NULL,
                keywords VARCHAR(255) NULL,
                notes TEXT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                CONSTRAINT fk_custom_emojis_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                UNIQUE KEY uq_custom_emojis_user_slug (user_id, slug),
                KEY idx_custom_emojis_active (user_id, is_active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }
};
