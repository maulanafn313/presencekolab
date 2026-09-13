<?php

namespace App\Services;

final class SettingVisibility
{
    // New settings are private until explicitly reviewed for client use.
    public const CLIENT_KEYS = [
        'app_name', 'app_timezone', 'maintenance_mode',
        'attendance_period_start', 'attendance_period_end', 'max_ontime_hour', 'min_checkin_hour', 'min_checkout_hour',
        'max_daily_report_days_back', 'max_monthly_report_months_back', 'monthly_report_end_year',
        'face_recognition_threshold', 'face_recognition_min_confidence',
        'face_recognition_input_size', 'face_recognition_score_threshold',
        'face_recognition_quality_threshold', 'geocode_timeout', 'geocode_accuracy_radius',
        'wfo_address', 'wfo_lat', 'wfo_lng', 'wfo_radius_m', 'max_radius',
        'wfo_mode', 'wfo_detection_method', 'wfo_require_wifi',
        'wfo_wifi_ssid', 'wfo_wifi_ssids', 'wfo_wifi_bssid',
        'wfo_wifi_range_start', 'wfo_wifi_range_end',
        'kpi_alpha_score', 'kpi_izin_sakit_score', 'kpi_overtime_bonus',
        'kpi_late_penalty_per_minute', 'kpi_late_max_deduction', 'kpi_late_tolerance_minutes',
        'help_wa_number', 'help_wa_message',
    ];

    public static function canRead(string $key, bool $isAdmin): bool
    {
        return $isAdmin || in_array($key, self::CLIENT_KEYS, true);
    }
}
