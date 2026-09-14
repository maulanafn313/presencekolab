<?php

namespace App\Services;

use PDO;

final class LegacyAuthorization
{
    public static function status(PDO $pdo, array $session, string $action, array $input): int
    {
        if (in_array($action, ['login', 'register', 'forgot_password', 'verify_otp', 'reset_password', 'get_settings', 'search_address', 'reverse_geocode', 'get_public_daily_report_stats', 'get_member_photo'], true)) {
            return 200;
        }
        $user = $session['user'] ?? [];
        if (empty($user['id'])) {
            // Kios presensi publik: aksi untuk memindai wajah, mengirim absen, dan
            // melihat log hari ini tetap terbuka bagi tamu, sesuai perilaku lama.
            return in_array($action, ['save_attendance', 'get_members', 'get_today_attendance'], true) ? 200 : 401;
        }
        if (($user['role'] ?? null) === 'admin') {
            return 200;
        }
        if (($user['role'] ?? null) !== 'pegawai') {
            return 403;
        }
        if (str_starts_with($action, 'admin_') || in_array($action, [
            'save_member', 'delete_member', 'delete_attendance', 'get_ga_qr',
            'save_face_embedding', 'save_computed_face_embedding', 'generate_face_embedding',
            'save_setting', 'update_settings', 'import_db', 'fix_monthly_reports',
            'get_backup_status', 'create_backup', 'list_backup_files', 'download_backup',
            'get_intern_groups', 'get_group_members', 'save_intern_group', 'delete_intern_group',
            'assign_members_to_group', 'archive_intern_group', 'unarchive_intern_group',
            'export_group_database', 'add_manual_holiday', 'delete_manual_holiday',
        ], true)) {
            return 403;
        }
        if (isset($input['user_id']) && (string) $input['user_id'] !== (string) $user['id']) {
            return 403;
        }
        if (in_array($action, ['save_attendance', 'get_clockin_location', 'get_user_info'], true)
            && isset($input['nim']) && (string) $input['nim'] !== (string) ($user['nim'] ?? '')) {
            return 403;
        }
        if ($action === 'get_member_photo' && (int) ($input['id'] ?? 0) !== (int) $user['id']) {
            return 403;
        }
        $table = match ($action) {
            'get_monthly_report_detail' => 'monthly_reports',
            'get_daily_report_detail' => 'daily_reports',
            'get_attendance_evidence', 'update_bukti_izin_sakit' => (($input['type'] ?? '') === 'note' || str_starts_with((string) ($input['id'] ?? ''), 'note_')) ? 'attendance_notes' : 'attendance',
            default => null,
        };
        if ($table && isset($input['id']) && $input['id'] !== '') {
            $stmt = $pdo->prepare("SELECT user_id FROM $table WHERE id = ?");
            $stmt->execute([(int) str_replace('note_', '', (string) $input['id'])]);
            $owner = $stmt->fetchColumn();
            if ($owner === false) {
                return 404;
            }
            if ((int) $owner !== (int) $user['id']) {
                return 403;
            }
        }

        return 200;
    }
}
