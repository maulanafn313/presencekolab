<?php

namespace App\Services\Attendance;

/** Sejak magang 2026/27 dimulai pada 7 Juli 2026, hari Jumat masuk 07:30 dan pulang 16:30. */
final class WorkHours
{
    public const FRIDAY_START = '07:30';

    public const FRIDAY_END = '16:30';

    public const FRIDAY_RULE_SINCE = '2026-07-07';

    private const REGULAR_START = '08:00';

    private const REGULAR_END = '17:00';

    public static function hasFridayHours(string $date): bool
    {
        return substr($date, 0, 10) >= self::FRIDAY_RULE_SINCE && date('N', strtotime($date)) === '5';
    }

    /** Kondisi SQL yang sama dengan hasFridayHours(). */
    public static function fridaySql(string $column): string
    {
        return "(WEEKDAY($column) = 4 AND DATE($column) >= '".self::FRIDAY_RULE_SINCE."')";
    }

    public static function latestOnTime(string $date, string $regular): string
    {
        return self::hasFridayHours($date) ? self::FRIDAY_START : $regular;
    }

    public static function earliestCheckout(string $date, string $regular): string
    {
        return self::hasFridayHours($date) ? self::FRIDAY_END : $regular;
    }

    public static function defaultStart(string $date): string
    {
        return self::latestOnTime($date, self::REGULAR_START);
    }

    public static function defaultEnd(string $date): string
    {
        return self::earliestCheckout($date, self::REGULAR_END);
    }
}
