<?php

declare(strict_types=1);

use App\Core\Migration;

return new class extends Migration
{
    public string $version = '11';
    public string $name = 'add_footer_text_setting';

    public function up(PDO $pdo): void
    {
        $statement = $pdo->prepare('SELECT COUNT(*) FROM system_settings WHERE key_name = :key');
        $statement->execute(['key' => 'footer_text']);

        if ((int) $statement->fetchColumn() > 0) {
            return;
        }

        $insert = $pdo->prepare(
            'INSERT INTO system_settings (key_name, value_text, created_at, updated_at)
             VALUES (:key_name, :value_text, UTC_TIMESTAMP(), UTC_TIMESTAMP())'
        );

        $insert->execute([
            'key_name' => 'footer_text',
            'value_text' => 'Liên hệ quản trị viên nếu cần hỗ trợ gia hạn, cấu hình hoặc xử lý sự cố hệ thống.',
        ]);
    }
};
