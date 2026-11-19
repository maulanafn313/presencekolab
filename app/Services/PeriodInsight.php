<?php

namespace App\Services;

use DateInterval;
use DatePeriod;
use DateTimeImmutable;
use DomainException;
use PDO;

/**
 * Laporan ringkasan satu periode: kualitas data, perbandingan dengan periode
 * sebelumnya, peringkat startup, mahasiswa berisiko, disiplin laporan, dan pola kehadiran.
 */
final class PeriodInsight
{
    private const MAX_DAYS = 366;

    private const PRESENT_KET = ['wfo', 'wfa', 'overtime'];

    private const REPORT_REQUIRED_KET = ['wfo', 'wfa'];

    private const ALPHA_STREAK = 2;

    private const LATE_RISE_POINTS = 15.0;

    private const ATTENDANCE_DROP_POINTS = 10.0;

    private const MIN_DAYS_TO_COMPARE = 3;

    private const SAMPLE_LIMIT = 30;

    private const WEEKDAYS = [1 => 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];

    private const HOUR_BUCKETS = ['< 08:00', '08:00-08:59', '09:00-09:59', '10:00-10:59', '>= 11:00'];

    /**
     * Hari ini tidak dihitung karena presensi dan laporannya belum selesai.
     *
     * @return array{start: string, end: string, requested_end: string, previous_start: string, previous_end: string}
     */
    public static function periods(string $start, string $end, string $today): array
    {
        if (! ReportReview::isValidDate($start) || ! ReportReview::isValidDate($end)) {
            throw new DomainException('Tanggal periode tidak valid.');
        }
        if ($start > $end) {
            throw new DomainException('Tanggal mulai tidak boleh setelah tanggal akhir.');
        }
        $from = new DateTimeImmutable($start);
        if ((int) $from->diff(new DateTimeImmutable($end))->days >= self::MAX_DAYS) {
            throw new DomainException('Periode laporan maksimal 1 tahun.');
        }
        $yesterday = (new DateTimeImmutable($today))->modify('-1 day')->format('Y-m-d');
        $cappedEnd = min($end, $yesterday);
        if ($start > $cappedEnd) {
            throw new DomainException('Periode ini belum memiliki data yang selesai.');
        }

        if ($from->format('j') === '1' && substr($start, 0, 7) === substr($end, 0, 7)) {
            $previousStart = $from->modify('first day of previous month');
            $previousEnd = $from->modify('last day of previous month');
        } else {
            $length = (int) $from->diff(new DateTimeImmutable($cappedEnd))->days;
            $previousEnd = $from->modify('-1 day');
            $previousStart = $previousEnd->modify("-$length days");
        }

        return [
            'start' => $start,
            'end' => $cappedEnd,
            'requested_end' => $end,
            'previous_start' => $previousStart->format('Y-m-d'),
            'previous_end' => $previousEnd->format('Y-m-d'),
        ];
    }

    public static function build(PDO $pdo, string $start, string $end, string $today): array
    {
        $period = self::periods($start, $end, $today);
        $range = [$period['previous_start'], $period['end']];
        $rows = function (string $sql, array $params = []) use ($pdo): array {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        };

        return self::analyze([
            'employees' => InternRoster::employees($pdo),
            'attendance' => $rows('SELECT user_id, DATE(jam_masuk_iso) AS day, HOUR(jam_masuk_iso) AS hour, status, ket, jam_pulang FROM attendance WHERE jam_masuk_iso BETWEEN ? AND ?', ["{$range[0]} 00:00:00", "{$range[1]} 23:59:59"]),
            'notes' => $rows('SELECT user_id, `date` AS day FROM attendance_notes WHERE `date` BETWEEN ? AND ?', $range),
            'reports' => $rows("SELECT user_id, report_date AS day, DATE(created_at) AS created_day, (TRIM(COALESCE(content, '')) <> '') AS filled FROM daily_reports WHERE report_date BETWEEN ? AND ?", $range),
            'startup_names' => array_column($rows('SELECT name, name_key FROM startups'), 'name', 'name_key'),
        ], $period, InternRoster::workingDayChecker($pdo, $range[0], $range[1]));
    }

