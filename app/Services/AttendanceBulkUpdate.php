<?php

namespace App\Services;

use App\Services\Attendance\WorkHours;
use PDO;
use Throwable;

/**
 * Ubah status masuk dan keterangan banyak baris Data Presensi sekaligus.
 *
 * Hadir/WFA tersimpan di `attendance`, izin/sakit di `attendance_notes`, jadi
 * perubahan antar kelompok itu memindahkan baris antar tabel.
 */
final class AttendanceBulkUpdate
{
    public const MAX_ROWS = 2000;

    private const STATUSES = ['ontime', 'terlambat'];

    private const PRESENT = ['wfo', 'wfa'];

    private const LEAVE = ['izin', 'sakit'];

    /**
     * @return array{attendance: int[], notes: int[]}|null null bila daftar kosong, terlalu panjang, atau tidak valid
     */
    public static function parseIds(string $raw): ?array
    {
        $ids = array_values(array_unique(array_filter(explode(',', $raw), 'strlen')));
        if ($ids === [] || count($ids) > self::MAX_ROWS) {
            return null;
        }

        $parsed = ['attendance' => [], 'notes' => []];
        foreach ($ids as $id) {
            if (! preg_match('/^(note_)?([1-9]\d{0,9})$/', $id, $match)) {
                return null;
            }
            $parsed[$match[1] === '' ? 'attendance' : 'notes'][] = (int) $match[2];
        }

        return $parsed;
    }

    public static function isValidChange(?string $status, ?string $ket): bool
    {
        return ($status !== null || $ket !== null)
            && ($status === null || in_array($status, self::STATUSES, true))
            && ($ket === null || in_array($ket, [...self::PRESENT, ...self::LEAVE], true));
    }

    /** @return 'none'|'update'|'to_note' */
    public static function planAttendance(string $currentStatus, string $currentKet, ?string $status, ?string $ket): string
    {
        if (in_array($ket, self::LEAVE, true)) {
            return 'to_note';
        }

        return ($status !== null && $status !== $currentStatus) || ($ket !== null && $ket !== $currentKet) ? 'update' : 'none';
    }

    /** @return 'none'|'retype'|'to_attendance' */
    public static function planNote(string $currentType, ?string $ket): string
    {
        if ($ket === null || $ket === $currentType) {
            return 'none';
        }

        return in_array($ket, self::LEAVE, true) ? 'retype' : 'to_attendance';
    }

    /**
     * Semua baris berhasil atau tidak sama sekali.
     *
     * @param  array{attendance: int[], notes: int[]}  $ids
     * @return array{changed: int, skipped: int, touched: array<int, array{0: int, 1: string}>}
     */
    public static function apply(PDO $pdo, array $ids, ?string $status, ?string $ket): array
    {
        $changed = 0;
        $touched = [];
        $undo = ['type' => 'attendance', 'attendance' => [], 'notes' => [], 'touched' => [], 'irreversible' => 0];
        $pdo->beginTransaction();

        try {
            foreach (self::lockedRows($pdo, 'SELECT id, user_id, DATE(jam_masuk_iso) AS day, status, ket FROM attendance', $ids['attendance']) as $row) {
                $plan = self::planAttendance((string) $row['status'], (string) $row['ket'], $status, $ket);
                if ($plan === 'none') {
                    continue;
                }
                $plan === 'update'
                    ? $pdo->prepare('UPDATE attendance SET status = COALESCE(?, status), ket = COALESCE(?, ket) WHERE id = ?')->execute([$status, $ket, $row['id']])
                    : self::moveToNote($pdo, (int) $row['id'], $ket);
                if ($plan === 'update') {
                    $undo['attendance'][] = ['id' => (int) $row['id'], 'status' => $row['status'], 'ket' => $row['ket'], 'new_status' => $status ?? $row['status'], 'new_ket' => $ket ?? $row['ket']];
                } else {
                    $undo['irreversible']++;
                }
                $changed++;
                $touched[$row['user_id'].'|'.substr((string) $row['day'], 0, 7)] = [(int) $row['user_id'], (string) $row['day']];
            }

            foreach (self::lockedRows($pdo, 'SELECT id, user_id, `date` AS day, type FROM attendance_notes', $ids['notes']) as $row) {
                $plan = self::planNote((string) $row['type'], $ket);
                if ($plan === 'none') {
                    continue;
                }
                $plan === 'retype'
                    ? $pdo->prepare('UPDATE attendance_notes SET type = ? WHERE id = ?')->execute([$ket, $row['id']])
                    : self::moveToAttendance($pdo, (int) $row['id'], $status, $ket);
                if ($plan === 'retype') {
                    $undo['notes'][] = ['id' => (int) $row['id'], 'type' => $row['type'], 'new_type' => $ket];
                } else {
                    $undo['irreversible']++;
                }
                $changed++;
                $touched[$row['user_id'].'|'.substr((string) $row['day'], 0, 7)] = [(int) $row['user_id'], (string) $row['day']];
            }

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();

            throw $e;
        }

        return [
            'changed' => $changed,
            'skipped' => count($ids['attendance']) + count($ids['notes']) - $changed,
            'touched' => array_values($touched),
            'undo' => $undo['attendance'] === [] && $undo['notes'] === [] ? null : ['touched' => array_values($touched)] + $undo,
        ];
    }

    /** @param int[] $ids */
    private static function lockedRows(PDO $pdo, string $select, array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $stmt = $pdo->prepare($select.' WHERE id IN ('.implode(',', array_fill(0, count($ids), '?')).') FOR UPDATE');
        $stmt->execute($ids);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Catatan izin/sakit yang sudah ada di hari itu tetap dipakai alasan dan buktinya. */
    private static function moveToNote(PDO $pdo, int $attendanceId, string $type): void
    {
        $pdo->prepare("INSERT INTO attendance_notes (user_id, `date`, type, keterangan, bukti, created_at)
            SELECT user_id, DATE(jam_masuk_iso), ?, COALESCE(NULLIF(TRIM(alasan_izin_sakit), ''), 'Tidak ada keterangan'), bukti_izin_sakit, NOW()
            FROM attendance WHERE id = ?
            ON DUPLICATE KEY UPDATE type = VALUES(type)")->execute([$type, $attendanceId]);
        $pdo->prepare('DELETE FROM attendance WHERE id = ?')->execute([$attendanceId]);
    }

    /** Jam mengikuti jam kerja hari itu. Alasan dan bukti izin ikut disimpan di baris presensi. */
    private static function moveToAttendance(PDO $pdo, int $noteId, ?string $status, string $ket): void
    {
        $note = $pdo->prepare('SELECT `date` FROM attendance_notes WHERE id = ?');
        $note->execute([$noteId]);
        $day = (string) $note->fetchColumn();
        $start = WorkHours::defaultStart($day);
        $end = WorkHours::defaultEnd($day);
        $pdo->prepare("INSERT INTO attendance (user_id, jam_masuk, jam_masuk_iso, jam_pulang, jam_pulang_iso, status, ket, alasan_izin_sakit, bukti_izin_sakit, created_at)
            SELECT user_id, '$start', CONCAT(`date`, ' $start:00'), '$end', CONCAT(`date`, ' $end:00'), COALESCE(?, 'ontime'), ?, keterangan, bukti, NOW()
            FROM attendance_notes WHERE id = ?
            ON DUPLICATE KEY UPDATE ket = VALUES(ket), status = COALESCE(?, attendance.status)")->execute([$status, $ket, $noteId, $status]);
        $pdo->prepare('DELETE FROM attendance_notes WHERE id = ?')->execute([$noteId]);
    }
}
