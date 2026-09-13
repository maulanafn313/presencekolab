<?php

namespace App\Services;

final class LegacyRequestPolicy
{
    public static function isRead(string $action): bool
    {
        if ($action === 'get_ga_qr') {
            return false; // This action may create an authenticator secret.
        }

        return str_starts_with($action, 'get_') || str_starts_with($action, 'admin_get_')
            || str_starts_with($action, 'export_') || in_array($action, [
                'search_address', 'reverse_geocode', 'check_session', 'list_backup_files',
                'download_backup', 'pegawai_get_notifications',
            ], true);
    }
}
