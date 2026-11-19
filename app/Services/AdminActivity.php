<?php

namespace App\Services;

use DomainException;
use PDO;
use Throwable;

/** Riwayat perubahan oleh admin, termasuk pembatalan aksi massal. */
final class AdminActivity
{
    private const LIST_LIMIT = 500;

    private const LABELS = [
        'save_member' => 'Menyimpan data member',
        'delete_member' => 'Menghapus member',
        'delete_attendance' => 'Menghapus data presensi',
        'admin_update_attendance' => 'Mengubah data presensi',
        'admin_add_absence' => 'Input presensi manual',
        'admin_bulk_fix_empty_checkout' => 'Mengisi jam pulang kosong',
        'admin_update_wfa_locations' => 'Memperbarui lokasi WFA',
        'admin_set_daily_status' => 'Mengubah status laporan harian',
        'admin_save_daily_report' => 'Menyimpan laporan harian mahasiswa',
        'admin_set_monthly_status' => 'Mengubah status laporan bulanan',
        'admin_handle_help_request' => 'Memproses request mahasiswa',
        'admin_approve_password_reset' => 'Menyetujui reset password',
        'admin_reject_password_reset' => 'Menolak reset password',
        'admin_save_work_schedule' => 'Mengubah jadwal kerja',
        'add_manual_holiday' => 'Menambah libur manual',
        'admin_add_manual_holiday' => 'Menambah libur manual',
        'delete_manual_holiday' => 'Menghapus libur manual',
        'admin_delete_manual_holiday' => 'Menghapus libur manual',
        'update_settings' => 'Mengubah pengaturan',
        'save_setting' => 'Mengubah pengaturan',
        'import_db' => 'Mengimport database',
        'save_intern_group' => 'Menyimpan kelompok magang',
        'delete_intern_group' => 'Menghapus kelompok magang',
        'assign_members_to_group' => 'Mengatur anggota kelompok magang',
        'archive_intern_group' => 'Mengarsipkan kelompok magang',
        'unarchive_intern_group' => 'Membuka arsip kelompok magang',
        'admin_create_startup' => 'Menambah startup',
        'admin_rename_startup' => 'Mengubah nama startup',
        'admin_set_startup_active' => 'Mengubah status startup',
        'admin_merge_startup' => 'Menggabungkan startup',
        'admin_sync_startups' => 'Sinkron startup dari portal',
        'admin_apply_startup_cleanup' => 'Merapikan nama startup lama',
    ];

    /** Hanya nilai ini yang boleh tersimpan. Password, foto, dan isi laporan tidak pernah dicatat. */
    private const SAFE_FIELDS = ['id', 'user_id', 'nim', 'nama', 'name', 'status', 'ket', 'date', 'tanggal', 'target_id', 'active'];

    public static function safeDetail(array $input): ?string
    {
        $parts = [];
        foreach (self::SAFE_FIELDS as $field) {
            if (isset($input[$field]) && is_scalar($input[$field]) && (string) $input[$field] !== '') {
                $parts[] = $field.': '.mb_substr((string) $input[$field], 0, 80);
            }
        }

        return $parts === [] ? null : mb_substr(implode(', ', $parts), 0, 500);
    }

    /** Dipanggil untuk setiap respons aksi admin. Pencatatan tidak boleh menggagalkan aksinya. */
    public static function recordResponse(PDO $pdo, string $action, array $session, array $input, array $payload, int $status): void
    {
        $succeeded = $status < 400 && ($payload['ok'] ?? true) !== false && ! isset($payload['error']);
        if ($succeeded && isset(self::LABELS[$action]) && ($session['user']['role'] ?? null) === 'admin') {
            self::record($pdo, $action, self::LABELS[$action], self::safeDetail($input), null, $session);
        }
    }

    public static function record(PDO $pdo, string $action, string $summary, ?string $detail, ?array $undo, array $session): void
    {
        try {
            $pdo->prepare('INSERT INTO admin_activity_logs (actor_id, actor_name, action, summary, detail, undo_payload, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())')->execute([
                $session['user']['id'] ?? null, $session['user']['nama'] ?? 'Admin', $action, $summary, $detail, $undo === null ? null : json_encode($undo),
            ]);
        } catch (Throwable $e) {
            error_log('Admin activity log failed: '.$e->getMessage());
        }
    }

    public static function latest(PDO $pdo): array
    {
        $rows = $pdo->query('SELECT id, actor_name, action, summary, detail, undo_payload, undone_at, undone_by, created_at FROM admin_activity_logs ORDER BY id DESC LIMIT '.self::LIST_LIMIT)->fetchAll(PDO::FETCH_ASSOC);

        return array_map(function (array $row) {
            $undo = $row['undo_payload'] === null ? null : json_decode($row['undo_payload'], true);
            unset($row['undo_payload']);

            return $row + [
                'id' => (int) $row['id'],
                'can_undo' => $undo !== null && $row['undone_at'] === null,
                'irreversible' => (int) ($undo['irreversible'] ?? 0),
            ];
        }, $rows);
    }

    /** Baris yang sudah diubah lagi sejak aksi massal tidak ditimpa. */
    public static function undo(PDO $pdo, int $id, array $session): array
    {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT * FROM admin_activity_logs WHERE id = ? FOR UPDATE');
            $stmt->execute([$id]);
            $log = $stmt->fetch(PDO::FETCH_ASSOC);
            if (! $log || $log['undo_payload'] === null) {
                throw new DomainException('Aktivitas ini tidak bisa dibatalkan.');
            }
            if ($log['undone_at'] !== null) {
                throw new DomainException('Aktivitas ini sudah dibatalkan.');
            }

            $payload = json_decode($log['undo_payload'], true);
            $result = $payload['type'] === 'reports' ? self::undoReports($pdo, $payload) : self::undoAttendance($pdo, $payload);
            $pdo->prepare('UPDATE admin_activity_logs SET undone_at = NOW(), undone_by = ? WHERE id = ?')->execute([$session['user']['nama'] ?? 'Admin', $id]);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();

            throw $e;
        }

        self::record($pdo, 'admin_undo_activity', 'Membatalkan: '.$log['summary'], "{$result['restored']} data dikembalikan, {$result['skipped']} dilewati", null, $session);

        return $result;
    }

    private static function undoReports(PDO $pdo, array $payload): array
    {
        $stmt = $pdo->prepare("UPDATE daily_reports SET status = 'pending', updated_at = NOW() WHERE id = ? AND status = 'approved'");
        $restored = 0;
        foreach ($payload['ids'] as $reportId) {
            $stmt->execute([$reportId]);
            $restored += $stmt->rowCount();
        }

        return ['restored' => $restored, 'skipped' => count($payload['ids']) - $restored, 'touched' => []];
    }

    private static function undoAttendance(PDO $pdo, array $payload): array
    {
        $restored = 0;
        $attendance = $pdo->prepare('UPDATE attendance SET status = ?, ket = ? WHERE id = ? AND status <=> ? AND ket <=> ?');
        foreach ($payload['attendance'] as $row) {
            $attendance->execute([$row['status'], $row['ket'], $row['id'], $row['new_status'], $row['new_ket']]);
            $restored += $attendance->rowCount();
        }
        $notes = $pdo->prepare('UPDATE attendance_notes SET type = ? WHERE id = ? AND type = ?');
        foreach ($payload['notes'] as $row) {
            $notes->execute([$row['type'], $row['id'], $row['new_type']]);
            $restored += $notes->rowCount();
        }

        return ['restored' => $restored, 'skipped' => count($payload['attendance']) + count($payload['notes']) - $restored, 'touched' => $payload['touched']];
    }
}
