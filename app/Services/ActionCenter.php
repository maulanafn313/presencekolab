<?php

namespace App\Services;

use PDO;

/** Daftar pekerjaan admin hari ini untuk Dashboard. */
final class ActionCenter
{
    private const CHECKOUT_LOOKBACK_DAYS = 30;

    public static function build(PDO $pdo, string $today, string $activeUsersSql): array
    {
        $active = "SELECT id FROM users WHERE role = 'pegawai' AND $activeUsersSql";
        $count = function (string $sql, array $params = []) use ($pdo): int {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            return (int) $stmt->fetchColumn();
        };

        $reminder = AttendanceReminder::build($pdo, $today, $activeUsersSql)['students'];
        $startups = StartupDirectory::overview($pdo)['unregistered'];
        $since = date('Y-m-d', strtotime("$today -".self::CHECKOUT_LOOKBACK_DAYS.' days'));

        return self::items([
            'absent' => count(array_filter($reminder, fn ($s) => $s['absent_today'])),
            'missing_reports' => count(array_filter($reminder, fn ($s) => $s['missing_reports'] > 0)),
            'pending_reports' => $count("SELECT COUNT(*) FROM daily_reports WHERE status = 'pending' AND TRIM(COALESCE(content, '')) <> '' AND user_id IN ($active)"),
            'help_requests' => $count("SELECT COUNT(*) FROM admin_help_requests WHERE status = 'pending'"),
            'password_resets' => $count("SELECT COUNT(*) FROM users WHERE role = 'pegawai' AND reset_requested_at IS NOT NULL AND $activeUsersSql"),
            'missing_checkout' => $count("SELECT COUNT(*) FROM attendance WHERE ket IN ('wfo', 'wfa') AND jam_masuk_iso >= ? AND jam_masuk_iso < ? AND (jam_pulang IS NULL OR TRIM(jam_pulang) IN ('', '-')) AND user_id IN ($active)", ["$since 00:00:00", "$today 00:00:00"]),
            'startup_names' => (int) array_sum(array_column($startups, 'members')),
        ]);
    }

    /** Urutan mengikuti yang paling mendesak. Item tanpa pekerjaan tidak ditampilkan. */
    public static function items(array $counts): array
    {
        $definitions = [
            'help_requests' => ['Request menunggu', 'Proses permintaan mahasiswa', 'help-requests'],
            'password_resets' => ['Reset password', 'Setujui atau tolak permintaan', 'password-resets'],
            'absent' => ['Belum presensi hari ini', 'Kirim pengingat ke grup', 'reminder'],
            'missing_reports' => ['Mahasiswa belum isi laporan', 'Kirim pengingat ke grup', 'reminder'],
            'pending_reports' => ['Laporan menunggu review', 'Tinjau laporan mahasiswa aktif', 'review-laporan'],
            'missing_checkout' => ['Presensi tanpa jam pulang', '30 hari terakhir', 'laporan'],
            'startup_names' => ['Nama startup belum rapi', 'Rapikan di Kelola Startup', 'startups'],
        ];

        $items = [];
        foreach ($definitions as $key => [$label, $hint, $target]) {
            if (($counts[$key] ?? 0) > 0) {
                $items[] = ['key' => $key, 'label' => $label, 'hint' => $hint, 'target' => $target, 'count' => $counts[$key]];
            }
        }

        return $items;
    }
}