    /** @param callable(int, string): bool $isWorkingDay */
    public static function analyze(array $data, array $period, callable $isWorkingDay): array
    {
        $attendance = $notes = $reports = $activity = [];
        foreach ($data['attendance'] as $row) {
            $attendance[$row['user_id']][$row['day']] = $row;
            $activity[$row['day']] = true;
        }
        foreach ($data['notes'] as $row) {
            $notes[$row['user_id']][$row['day']] = true;
            $activity[$row['day']] = true;
        }
        foreach ($data['reports'] as $row) {
            $reports[$row['user_id']][$row['day']] = $row;
        }

        // Hari kerja tanpa satu pun presensi biasanya libur yang belum dicatat, jadi tidak dihitung alpha.
        $allDays = self::dates($period['start'], $period['end']);
        $days = array_values(array_filter($allDays, fn ($day) => isset($activity[$day])));
        $gapDays = array_values(array_filter($allDays, fn ($day) => ! isset($activity[$day]) && $isWorkingDay(0, $day)));
        if ($days === []) {
            throw new DomainException('Belum ada data presensi pada periode ini.');
        }
        $previousDays = array_values(array_filter(self::dates($period['previous_start'], $period['previous_end']), fn ($day) => isset($activity[$day])));

        $pattern = array_fill_keys(array_keys(self::WEEKDAYS), ['total' => 0, 'late' => 0, 'hours' => array_fill(0, count(self::HOUR_BUCKETS), 0)]);
        $students = [];
        foreach ($data['employees'] as $employee) {
            $current = self::measure($employee, $days, $attendance, $notes, $reports, $isWorkingDay, $pattern);
            if ($current['expected'] === 0) {
                continue;
            }
            $ignored = null;
            $previous = self::measure($employee, $previousDays, $attendance, $notes, $reports, $isWorkingDay, $ignored);
            $students[] = [
                'id' => $employee['id'],
                'nim' => $employee['nim'],
                'nama' => $employee['nama'],
                'startup' => self::startupName($employee['startup'], $data['startup_names']),
                'active' => $employee['last_day'] === null,
                'current' => $current + self::rates($current),
                'previous' => $previous + self::rates($previous),
            ];
        }
        if ($students === []) {
            throw new DomainException('Belum ada mahasiswa yang terdaftar pada periode ini.');
        }

        $total = self::sum(array_column($students, 'current'));
        $previousTotal = self::sum(array_column($students, 'previous'));
        $startups = self::startups($students);
        $risks = self::risks($students);
        $patternRows = self::patternRows($pattern);
        $quality = self::quality($data, $period, $students, $attendance, $notes, $gapDays);
        $summary = self::summary($total, $previousTotal);

        return [
            'period' => $period + ['capped' => $period['end'] !== $period['requested_end'], 'has_previous' => $previousTotal['expected'] > 0],
            'student_count' => count($students),
            'totals' => $total,
            'summary' => $summary,
            'conclusions' => self::conclusions($summary, $total, $startups, $risks, $patternRows, $quality, $previousTotal['expected'] > 0),
            'quality' => $quality,
            'startups' => $startups,
            'risks' => $risks,
            'discipline' => self::discipline($students),
            'pattern' => ['hours' => self::HOUR_BUCKETS, 'rows' => $patternRows],
        ];
    }

    private static function measure(array $employee, array $days, array $attendance, array $notes, array $reports, callable $isWorkingDay, ?array &$pattern): array
    {
        $id = $employee['id'];
        $m = ['expected' => 0, 'present' => 0, 'on_time' => 0, 'late' => 0, 'leave' => 0, 'alpha' => 0, 'report_due' => 0, 'report_filled' => 0, 'report_timely' => 0, 'alpha_streak' => 0];
        $streak = 0;
        foreach ($days as $day) {
            if ($day < $employee['first_day'] || ($employee['last_day'] !== null && $day > $employee['last_day']) || ! $isWorkingDay($id, $day)) {
                continue;
            }
            $m['expected']++;
            $row = $attendance[$id][$day] ?? null;
            if (isset($notes[$id][$day])) {
                $m['leave']++;
                $streak = 0;
            } elseif ($row && in_array($row['ket'], self::PRESENT_KET, true)) {
                $streak = 0;
                $late = $row['status'] === 'terlambat';
                $m['present']++;
                $m[$late ? 'late' : 'on_time']++;
                if ($pattern !== null) {
                    $weekday = (int) (new DateTimeImmutable($day))->format('N');
                    $pattern[$weekday]['total']++;
                    if ($late) {
                        $pattern[$weekday]['late']++;
                        $pattern[$weekday]['hours'][max(0, min(4, (int) $row['hour'] - 7))]++;
                    }
                }
                if (in_array($row['ket'], self::REPORT_REQUIRED_KET, true)) {
                    $m['report_due']++;
                    $report = $reports[$id][$day] ?? null;
                    if ($report && (int) $report['filled'] === 1) {
                        $m['report_filled']++;
                        $lag = (int) (new DateTimeImmutable($day))->diff(new DateTimeImmutable($report['created_day']))->format('%r%a');
                        $m['report_timely'] += $lag <= 1 ? 1 : 0;
                    }
                }
            } else {
                $m['alpha']++;
                $m['alpha_streak'] = max($m['alpha_streak'], ++$streak);
            }
        }

        return $m;
    }

