<?php

declare(strict_types=1);

use App\Core\Migration;

return new class extends Migration
{
    public string $version = '8';
    public string $name = 'add_super_admin_subscription_support';

    public function up(PDO $pdo): void
    {
        $userColumns = $pdo->query('SHOW COLUMNS FROM users LIKE \'subscription_expires_at\'')->fetchAll();
        if ($userColumns === []) {
            $pdo->exec(
                'ALTER TABLE users
                 ADD COLUMN subscription_expires_at DATETIME NULL AFTER status'
            );
        }

        $roleRows = $pdo->query('SHOW COLUMNS FROM users LIKE \'role\'')->fetchAll();
        if ($roleRows !== []) {
            $pdo->exec(
                'ALTER TABLE users
                 MODIFY COLUMN role VARCHAR(40) NOT NULL DEFAULT \'admin\''
            );
        }

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS user_subscription_adjustments (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                target_user_id BIGINT UNSIGNED NOT NULL,
                actor_user_id BIGINT UNSIGNED NOT NULL,
                delta_days INT NOT NULL,
                previous_expires_at DATETIME NULL,
                new_expires_at DATETIME NULL,
                note TEXT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                CONSTRAINT fk_subscription_adjustments_target FOREIGN KEY (target_user_id) REFERENCES users(id) ON DELETE CASCADE,
                CONSTRAINT fk_subscription_adjustments_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE CASCADE,
                KEY idx_subscription_adjustments_target (target_user_id, created_at),
                KEY idx_subscription_adjustments_actor (actor_user_id, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS system_settings (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                key_name VARCHAR(120) NOT NULL,
                value_text LONGTEXT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE KEY uq_system_settings_key (key_name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $superAdminEmail = mb_strtolower(trim((string) env('SUPER_ADMIN_EMAIL', '')));

        if ($superAdminEmail !== '') {
            $pdo->prepare(
                'UPDATE users
                 SET role = CASE WHEN LOWER(email) = :email THEN \'super_admin\' ELSE \'admin\' END'
            )->execute(['email' => $superAdminEmail]);
        } else {
            $pdo->exec(
                'UPDATE users
                 SET role = CASE WHEN role = \'super_admin\' THEN \'super_admin\' ELSE \'admin\' END'
            );
        }

        $defaults = [
            'expired_notice_title' => 'Gói sử dụng của bạn đã hết hạn',
            'expired_notice_message' => 'Tài khoản hiện đã hết thời gian sử dụng. Vui lòng liên hệ quản trị viên để được gia hạn và mở lại quyền truy cập.',
            'support_contact_name' => 'Quản trị viên',
            'support_contact_value' => '',
            'support_contact_extra' => '',
        ];

        $insert = $pdo->prepare(
            'INSERT INTO system_settings (key_name, value_text, created_at, updated_at)
             VALUES (:key_name, :value_text, UTC_TIMESTAMP(), UTC_TIMESTAMP())'
        );

        foreach ($defaults as $key => $value) {
            $exists = $pdo->prepare('SELECT id FROM system_settings WHERE key_name = :key LIMIT 1');
            $exists->execute(['key' => $key]);

            if ($exists->fetch() !== false) {
                continue;
            }

            $insert->execute([
                'key_name' => $key,
                'value_text' => $value,
            ]);
        }
    }
};
