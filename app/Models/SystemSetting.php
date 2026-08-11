<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class SystemSetting extends Model
{
    protected string $table = 'system_settings';
    protected array $fillable = [
        'key_name',
        'value_text',
        'created_at',
        'updated_at',
    ];

    public function allAsMap(): array
    {
        $rows = $this->all('key_name ASC');
        $settings = [];

        foreach ($rows as $row) {
            $settings[(string) $row['key_name']] = (string) ($row['value_text'] ?? '');
        }

        return $settings;
    }

    public function getValue(string $key, string $default = ''): string
    {
        $row = $this->db()->fetch(
            'SELECT value_text FROM system_settings WHERE key_name = :key LIMIT 1',
            ['key' => $key]
        );

        return $row === null ? $default : (string) ($row['value_text'] ?? $default);
    }

    public function defaults(): array
    {
        return [
            'expired_notice_title' => 'Gói sử dụng của bạn đã hết hạn',
            'expired_notice_message' => 'Tài khoản hiện đã hết thời gian sử dụng. Vui lòng liên hệ quản trị viên để được gia hạn và mở lại quyền truy cập.',
            'support_contact_name' => 'Quản trị viên',
            'support_contact_value' => '',
            'support_contact_extra' => '',
            'footer_text' => 'Liên hệ quản trị viên nếu cần hỗ trợ gia hạn, cấu hình hoặc xử lý sự cố hệ thống.',
            'safety_safe_hourly_limit' => '8',
            'safety_safe_daily_limit' => '40',
            'safety_safe_min_gap_minutes' => '8',
            'safety_elevated_hourly_limit' => '10',
            'safety_elevated_daily_limit' => '80',
            'safety_elevated_min_gap_minutes' => '5',
            'safety_risk_min_gap_minutes' => '1',
            'safety_admin_self_override_enabled' => '1',
            'safety_circuit_breaker_error_count' => '3',
            'safety_circuit_breaker_window_minutes' => '15',
            'safety_circuit_breaker_cooldown_minutes' => '180',
            'safety_audit_retention_days' => '30',
        ];
    }

    public function resolvedMap(): array
    {
        return array_merge($this->defaults(), $this->allAsMap());
    }

    public function saveMany(array $values): void
    {
        $existing = $this->allAsMap();
        $now = gmdate('Y-m-d H:i:s');

        foreach ($values as $key => $value) {
            $payload = [
                'value_text' => (string) $value,
                'updated_at' => $now,
            ];

            if (array_key_exists($key, $existing)) {
                $this->db()->update($this->table, $payload, 'key_name = :key', ['key' => $key]);
                continue;
            }

            $this->create([
                'key_name' => (string) $key,
                'value_text' => (string) $value,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
