<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\CronExpression;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

class ScheduleBuilderService
{
    public function __construct(private readonly CronExpression $cron)
    {
    }

    public function defaultFormData(string $timezone): array
    {
        return [
            'schedule_type' => 'daily_times',
            'timezone' => $timezone,
            'cron_expression' => '0 8 * * *',
            'builder' => [
                'interval_minutes' => 15,
                'interval_hours' => 4,
                'interval_hour_minute' => '00',
                'daily_times' => ['08:00', '12:00', '20:00'],
                'weekly_days' => ['1', '2', '3', '4', '5'],
                'weekly_times' => ['09:00', '13:00', '17:00'],
            ],
        ];
    }

    public function formDataFromSchedule(?array $schedule, string $timezone): array
    {
        $defaults = $this->defaultFormData($timezone);

        if ($schedule === null) {
            return $defaults;
        }

        $type = trim((string) ($schedule['schedule_type'] ?? ''));
        $type = $type !== '' ? $type : 'advanced';
        $config = $this->decodeConfig((string) ($schedule['schedule_config_json'] ?? ''));
        $builder = $defaults['builder'];

        if ($type === 'interval_minutes') {
            $builder['interval_minutes'] = (int) ($config['interval_minutes'] ?? $builder['interval_minutes']);
        } elseif ($type === 'interval_hours') {
            $builder['interval_hours'] = (int) ($config['interval_hours'] ?? $builder['interval_hours']);
            $builder['interval_hour_minute'] = $this->formatMinute((int) ($config['minute'] ?? 0));
        } elseif ($type === 'daily_times') {
            $builder['daily_times'] = $this->normalizeTimeList($config['times'] ?? $builder['daily_times']);
        } elseif ($type === 'weekly_times') {
            $builder['weekly_days'] = $this->normalizeWeekdayList($config['days'] ?? $builder['weekly_days']);
            $builder['weekly_times'] = $this->normalizeTimeList($config['times'] ?? $builder['weekly_times']);
        }

        return [
            'schedule_type' => $type,
            'timezone' => (string) ($schedule['timezone'] ?? $timezone),
            'cron_expression' => (string) ($schedule['cron_expression'] ?? $defaults['cron_expression']),
            'builder' => $builder,
        ];
    }

    public function buildFromPayload(array $payload): array
    {
        $type = trim((string) ($payload['schedule_type'] ?? 'daily_times'));

        return match ($type) {
            'interval_minutes' => $this->buildIntervalMinutes($payload),
            'interval_hours' => $this->buildIntervalHours($payload),
            'daily_times' => $this->buildDailyTimes($payload),
            'weekly_times' => $this->buildWeeklyTimes($payload),
            'advanced' => $this->buildAdvanced($payload),
            default => throw new InvalidArgumentException('Kiểu lịch không hợp lệ.'),
        };
    }

    public function preview(array $payload, string $timezone, int $count = 5): array
    {
        $built = $this->buildFromPayload($payload);

        return array_merge($built, [
            'next_runs' => $this->previewNextRuns($built['cron_expression'], $timezone, $count),
            'summary' => $this->describe(
                $built['schedule_type'],
                $built['schedule_config'],
                $built['cron_expression']
            ),
        ]);
    }

    public function summaryFromSchedule(array $schedule): string
    {
        $type = trim((string) ($schedule['schedule_type'] ?? ''));
        $type = $type !== '' ? $type : 'advanced';
        $cronExpression = (string) ($schedule['cron_expression'] ?? '');
        $config = $this->decodeConfig((string) ($schedule['schedule_config_json'] ?? ''));

        return $this->describe($type, $config, $cronExpression);
    }

    public function modeOptions(): array
    {
        return [
            ['value' => 'interval_minutes', 'label' => 'Mỗi X phút'],
            ['value' => 'interval_hours', 'label' => 'Mỗi X giờ'],
            ['value' => 'daily_times', 'label' => 'Mỗi ngày theo giờ'],
            ['value' => 'weekly_times', 'label' => 'Theo ngày trong tuần'],
            ['value' => 'advanced', 'label' => 'Nâng cao'],
        ];
    }

    private function buildIntervalMinutes(array $payload): array
    {
        $minutes = (int) ($payload['interval_minutes'] ?? 0);

        if ($minutes < 5 || $minutes > 59) {
            throw new InvalidArgumentException('Lịch mỗi X phút chỉ hỗ trợ từ 5 đến 59 phút.');
        }

        $config = ['interval_minutes' => $minutes];

        return $this->wrap('interval_minutes', $config, '*/' . $minutes . ' * * * *');
    }

    private function buildIntervalHours(array $payload): array
    {
        $hours = (int) ($payload['interval_hours'] ?? 0);
        $minute = $this->normalizeMinute($payload['interval_hour_minute'] ?? '00');

        if ($hours < 1 || $hours > 23) {
            throw new InvalidArgumentException('Lịch mỗi X giờ chỉ hỗ trợ từ 1 đến 23 giờ.');
        }

        $config = [
            'interval_hours' => $hours,
            'minute' => $minute,
        ];

        return $this->wrap('interval_hours', $config, $minute . ' */' . $hours . ' * * *');
    }

    private function buildDailyTimes(array $payload): array
    {
        $times = $this->normalizeTimeList($payload['daily_times'] ?? []);

        if ($times === []) {
            throw new InvalidArgumentException('Bạn cần chọn ít nhất 1 mốc giờ trong ngày.');
        }

        $config = ['times' => $times];
        $expressions = array_map(
            static fn (string $time): string => self::timeToCron($time, '* * *'),
            $times
        );

        return $this->wrap('daily_times', $config, implode(' | ', $expressions));
    }

