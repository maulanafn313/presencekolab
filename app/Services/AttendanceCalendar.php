<?php

namespace App\Services;

use DateTimeImmutable;
use DomainException;
use PDO;

/** Tabel kehadiran bulanan: satu baris per mahasiswa, satu kolom per tanggal. */
final class AttendanceCalendar
{
    private const PRESENT_KET = ['wfo', 'wfa', 'overtime'];

    private const REPORT_REQUIRED_KET = ['wfo', 'wfa'];

    private const WEEKDAYS = [1 => 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'];

    public static function build(PDO $pdo, string $month, string $today): array
    {
        [$from, $to] = self::range($month);
        $rows = function (string $sql, array $params) use ($pdo): array {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        };

        return self::compose([
            'employees' => InternRoster::employees($pdo),
            'attendance' => $rows('SELECT id, user_id, DATE(jam_masuk_iso) AS day, jam_masuk, jam_pulang, status, ket FROM attendance WHERE jam_masuk_iso BETWEEN ? AND ?', ["$from 00:00:00", "$to 23:59:59"]),
            'notes' => $rows('SELECT id, user_id, `date` AS day, type FROM attendance_notes WHERE `date` BETWEEN ? AND ?', [$from, $to]),
            'reports' => $rows("SELECT user_id, report_date AS day FROM daily_reports WHERE report_date BETWEEN ? AND ? AND TRIM(COALESCE(content, '')) <> ''", [$from, $to]),
            'holidays' => array_column($rows('SELECT `date`, name FROM manual_holidays WHERE `date` BETWEEN ? AND ?', [$from, $to]), 'name', 'date'),
            'startup_names' => array_column($rows('SELECT name, name_key FROM startups', []), 'name', 'name_key'),
        ], $month, $today, InternRoster::workingDayChecker($pdo, $from, $to));
    }

    /** @return array{0: string, 1: string} */
    public static function range(string $month): array
    {
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            throw new DomainException('Bulan tidak valid.');
        }
        $first = new DateTimeImmutable("$month-01");

        return [$first->format('Y-m-d'), $first->format('Y-m-t')];
    }

    /** @param callable(int, string): bool $isWorkingDay */
    public static function compose(array $data, string $month, string $today, callable $isWorkingDay): array
    {
        [$from, $to] = self::range($month);
        $attendance = $notes = $reports = $activity = [];
        foreach ($data['attendance'] as $row) {
            $attendance[$row['user_id']][$row['day']] = $row;
            $activity[$row['day']] = true;
        }
        foreach ($data['notes'] as $row) {
            $notes[$row['user_id']][$row['day']] = $row;
            $activity[$row['day']] = true;
        }
        foreach ($data['reports'] as $row) {
            $reports[$row['user_id']][$row['day']] = true;
        }

        $days = [];
        for ($date = new DateTimeImmutable($from); $date->format('Y-m-d') <= $to; $date = $date->modify('+1 day')) {
            $iso = $date->format('Y-m-d');
            $weekday = (int) $date->format('N');
            $days[] = ['date' => $iso, 'day' => (int) $date->format('j'), 'weekday' => self::WEEKDAYS[$weekday], 'off' => $weekday >= 6 || isset($data['holidays'][$iso]), 'holiday' => $data['holidays'][$iso] ?? null];
        }

        $students = [];
        foreach ($data['employees'] as $employee) {
            $id = $employee['id'];
            if ($employee['first_day'] > $to || ($employee['last_day'] !== null && $employee['last_day'] < $from)) {
                continue;
            }
            $cells = [];
            $totals = ['present' => 0, 'late' => 0, 'leave' => 0, 'alpha' => 0, 'missing_reports' => 0];
            foreach ($days as $day) {
                $iso = $day['date'];
                if ($iso < $employee['first_day'] || ($employee['last_day'] !== null && $iso > $employee['last_day'])) {
                    continue;
                }
                $cell = self::cell($iso, $today, $attendance[$id][$iso] ?? null, $notes[$id][$iso] ?? null, isset($reports[$id][$iso]), isset($activity[$iso]), $isWorkingDay($id, $iso));
                if ($cell === null) {
                    continue;
                }
                $cells[$iso] = $cell;
                $totals['present'] += in_array($cell['state'], ['hadir', 'terlambat', 'wfa', 'overtime'], true) ? 1 : 0;
                $totals['late'] += $cell['late'] ? 1 : 0;
                $totals['leave'] += in_array($cell['state'], ['izin', 'sakit'], true) ? 1 : 0;
                $totals['alpha'] += $cell['state'] === 'alpha' ? 1 : 0;
                $totals['missing_reports'] += $cell['report'] === 'missing' ? 1 : 0;
            }
            $clean = StartupDirectory::cleanName((string) $employee['startup']);
            $students[] = [
                'id' => $id, 'nim' => $employee['nim'], 'nama' => $employee['nama'],
                'startup' => $clean === '' ? 'Tanpa Startup' : ($data['startup_names'][StartupDirectory::canonicalKey($clean)] ?? $clean),
                'cells' => (object) $cells, 'totals' => $totals,
            ];
        }

        return ['month' => $month, 'today' => $today, 'days' => $days, 'students' => $students];
    }

    /** Izin/sakit diutamakan, sama seperti perhitungan KPI. */
    private static function cell(string $day, string $today, ?array $attendance, ?array $note, bool $filled, bool $hasActivity, bool $working): ?array
    {
        $base = ['ref' => null, 'ket' => null, 'in' => null, 'out' => null, 'late' => false, 'report' => null];
        if ($note) {
            return ['state' => $note['type'], 'ref' => 'note_'.$note['id'], 'ket' => $note['type']] + $base;
        }
        if ($attendance && in_array($attendance['ket'], self::PRESENT_KET, true)) {
            $late = $attendance['status'] === 'terlambat';
            $state = match (true) {
                $attendance['ket'] === 'overtime' => 'overtime',
                $attendance['ket'] === 'wfa' => 'wfa',
                $late => 'terlambat',
                default => 'hadir',
            };
            $report = null;
            if (in_array($attendance['ket'], self::REPORT_REQUIRED_KET, true)) {
                $report = $filled ? 'ok' : ($day < $today ? 'missing' : null);
            }

            return ['state' => $state, 'ref' => (string) $attendance['id'], 'ket' => $attendance['ket'], 'in' => $attendance['jam_masuk'], 'out' => $attendance['jam_pulang'], 'late' => $late, 'report' => $report] + $base;
        }
        if ($attendance) {
            return ['state' => 'alpha', 'ref' => (string) $attendance['id'], 'ket' => $attendance['ket']] + $base;
        }
        if ($day > $today) {
            return null;
        }
        if (! $working) {
            return ['state' => 'libur'] + $base;
        }
        if ($day === $today) {
            return ['state' => 'belum'] + $base;
        }

        return ['state' => $hasActivity ? 'alpha' : 'nodata'] + $base;
    }
}
