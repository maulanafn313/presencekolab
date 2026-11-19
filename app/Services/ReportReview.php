<?php

namespace App\Services;

use DateTimeImmutable;
use PDO;

/**
 * Antrean review laporan harian per mahasiswa untuk seluruh tanggal.
 */
final class ReportReview
{
    private const DEFAULT_MAX_DAYS_BACK = 5;

    public static function isValidDate(string $date): bool
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);

        return $parsed !== false && $parsed->format('Y-m-d') === $date;
    }

    /** Selisih hari antara tanggal laporan dan tanggal laporan pertama kali dikirim. */
    public static function daysLate(string $reportDate, string $createdAt): int
    {
        $diff = (new DateTimeImmutable($reportDate))->diff(new DateTimeImmutable(substr($createdAt, 0, 10)));

        return max(0, (int) $diff->format('%r%a'));
    }

    /** @param string $activeUsersSql Kondisi pengecualian pegawai arsip, diawali kolom `id`. */
    public static function summary(PDO $pdo, ?string $start, ?string $end, string $activeUsersSql): array
    {
        [$period, $params] = self::periodSql($start, $end);
        $stmt = $pdo->prepare("SELECT u.id AS user_id, u.nim, u.nama, u.startup, dr.id, dr.report_date, dr.status, dr.created_at
            FROM daily_reports dr
            JOIN users u ON u.id = dr.user_id
            WHERE u.role = 'pegawai' AND TRIM(COALESCE(dr.content, '')) <> '' $period");
        $stmt->execute($params);

        $activeIds = $pdo->query("SELECT u.id FROM users u WHERE u.role = 'pegawai' AND u.$activeUsersSql")->fetchAll(PDO::FETCH_COLUMN);
        $maxDaysBack = self::maxDaysBack($pdo);

        return [
            'max_days_back' => $maxDaysBack,
            'employees' => self::summarize($stmt->fetchAll(PDO::FETCH_ASSOC), array_map('intval', $activeIds), $maxDaysBack, ReportQuality::flagged($pdo)),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows  Satu baris per laporan.
     * @param  list<int>  $activeIds
     * @param  array<int, mixed>  $flags  Laporan yang perlu dibaca sebelum disetujui.
     */
    public static function summarize(array $rows, array $activeIds, int $maxDaysBack, array $flags = []): array
    {
        $active = array_flip($activeIds);
        $employees = [];
        foreach ($rows as $row) {
            $id = (int) $row['user_id'];
            $employees[$id] ??= [
                'id' => $id,
                'nim' => $row['nim'],
                'nama' => $row['nama'],
                'startup' => $row['startup'],
                'archived' => ! isset($active[$id]),
                'pending' => 0,
                'approved' => 0,
                'disapproved' => 0,
                'late' => 0,
                'oldest_pending' => null,
                'pending_ids' => [],
                'flagged' => 0,
                'flagged_ids' => [],
            ];
            if ($row['status'] === 'approved' || $row['status'] === 'disapproved') {
                $employees[$id][$row['status']]++;

                continue;
            }
            $employees[$id]['pending']++;
            $employees[$id]['pending_ids'][] = (int) $row['id'];
            if (isset($flags[(int) $row['id']])) {
                $employees[$id]['flagged']++;
                $employees[$id]['flagged_ids'][] = (int) $row['id'];
            }
            if (self::daysLate($row['report_date'], $row['created_at']) > $maxDaysBack) {
                $employees[$id]['late']++;
            }
            $oldest = $employees[$id]['oldest_pending'];
            $employees[$id]['oldest_pending'] = $oldest === null ? $row['report_date'] : min($oldest, $row['report_date']);
        }

        // Mahasiswa aktif didahulukan, lalu yang antreannya paling banyak.
        usort($employees, fn (array $a, array $b) => [$a['archived'], $b['pending'], $a['nama']] <=> [$b['archived'], $a['pending'], $b['nama']]);

        return $employees;
    }

    public static function detail(PDO $pdo, int $userId, ?string $start, ?string $end): array
    {
        [$period, $params] = self::periodSql($start, $end);
        $params[':uid'] = $userId;
        $stmt = $pdo->prepare("SELECT dr.id, dr.report_date, dr.content, dr.status, dr.evaluation, dr.created_at
            FROM daily_reports dr
            WHERE dr.user_id = :uid AND TRIM(COALESCE(dr.content, '')) <> '' $period
            ORDER BY dr.report_date DESC");
        $stmt->execute($params);
        $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $attendance = $pdo->prepare('SELECT DATE(jam_masuk_iso) AS d, jam_masuk, jam_pulang, status, ket FROM attendance WHERE user_id = ? ORDER BY jam_masuk_iso DESC');
        $attendance->execute([$userId]);
        $attendanceByDate = array_column($attendance->fetchAll(PDO::FETCH_ASSOC), null, 'd');

        $notes = $pdo->prepare('SELECT date AS d, type FROM attendance_notes WHERE user_id = ?');
        $notes->execute([$userId]);
        $noteByDate = array_column($notes->fetchAll(PDO::FETCH_ASSOC), 'type', 'd');

        $maxDaysBack = self::maxDaysBack($pdo);
        $flags = ReportQuality::flagged($pdo);

        return array_map(function (array $report) use ($attendanceByDate, $noteByDate, $maxDaysBack, $flags) {
            $date = $report['report_date'];
            $present = $attendanceByDate[$date] ?? null;
            $daysLate = self::daysLate($date, $report['created_at']);

            return [
                'id' => (int) $report['id'],
                'date' => $date,
                'content' => $report['content'],
                'status' => in_array($report['status'], ['approved', 'disapproved'], true) ? $report['status'] : 'pending',
                'evaluation' => $report['evaluation'],
                'submitted_at' => $report['created_at'],
                'days_late' => $daysLate,
                'is_late' => $daysLate > $maxDaysBack,
                'flags' => $flags[(int) $report['id']] ?? [],
                'attendance' => self::attendanceState($present, $noteByDate[$date] ?? null),
                'jam_masuk' => $present['jam_masuk'] ?? null,
                'jam_pulang' => $present['jam_pulang'] ?? null,
            ];
        }, $reports);
    }

    /** @return int[] Laporan yang akan berubah bila approvePending() dijalankan. */
    public static function approvableIds(PDO $pdo, array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn (int $id) => $id > 0)));
        if ($ids === []) {
            return [];
        }
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("SELECT id FROM daily_reports WHERE status = 'pending' AND TRIM(COALESCE(content, '')) <> '' AND id IN ($marks)");
        $stmt->execute($ids);

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /** @return int Jumlah laporan yang benar-benar berubah menjadi approved. */
    public static function approvePending(PDO $pdo, array $ids): int
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn (int $id) => $id > 0)));
        if ($ids === []) {
            return 0;
        }
        $marks = implode(',', array_fill(0, count($ids), '?'));
        // Laporan ditolak dan laporan kosong sengaja tidak ikut; evaluasi yang ada tetap utuh.
        $stmt = $pdo->prepare("UPDATE daily_reports SET status = 'approved', updated_at = ? WHERE status = 'pending' AND TRIM(COALESCE(content, '')) <> '' AND id IN ($marks)");
        $stmt->execute([date('Y-m-d H:i:s'), ...$ids]);

        return $stmt->rowCount();
    }

    public static function attendanceState(?array $attendance, ?string $noteType): string
    {
        $ket = strtolower((string) ($attendance['ket'] ?? ''));

        return match (true) {
            // Sama dengan KPI: catatan izin/sakit lebih diutamakan daripada presensi.
            $noteType !== null => strtolower($noteType) === 'sakit' ? 'sakit' : 'izin',
            in_array($ket, ['izin', 'sakit'], true) => $ket,
            $attendance === null => 'tanpa_presensi',
            $attendance['status'] === 'terlambat' => 'terlambat',
            default => 'hadir',
        };
    }

    /** @return array{0: string, 1: array<string, string>} */
    private static function periodSql(?string $start, ?string $end): array
    {
        $sql = '';
        $params = [];
        if ($start !== null) {
            $sql .= ' AND dr.report_date >= :start';
            $params[':start'] = $start;
        }
        if ($end !== null) {
            $sql .= ' AND dr.report_date <= :end';
            $params[':end'] = $end;
        }

        return [$sql, $params];
    }

    public static function maxDaysBack(PDO $pdo): int
    {
        $stmt = $pdo->prepare('SELECT setting_value FROM settings WHERE setting_key = ?');
        $stmt->execute(['max_daily_report_days_back']);
        $value = $stmt->fetchColumn();

        return is_numeric($value) ? (int) $value : self::DEFAULT_MAX_DAYS_BACK;
    }
}