    private static function rates(array $m): array
    {
        return [
            'attendance_rate' => self::percent($m['present'], $m['expected']),
            'late_rate' => self::percent($m['late'], $m['present']),
            'alpha_rate' => self::percent($m['alpha'], $m['expected']),
            'report_rate' => self::percent($m['report_filled'], $m['report_due']),
            'timely_rate' => self::percent($m['report_timely'], $m['report_filled']),
        ];
    }

    private static function sum(array $metrics): array
    {
        $total = [];
        foreach (['expected', 'present', 'on_time', 'late', 'leave', 'alpha', 'report_due', 'report_filled', 'report_timely'] as $key) {
            $total[$key] = array_sum(array_column($metrics, $key));
        }

        return $total + self::rates($total);
    }

    private static function percent(int $part, int $whole): ?float
    {
        return $whole > 0 ? round($part / $whole * 100, 1) : null;
    }

    private static function startupName(?string $raw, array $names): string
    {
        $clean = StartupDirectory::cleanName((string) $raw);

        return $clean === '' ? 'Tanpa Startup' : ($names[StartupDirectory::canonicalKey($clean)] ?? $clean);
    }

    private static function summary(array $total, array $previous): array
    {
        $card = function (string $key, string $label, bool $higherIsBetter) use ($total, $previous) {
            $value = $total[$key];
            $before = $previous[$key];
            $delta = $value !== null && $before !== null ? round($value - $before, 1) : null;

            return [
                'key' => $key, 'label' => $label, 'value' => $value, 'previous' => $before, 'delta' => $delta,
                'better' => $delta === null || $delta == 0.0 ? null : ($delta > 0) === $higherIsBetter,
            ];
        };

        return [
            $card('attendance_rate', 'Kehadiran', true),
            $card('late_rate', 'Terlambat', false),
            $card('alpha_rate', 'Alpha', false),
            $card('report_rate', 'Laporan Terisi', true),
            $card('timely_rate', 'Laporan Tepat Waktu', true),
        ];
    }

    private static function startups(array $students): array
    {
        $groups = [];
        foreach ($students as $student) {
            $groups[$student['startup']][] = $student;
        }

        $rows = [];
        foreach ($groups as $name => $members) {
            $current = self::sum(array_column($members, 'current'));
            $previous = self::sum(array_column($members, 'previous'));
            $rows[] = ['name' => (string) $name, 'students' => count($members), 'alpha' => $current['alpha'], 'attendance_delta' => $current['attendance_rate'] !== null && $previous['attendance_rate'] !== null ? round($current['attendance_rate'] - $previous['attendance_rate'], 1) : null] + $current;
        }
        usort($rows, fn ($a, $b) => [$b['attendance_rate'], $a['late_rate'] ?? 100.0, $a['name']] <=> [$a['attendance_rate'], $b['late_rate'] ?? 100.0, $b['name']]);

        return $rows;
    }

