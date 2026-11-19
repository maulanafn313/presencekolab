<?php

namespace App\Services;

use PDO;

/**
 * Daftar mahasiswa aktif yang perlu diingatkan lewat grup WhatsApp.
 *
 * Hanya memuat nama, startup, dan jumlah tunggakan karena pesannya dibaca satu grup.
 */
final class AttendanceReminder
{
    private const REPORT_REQUIRED_KET = ['wfo', 'wfa'];

    private const MAX_LOOKBACK_DAYS = 60;

    public static function build(PDO $pdo, string $today, string $activeUsersSql): array
    {
        $maxDaysBack = max(0, min(self::MAX_LOOKBACK_DAYS, ReportReview::maxDaysBack($pdo)));
        $from = date('Y-m-d', strtotime("$today -$maxDaysBack days"));
        $range = [$from, $today];

        $rows = function (string $sql, array $params = []) use ($pdo): array {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        };

        $holidays = array_fill_keys(array_column($rows('SELECT `date` FROM manual_holidays WHERE `date` BETWEEN ? AND ?', $range), 'date'), true);
        $schedules = [];
        foreach ($rows('SELECT user_id, day_of_week, is_working_day FROM employee_work_schedule') as $row) {
            $schedules[$row['user_id']][$row['day_of_week']] = ['is_working_day' => (bool) $row['is_working_day']];
        }
        $startDates = [];
        foreach ($rows("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'work_start_date_user_%'") as $row) {
            $startDates[(int) substr($row['setting_key'], strlen('work_start_date_user_'))] = $row['setting_value'];
        }

        return self::summarize(
            $rows("SELECT id, nama, startup, created_at FROM users WHERE role = 'pegawai' AND $activeUsersSql ORDER BY nama"),
            $rows('SELECT user_id, DATE(jam_masuk_iso) AS day, ket FROM attendance WHERE jam_masuk_iso BETWEEN ? AND ?', ["$from 00:00:00", "$today 23:59:59"]),
            $rows('SELECT user_id, `date` AS day FROM attendance_notes WHERE `date` BETWEEN ? AND ?', $range),
            $rows("SELECT user_id, report_date AS day FROM daily_reports WHERE report_date BETWEEN ? AND ? AND TRIM(COALESCE(content, '')) <> ''", $range),
            fn (int $userId, string $date) => isEmployeeWorkingDay($pdo, $userId, $date, $schedules[$userId] ?? [], $holidays),
            $startDates,
            $today,
            $maxDaysBack,
        );
    }

    /**
     * Hari ini belum ditagih laporannya karena biasanya baru diisi saat pulang.
     *
     * @param  callable(int, string): bool  $isWorkingDay
     * @param  array<int, string>  $startDates
     */
    public static function summarize(array $employees, array $attendance, array $notes, array $reports, callable $isWorkingDay, array $startDates, string $today, int $maxDaysBack): array
    {
        $present = $leave = $filled = [];
        foreach ($attendance as $row) {
            $present[$row['user_id']][$row['day']] = $row['ket'];
        }
        foreach ($notes as $row) {
            $leave[$row['user_id']][$row['day']] = true;
        }
        foreach ($reports as $row) {
            $filled[$row['user_id']][$row['day']] = true;
        }

        $students = [];
        foreach ($employees as $employee) {
            $id = (int) $employee['id'];
            $start = $startDates[$id] ?? substr((string) $employee['created_at'], 0, 10);
            $owes = fn (string $day) => $day >= $start && $isWorkingDay($id, $day) && ! isset($leave[$id][$day]);

            $absentToday = $owes($today) && ! isset($present[$id][$today]);
            $missingReports = 0;
            for ($back = 1; $back <= $maxDaysBack; $back++) {
                $day = date('Y-m-d', strtotime("$today -$back days"));
                if ($owes($day) && in_array($present[$id][$day] ?? null, self::REPORT_REQUIRED_KET, true) && ! isset($filled[$id][$day])) {
                    $missingReports++;
                }
            }

            if ($absentToday || $missingReports > 0) {
                $students[] = [
                    'nama' => $employee['nama'],
                    'startup' => $employee['startup'],
                    'absent_today' => $absentToday,
                    'missing_reports' => $missingReports,
                ];
            }
        }

        return ['date' => $today, 'max_days_back' => $maxDaysBack, 'students' => $students];
    }
}
