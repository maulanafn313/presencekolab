<?php

// Action group: riwayat aktivitas dan daftar pekerjaan admin
// Variables in scope: $pdo, $action, $archivedExcludeUsersQuery

use App\Services\ActionCenter;
use App\Services\AdminActivity;

if (in_array($action, ['admin_get_activity_logs', 'admin_get_action_center', 'admin_get_attendance_calendar', 'admin_undo_activity'], true)) {
    if (! isAdmin()) {
        jsonResponse(['error' => 'Forbidden'], 403);
    }
    if ($action === 'admin_get_activity_logs') {
        jsonResponse(['ok' => true, 'data' => AdminActivity::latest($pdo)]);
    }
    if ($action === 'admin_get_action_center') {
        jsonResponse(['ok' => true, 'data' => ActionCenter::build($pdo, date('Y-m-d'), $archivedExcludeUsersQuery)]);
    }
    if ($action === 'admin_get_attendance_calendar') {
        try {
            jsonResponse(['ok' => true, 'data' => \App\Services\AttendanceCalendar::build($pdo, trim((string) ($_REQUEST['month'] ?? '')), date('Y-m-d'))]);
        } catch (DomainException $e) {
            jsonResponse(['ok' => false, 'message' => $e->getMessage()], 400);
        }
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(['ok' => false, 'message' => 'Metode tidak diizinkan'], 405);
    }
    try {
        $undone = AdminActivity::undo($pdo, (int) ($_POST['id'] ?? 0), $_SESSION);
    } catch (DomainException $e) {
        jsonResponse(['ok' => false, 'message' => $e->getMessage()], 400);
    }
    foreach ($undone['touched'] as [$touchedUserId, $touchedDate]) {
        clearKpiCache($pdo, $touchedUserId, $touchedDate);
    }
    jsonResponse(['ok' => true, 'restored' => $undone['restored'], 'skipped' => $undone['skipped']]);
}