    private static function risks(array $students): array
    {
        $risks = [];
        foreach ($students as $student) {
            if (! $student['active']) {
                continue;
            }
            ['current' => $now, 'previous' => $before] = $student;
            $reasons = [];
            if ($now['alpha_streak'] >= self::ALPHA_STREAK) {
                $reasons[] = "Alpha {$now['alpha_streak']} hari kerja berturut-turut";
            }
            if ($now['present'] >= self::MIN_DAYS_TO_COMPARE && $before['present'] >= self::MIN_DAYS_TO_COMPARE && $now['late_rate'] - $before['late_rate'] >= self::LATE_RISE_POINTS) {
                $reasons[] = 'Keterlambatan naik dari '.self::number($before['late_rate']).'% ke '.self::number($now['late_rate']).'%';
            }
            if ($now['expected'] >= self::MIN_DAYS_TO_COMPARE && $before['expected'] >= self::MIN_DAYS_TO_COMPARE && $before['attendance_rate'] - $now['attendance_rate'] >= self::ATTENDANCE_DROP_POINTS) {
                $reasons[] = 'Kehadiran turun dari '.self::number($before['attendance_rate']).'% ke '.self::number($now['attendance_rate']).'%';
            }
            if ($reasons !== []) {
                $risks[] = ['nama' => $student['nama'], 'nim' => $student['nim'], 'startup' => $student['startup'], 'reasons' => $reasons, 'alpha' => $now['alpha'], 'attendance_rate' => $now['attendance_rate']];
            }
        }
        usort($risks, fn ($a, $b) => [count($b['reasons']), $b['alpha'], $a['nama']] <=> [count($a['reasons']), $a['alpha'], $b['nama']]);

        return $risks;
    }

    private static function discipline(array $students): array
    {
        $rows = [];
        foreach ($students as $student) {
            $m = $student['current'];
            if ($m['report_due'] > 0) {
                $rows[] = ['nama' => $student['nama'], 'nim' => $student['nim'], 'startup' => $student['startup'], 'report_due' => $m['report_due'], 'report_filled' => $m['report_filled'], 'report_timely' => $m['report_timely'], 'report_rate' => $m['report_rate'], 'timely_rate' => $m['timely_rate']];
            }
        }
        usort($rows, fn ($a, $b) => [$a['report_rate'], $a['timely_rate'] ?? -1.0, $a['nama']] <=> [$b['report_rate'], $b['timely_rate'] ?? -1.0, $b['nama']]);

        return $rows;
    }

    private static function patternRows(array $pattern): array
    {
        $rows = [];
        foreach ($pattern as $weekday => $row) {
            if ($row['total'] > 0) {
                $rows[] = ['day' => self::WEEKDAYS[$weekday], 'total' => $row['total'], 'late' => $row['late'], 'late_rate' => self::percent($row['late'], $row['total']), 'hours' => $row['hours']];
            }
        }

        return $rows;
    }

    private static function quality(array $data, array $period, array $students, array $attendance, array $notes, array $gapDays): array
    {
        $inScope = array_column($students, null, 'id');
        $missingCheckout = $doubleEntry = $unregistered = $noStartup = $neverPresent = [];
        foreach ($data['employees'] as $employee) {
            $id = $employee['id'];
            if (! isset($inScope[$id])) {
                continue;
            }
            $measured = $inScope[$id]['current'];
            if ($measured['expected'] >= self::MIN_DAYS_TO_COMPARE && $measured['present'] + $measured['leave'] === 0) {
                $neverPresent[] = ['nama' => $employee['nama'], 'detail' => $inScope[$id]['startup'].", {$measured['expected']} hari kerja"];
            }
            $raw = (string) $employee['startup'];
            $official = $data['startup_names'][StartupDirectory::canonicalKey($raw)] ?? null;
            if (trim($raw) === '') {
                $noStartup[] = ['nama' => $employee['nama'], 'detail' => $employee['nim']];
            } elseif ($raw !== $official) {
                $unregistered[] = ['nama' => $employee['nama'], 'detail' => $official ? "$raw, seharusnya $official" : "$raw, belum terdaftar"];
            }
            foreach ($attendance[$id] ?? [] as $day => $row) {
                if ($day < $period['start'] || $day > $period['end']) {
                    continue;
                }
                if (in_array($row['ket'], self::REPORT_REQUIRED_KET, true) && in_array(trim((string) $row['jam_pulang']), ['', '-'], true)) {
                    $missingCheckout[] = ['nama' => $employee['nama'], 'detail' => $day];
                }
                if (isset($notes[$id][$day])) {
                    $doubleEntry[] = ['nama' => $employee['nama'], 'detail' => $day];
                }
            }
        }

        $issue = fn (string $key, string $title, string $action, array $items) => [
            'key' => $key, 'title' => $title, 'action' => $action, 'count' => count($items), 'samples' => array_slice($items, 0, self::SAMPLE_LIMIT),
        ];

        return array_values(array_filter([
            $issue('gap_days', 'Hari kerja tanpa presensi sama sekali', 'Tidak dihitung alpha. Catat di Libur Manual bila memang libur.', array_map(fn ($day) => ['nama' => $day, 'detail' => ''], $gapDays)),
            $issue('never_present', 'Mahasiswa tanpa presensi sama sekali', 'Dihitung alpha. Bila magangnya sudah selesai, arsipkan kelompoknya di Kelola Member.', $neverPresent),
            $issue('missing_checkout', 'Presensi tanpa jam pulang', 'Lengkapi lewat Fix Jam Pulang di Data Presensi.', $missingCheckout),
            $issue('double_entry', 'Hadir dan izin/sakit di hari yang sama', 'Hapus salah satu di Data Presensi. Laporan ini memakai izin/sakit.', $doubleEntry),
            $issue('unregistered_startup', 'Nama startup belum rapi', 'Sudah digabung di laporan ini. Rapikan permanen di Kelola Startup.', $unregistered),
            $issue('no_startup', 'Mahasiswa tanpa startup', 'Lengkapi lewat Edit di Kelola Member.', $noStartup),
        ], fn ($item) => $item['count'] > 0));
    }