    private function buildWeeklyTimes(array $payload): array
    {
        $days = $this->normalizeWeekdayList($payload['weekly_days'] ?? []);
        $times = $this->normalizeTimeList($payload['weekly_times'] ?? []);

        if ($days === []) {
            throw new InvalidArgumentException('Bạn cần chọn ít nhất 1 ngày trong tuần.');
        }

        if ($times === []) {
            throw new InvalidArgumentException('Bạn cần chọn ít nhất 1 mốc giờ trong tuần.');
        }

        $dayExpression = implode(',', $days);
        $config = [
            'days' => $days,
            'times' => $times,
        ];
        $expressions = array_map(
            static fn (string $time): string => self::timeToCron($time, '* * ' . $dayExpression),
            $times
        );

        return $this->wrap('weekly_times', $config, implode(' | ', $expressions));
    }

    private function buildAdvanced(array $payload): array
    {
        $cronExpression = trim((string) ($payload['cron_expression'] ?? ''));

        if ($cronExpression === '') {
            throw new InvalidArgumentException('Cron expression là bắt buộc ở chế độ nâng cao.');
        }

        $this->cron->validate($cronExpression);

        return $this->wrap('advanced', ['cron_expression' => $cronExpression], $cronExpression);
    }

    private function wrap(string $type, array $config, string $cronExpression): array
    {
        $this->cron->validate($cronExpression);

        return [
            'schedule_type' => $type,
            'schedule_config' => $config,
            'schedule_config_json' => json_encode($config, JSON_UNESCAPED_UNICODE),
            'cron_expression' => $cronExpression,
        ];
    }

    private function previewNextRuns(string $cronExpression, string $timezone, int $count): array
    {
        $runs = [];
        $cursor = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        for ($i = 0; $i < $count; $i++) {
            $cursor = $this->cron->nextRun($cronExpression, $cursor, $timezone);
            $runs[] = $cursor->format('d/m/Y H:i');
        }

        return $runs;
    }

    private function describe(string $type, array $config, string $cronExpression): string
    {
        return match ($type) {
            'interval_minutes' => 'Mỗi ' . (int) ($config['interval_minutes'] ?? 0) . ' phút',
            'interval_hours' => 'Mỗi ' . (int) ($config['interval_hours'] ?? 0) . ' giờ, vào phút ' . $this->formatMinute((int) ($config['minute'] ?? 0)),
            'daily_times' => 'Mỗi ngày lúc ' . implode(', ', $this->normalizeTimeList($config['times'] ?? [])),
            'weekly_times' => $this->describeWeekly($config),
            default => 'Cron tùy chỉnh: ' . $cronExpression,
        };
    }

    private function describeWeekly(array $config): string
    {
        $days = $this->normalizeWeekdayList($config['days'] ?? []);
        $times = $this->normalizeTimeList($config['times'] ?? []);
        $dayLabels = array_map(fn (string $day): string => $this->weekdayLabel((int) $day), $days);

        return 'Mỗi ' . implode(', ', $dayLabels) . ' lúc ' . implode(', ', $times);
    }

    private function decodeConfig(string $config): array
    {
        if ($config === '') {
            return [];
        }

        $decoded = json_decode($config, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function normalizeMinute(mixed $value): int
    {
        $minute = is_string($value) && str_contains($value, ':')
            ? (int) explode(':', $value, 2)[1]
            : (int) $value;

        if ($minute < 0 || $minute > 59) {
            throw new InvalidArgumentException('Phút chạy không hợp lệ.');
        }

        return $minute;
    }

    private function normalizeTimeList(mixed $value): array
    {
        $items = is_array($value) ? $value : explode(',', (string) $value);
        $times = [];

        foreach ($items as $item) {
            $item = trim((string) $item);

            if ($item === '') {
                continue;
            }

            if (!preg_match('/^(2[0-3]|[01]?\d):([0-5]\d)$/', $item, $matches)) {
                throw new InvalidArgumentException('Mốc giờ không hợp lệ: ' . $item);
            }

            $times[] = sprintf('%02d:%02d', (int) $matches[1], (int) $matches[2]);
        }

        $times = array_values(array_unique($times));
        sort($times);

        return $times;
    }

    private function normalizeWeekdayList(mixed $value): array
    {
        $items = is_array($value) ? $value : explode(',', (string) $value);
        $days = [];

        foreach ($items as $item) {
            $day = trim((string) $item);

            if ($day === '') {
                continue;
            }

            $dayNumber = (int) $day;
            if ($dayNumber < 0 || $dayNumber > 6) {
                throw new InvalidArgumentException('Ngày trong tuần không hợp lệ.');
            }

            $days[] = (string) $dayNumber;
        }

        $days = array_values(array_unique($days));
        sort($days);

        return $days;
    }

    private function formatMinute(int $minute): string
    {
        return str_pad((string) $minute, 2, '0', STR_PAD_LEFT);
    }

    private function weekdayLabel(int $day): string
    {
        return match ($day) {
            0 => 'Chủ nhật',
            1 => 'Thứ 2',
            2 => 'Thứ 3',
            3 => 'Thứ 4',
            4 => 'Thứ 5',
            5 => 'Thứ 6',
            6 => 'Thứ 7',
        };
    }

    private static function timeToCron(string $time, string $tail): string
    {
        [$hour, $minute] = explode(':', $time, 2);

        return (int) $minute . ' ' . (int) $hour . ' ' . $tail;
    }
}
