<?php

use App\Legacy\ResponseSignal;
use App\Services\Attendance\AttendanceSubmission;
use App\Services\LegacyAuthorization;

if (isset($_REQUEST['ajax'])) {
    $action = $_REQUEST['ajax'];
    ob_start();
    @ini_set('memory_limit', '1024M');
    try {

        // Check if database is available
        if (! isset($pdo)) {
            error_log('Database connection failed in AJAX handler');
            jsonResponse(['error' => 'Database connection failed'], 500);
        }

        $accessStatus = LegacyAuthorization::status($pdo, $_SESSION ?? [], $action, array_merge($_GET, $_POST));
        if ($accessStatus !== 200) {
            jsonResponse(['ok' => false, 'message' => $accessStatus === 401 ? 'Silakan login terlebih dahulu.' : 'Akses tidak diizinkan.'], $accessStatus);
        }

        // Helper query strings to exclude archived users (who are in archived groups and NOT in any active groups)
        $archivedExcludeQuery = 'user_id NOT IN (
        SELECT DISTINCT igm.user_id 
        FROM intern_group_members igm 
        JOIN intern_groups ig ON ig.id = igm.group_id 
        WHERE ig.is_archived = 1 
        AND igm.user_id NOT IN (
            SELECT DISTINCT igm2.user_id 
            FROM intern_group_members igm2 
            JOIN intern_groups ig2 ON ig2.id = igm2.group_id 
            WHERE ig2.is_archived = 0
        )
    )';

        $archivedExcludeUsersQuery = 'id NOT IN (
        SELECT DISTINCT igm.user_id 
        FROM intern_group_members igm 
        JOIN intern_groups ig ON ig.id = igm.group_id 
        WHERE ig.is_archived = 1 
        AND igm.user_id NOT IN (
            SELECT DISTINCT igm2.user_id 
            FROM intern_group_members igm2 
            JOIN intern_groups ig2 ON ig2.id = igm2.group_id 
            WHERE ig2.is_archived = 0
        )
    )';

        // Must be authenticated for all endpoints except auth-related and public landing scan
        if (! in_array($action, ['login', 'register', 'get_members', 'get_member_photo', 'save_face_embedding', 'save_attendance', 'get_today_attendance', 'forgot_password', 'verify_otp', 'reset_password', 'get_ga_qr', 'get_public_daily_report_stats', 'reverse_geocode', 'submit_help_request', 'search_address', 'get_clockin_location', 'get_settings'], true)) {
            if (! isset($_SESSION['user'])) {
                jsonResponse(['error' => 'Unauthorized'], 401);
            }

            // Auto-cleanup old photos — THROTTLED: max 1x per hour (per session)
            // PERFORMANCE FIX: Previously ran on EVERY authenticated request, causing heavy DB load.
            $cleanupSessionKey = '_last_photo_cleanup_ts';
            $lastCleanupTs = $_SESSION[$cleanupSessionKey] ?? 0;
            if (isset($pdo) && (time() - $lastCleanupTs) > 3600) {
                // Retention cleanup must be run explicitly, not by a read request.
                $_SESSION[$cleanupSessionKey] = time();
            }
        }
        // Address Search
        // === Modular action includes ===
        require __DIR__.'/actions/misc_actions.php';
        require __DIR__.'/actions/holiday_actions.php';
        require __DIR__.'/actions/auth_actions.php';
        require __DIR__.'/actions/face_actions.php';
        require __DIR__.'/actions/member_actions.php';
        require __DIR__.'/actions/attendance_actions.php';
        require __DIR__.'/actions/report_actions.php';
        require __DIR__.'/actions/settings_actions.php';
        require __DIR__.'/actions/backup_actions.php';
        require __DIR__.'/actions/help_actions.php';
        require __DIR__.'/actions/group_actions.php';

        if ($action === 'save_attendance' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $result = app(AttendanceSubmission::class)->submit($_POST, $_SESSION['user'] ?? []);
            jsonResponse($result->getData(true), $result->getStatusCode());
        }

        jsonResponse(['ok' => false, 'message' => 'Endpoint tidak ditemukan'], 404);

    } catch (Throwable $e) {
        if ($e instanceof ResponseSignal) {
            throw $e;
        }
        error_log('AJAX Handler Error: '.$e->getMessage().' in '.$e->getFile().':'.$e->getLine());
        jsonResponse([
            'ok' => false,
            'message' => 'Terjadi kesalahan sistem internal: '.substr($e->getMessage(), 0, 100),
            'debug' => (config('app.debug') ? $e->getTraceAsString() : null),
        ], 500);
    }
}
