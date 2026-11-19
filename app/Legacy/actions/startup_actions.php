<?php

// Action group: kelola startup (khusus admin)
// Variables in scope: $pdo, $action

use App\Services\AdminActivity;
use App\Services\StartupDirectory;

if (in_array($action, ['admin_get_startups', 'admin_create_startup', 'admin_rename_startup', 'admin_set_startup_active', 'admin_merge_startup', 'admin_sync_startups', 'admin_apply_startup_cleanup'], true)) {
    if (! isAdmin()) {
        jsonResponse(['error' => 'Forbidden'], 403);
    }
    if ($action === 'admin_get_startups') {
        jsonResponse(['ok' => true, 'data' => StartupDirectory::overview($pdo)]);
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(['ok' => false, 'message' => 'Metode tidak diizinkan'], 405);
    }

    try {
        $startupId = (int) ($_POST['id'] ?? 0);
        switch ($action) {
            case 'admin_create_startup':
                StartupDirectory::create($pdo, (string) ($_POST['name'] ?? ''));
                jsonResponse(['ok' => true]);
                // no break
            case 'admin_rename_startup':
                StartupDirectory::rename($pdo, $startupId, (string) ($_POST['name'] ?? ''));
                jsonResponse(['ok' => true]);
                // no break
            case 'admin_set_startup_active':
                StartupDirectory::setActive($pdo, $startupId, ($_POST['active'] ?? '') === '1');
                jsonResponse(['ok' => true]);
                // no break
            case 'admin_merge_startup':
                $merged = StartupDirectory::merge($pdo, $startupId, (int) ($_POST['target_id'] ?? 0));
                AdminActivity::record($pdo, $action, $merged['summary'], null, $merged['undo'], $_SESSION);
                jsonResponse(['ok' => true, 'moved' => $merged['moved']]);
                // no break
            case 'admin_sync_startups':
                jsonResponse(['ok' => true] + StartupDirectory::syncPortal($pdo, StartupDirectory::fetchPortal()));
                // no break
            case 'admin_apply_startup_cleanup':
                $mapping = json_decode((string) ($_POST['mapping'] ?? ''), true);
                if (! is_array($mapping)) {
                    throw new DomainException('Data penyesuaian tidak valid.');
                }
                $cleaned = StartupDirectory::applyCleanup($pdo, $mapping);
                AdminActivity::record($pdo, $action, $cleaned['summary'], null, $cleaned['undo'], $_SESSION);
                jsonResponse(['ok' => true, 'moved' => $cleaned['moved']]);
        }
    } catch (DomainException $e) {
        jsonResponse(['ok' => false, 'message' => $e->getMessage()], 400);
    }
}
