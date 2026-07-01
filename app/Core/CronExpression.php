<?php

declare(strict_types=1);

namespace App\Core;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

class CronExpression
{
    public function isDue(string $expression, DateTimeImmutable $date): bool
    {
        $parts = $this->parse($expression);
        return $this->matches($parts, $date);
    }

    public function nextRun(string $expression, DateTimeImmutable $after, string $timezone): DateTimeImmutable
    {
        $tz = new DateTimeZone($timezone);
        $parts = $this->parse($expression);
        $candidate = $after->setTimezone($tz)->setTime(
            (int) $after->setTimezone($tz)->format('H'),
            (int) $after->setTimezone($tz)->format('i'),
            0
        )->modify('+1 minute');

        for ($i = 0; $i < 527040; $i++) {
            if ($this->matches($parts, $candidate)) {
                return $candidate;
            }

            $candidate = $candidate->modify('+1 minute');
        }

        throw new InvalidArgumentException('Không tìm được lần chạy kế tiếp cho cron expression.');
    }

    public function validate(string $expression): void
    {
        $this->parse($expression);
    }

    private function parse(string $expression): array
    {
        $fields = preg_split('/\s+/', trim($expression)) ?: [];

        if (count($fields) !== 5) {
            throw new InvalidArgumentException('Cron expression phải gồm 5 phần: minute hour day month weekday.');
        }

        return [
            'minute' => $this->expandField($fields[0], 0, 59),
            'hour' => $this->expandField($fields[1], 0, 23),
            'day' => $this->expandField($fields[2], 1, 31),
            'month' => $this->expandField($fields[3], 1, 12),
            'weekday' => $this->expandField($fields[4], 0, 6),
        ];
    }

    private function matches(array $parts, DateTimeImmutable $date): bool
    {
        $minute = (int) $date->format('i');
        $hour = (int) $date->format('G');
        $day = (int) $date->format('j');
        $month = (int) $date->format('n');
        $weekday = (int) $date->format('w');

        $dayStar = $parts['day']['all'];
        $weekdayStar = $parts['weekday']['all'];
        $dayMatch = in_array($day, $parts['day']['values'], true);
        $weekdayMatch = in_array($weekday, $parts['weekday']['values'], true);
        $dayRule = (!$dayStar && !$weekdayStar) ? ($dayMatch || $weekdayMatch) : ($dayMatch && $weekdayMatch);

        return in_array($minute, $parts['minute']['values'], true)
            && in_array($hour, $parts['hour']['values'], true)
            && in_array($month, $parts['month']['values'], true)
            && $dayRule;
    }

    private function expandField(string $field, int $min, int $max): array
    {
        $isAll = trim($field) === '*';
        $values = [];

        foreach (explode(',', $field) as $segment) {
            $segment = trim($segment);

            if ($segment === '*') {
                $values = range($min, $max);
                continue;
            }

            $step = 1;
            if (str_contains($segment, '/')) {
                [$segment, $stepValue] = explode('/', $segment, 2);
                $step = max(1, (int) $stepValue);
            }

            if ($segment === '*') {
                $start = $min;
                $end = $max;
            } elseif (str_contains($segment, '-')) {
                [$start, $end] = array_map('intval', explode('-', $segment, 2));
            } else {
                $start = (int) $segment;
                $end = (int) $segment;
            }

            if ($start < $min || $end > $max || $start > $end) {
                throw new InvalidArgumentException('Cron expression không hợp lệ: ' . $field);
            }

            for ($i = $start; $i <= $end; $i += $step) {
                $values[] = $i;
            }
        }

        $values = array_values(array_unique($values));
        sort($values);

        if ($values === []) {
            throw new InvalidArgumentException('Cron expression không hợp lệ: ' . $field);
        }

        return [
            'all' => $isAll,
            'values' => $values,
        ];
    }
}