    private static function conclusions(array $summary, array $total, array $startups, array $risks, array $pattern, array $quality, bool $hasPrevious): array
    {
        $cards = array_column($summary, null, 'key');
        $change = function (array $card) use ($hasPrevious) {
            if (! $hasPrevious || $card['delta'] === null) {
                return '';
            }
            if ($card['delta'] == 0.0) {
                return ', sama dengan periode sebelumnya';
            }

            return ', '.($card['delta'] > 0 ? 'naik ' : 'turun ').self::number(abs($card['delta'])).' poin dari periode sebelumnya';
        };

        $lines = [];
        $lines[] = 'Kehadiran '.self::number($cards['attendance_rate']['value']).'%'.$change($cards['attendance_rate']).'.';
        if ($cards['late_rate']['value'] !== null) {
            $lines[] = 'Keterlambatan '.self::number($cards['late_rate']['value']).'%'.$change($cards['late_rate']).'.';
        }
        $lines[] = $total['alpha'] > 0 ? "Tercatat {$total['alpha']} hari alpha dari {$total['expected']} hari kerja mahasiswa." : 'Tidak ada alpha pada periode ini.';
        if ($cards['report_rate']['value'] !== null) {
            $timely = $cards['timely_rate']['value'] !== null ? ', dan '.self::number($cards['timely_rate']['value']).'% di antaranya diisi tepat waktu' : '';
            $lines[] = 'Laporan harian terisi '.self::number($cards['report_rate']['value']).'%'.$timely.'.';
        }
        if (count($startups) >= 2) {
            $best = $startups[0];
            $lowest = $startups[count($startups) - 1];
            $lines[] = "Kehadiran tertinggi di {$best['name']} (".self::number($best['attendance_rate'])."%), terendah di {$lowest['name']} (".self::number($lowest['attendance_rate']).'%).';
        }
        if ($pattern !== []) {
            $peak = array_reduce($pattern, fn ($carry, $row) => $carry === null || $row['late_rate'] > $carry['late_rate'] ? $row : $carry);
            if ($peak['late'] > 0) {
                $lines[] = "Keterlambatan paling sering terjadi pada hari {$peak['day']} (".self::number($peak['late_rate']).'%).';
            }
        }
        $lines[] = $risks === [] ? 'Tidak ada mahasiswa aktif yang masuk daftar berisiko.' : count($risks).' mahasiswa aktif perlu ditindaklanjuti.';
        if ($quality !== []) {
            $lines[] = count($quality).' jenis masalah data perlu diperiksa sebelum laporan dibagikan.';
        }

        return $lines;
    }

    private static function number(?float $value): string
    {
        return $value === null ? '-' : number_format($value, 1, ',', '.');
    }

    /** @return string[] */
    private static function dates(string $start, string $end): array
    {
        $dates = [];
        $range = new DatePeriod(new DateTimeImmutable($start), new DateInterval('P1D'), (new DateTimeImmutable($end))->modify('+1 day'));
        foreach ($range as $date) {
            $dates[] = $date->format('Y-m-d');
        }

        return $dates;
    }
}
