<?php

// Extracted from ajax_handler.php — action group: holiday
// Variables in scope: $pdo, $action, $archivedExcludeQuery, $archivedExcludeUsersQuery

if ($action === 'admin_get_manual_holidays') {
    if (! isAdmin()) {
        jsonResponse(['error' => 'Forbidden'], 403);
    }
    $start = $_GET['start'] ?? ($_POST['start'] ?? date('Y-01-01'));
    $end = $_GET['end'] ?? ($_POST['end'] ?? date('Y-12-31'));
    $rows = getManualHolidaysInRange($pdo, $start, $end);
    jsonResponse(['ok' => true, 'data' => $rows]);
}

if ($action === 'admin_add_manual_holiday' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (! isAdmin()) {
        jsonResponse(['error' => 'Forbidden'], 403);
    }
    $date = $_POST['date'] ?? '';
    $name = trim($_POST['name'] ?? 'Libur Manual');
    if (! $date) {
        jsonResponse(['ok' => false, 'message' => 'Tanggal wajib diisi'], 400);
    }

    // Validate date format
    if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        jsonResponse(['ok' => false, 'message' => 'Format tanggal tidak valid. Gunakan YYYY-MM-DD'], 400);
    }

    try {
        // Check if table exists and has correct structure
        $checkTable = $pdo->query("SHOW TABLES LIKE 'manual_holidays'");
        if ($checkTable->rowCount() == 0) {
            error_log('manual_holidays table does not exist');
            jsonResponse(['ok' => false, 'message' => 'Tabel manual_holidays tidak ditemukan'], 500);
        }

        // Table structure check — log dihapus (bukan hot fix lagi)

        // Validate user session
        $userId = $_SESSION['user']['id'] ?? null;
        if (! $userId) {
            jsonResponse(['ok' => false, 'message' => 'Session tidak valid'], 400);
        }

        // Check if date already exists
        $checkDate = $pdo->prepare('SELECT id FROM manual_holidays WHERE date = :d LIMIT 1');
        $checkDate->execute([':d' => $date]);
        $existingId = $checkDate->fetchColumn();

        if ($existingId) {
            // Update existing record
            $stmt = $pdo->prepare('UPDATE manual_holidays SET name = :n, created_by = :u WHERE id = :id');
            $result = $stmt->execute([':n' => $name, ':u' => $userId, ':id' => $existingId]);
            $message = 'Hari libur manual diperbarui';
        } else {
            // Insert new record
            $stmt = $pdo->prepare('INSERT INTO manual_holidays(date,name,created_by) VALUES(:d,:n,:u)');
            $result = $stmt->execute([':d' => $date, ':n' => $name, ':u' => $userId]);
            $message = 'Hari libur manual disimpan';
        }

        if ($result) {
            triggerDatabaseBackup();
            jsonResponse(['ok' => true, 'message' => $message]);
        } else {
            error_log('Failed to execute manual holiday insert/update');
            jsonResponse(['ok' => false, 'message' => 'Gagal menyimpan hari libur'], 500);
        }
    } catch (PDOException $e) {
        error_log('add manual holiday error: '.$e->getMessage());
        error_log('SQL State: '.$e->getCode());
        error_log('Error Info: '.print_r($e->errorInfo, true));
        jsonResponse(['ok' => false, 'message' => 'Gagal menyimpan hari libur: '.$e->getMessage()], 500);
    }
}

if ($action === 'admin_delete_manual_holiday' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (! isAdmin()) {
        jsonResponse(['error' => 'Forbidden'], 403);
    }
    $id = (int) ($_POST['id'] ?? 0);
    if (! $id) {
        jsonResponse(['ok' => false, 'message' => 'ID tidak valid'], 400);
    }
    $pdo->prepare('DELETE FROM manual_holidays WHERE id=:id')->execute([':id' => $id]);
    triggerDatabaseBackup();
    jsonResponse(['ok' => true]);
}

if ($action === 'get_manual_holidays') {
    if (! isAdmin()) {
        jsonResponse(['error' => 'Forbidden'], 403);
    }
    // Check if table exists first preventing error if not migrated
    try {
        $stmt = $pdo->query('SELECT * FROM manual_holidays ORDER BY date DESC');
        jsonResponse(['ok' => true, 'data' => $stmt->fetchAll()]);
    } catch (PDOException $e) {
        jsonResponse(['ok' => true, 'data' => [], 'message' => 'Table not found or empty']);
    }
}

if ($action === 'add_manual_holiday' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (! isAdmin()) {
        jsonResponse(['error' => 'Forbidden'], 403);
    }
    $date = $_POST['date'] ?? '';
    $desc = $_POST['description'] ?? '';

    if (! $date || ! $desc) {
        jsonResponse(['ok' => false, 'message' => 'Tanggal dan keterangan harus diisi'], 400);
    }

    try {
        // Insert into 'name' column appropriately
        $stmt = $pdo->prepare('INSERT INTO manual_holidays (date, name) VALUES (:d, :n)');
        $stmt->execute([':d' => $date, ':n' => $desc]);
        jsonResponse(['ok' => true]);
    } catch (PDOException $e) {
        jsonResponse(['ok' => false, 'message' => 'Gagal menyimpan: '.$e->getMessage()], 500);
    }
}

if ($action === 'delete_manual_holiday' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (! isAdmin()) {
        jsonResponse(['error' => 'Forbidden'], 403);
    }
    $id = (int) ($_POST['id'] ?? 0);
    if (! $id) {
        jsonResponse(['ok' => false, 'message' => 'ID tidak valid'], 400);
    }

    try {
        $stmt = $pdo->prepare('DELETE FROM manual_holidays WHERE id = :id');
        $stmt->execute([':id' => $id]);
        jsonResponse(['ok' => true]);
    } catch (PDOException $e) {
        jsonResponse(['ok' => false, 'message' => 'Gagal menghapus: '.$e->getMessage()], 500);
    }
}
