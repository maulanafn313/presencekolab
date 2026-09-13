<?php

// Extracted from ajax_handler.php — action group: group
// Variables in scope: $pdo, $action, $archivedExcludeQuery, $archivedExcludeUsersQuery

if ($action === 'get_intern_groups') {
    if (! isAdmin()) {
        jsonResponse(['error' => 'Forbidden'], 403);
    }

    $stmt = $pdo->query("
            SELECT ig.*,
                COUNT(igm.user_id) as member_count,
                GROUP_CONCAT(u.nama ORDER BY u.nama SEPARATOR '||') as member_names,
                GROUP_CONCAT(u.id ORDER BY u.nama SEPARATOR ',') as member_ids,
                GROUP_CONCAT(IFNULL(u.foto_base64,'') ORDER BY u.nama SEPARATOR '||') as member_photos
            FROM intern_groups ig
            LEFT JOIN intern_group_members igm ON igm.group_id = ig.id
            LEFT JOIN users u ON u.id = igm.user_id
            GROUP BY ig.id
            ORDER BY ig.is_archived ASC, ig.tanggal_mulai DESC
        ");
    $rows = $stmt->fetchAll();
    // Parse member lists
    foreach ($rows as &$row) {
        $row['member_count'] = (int) $row['member_count'];
        $row['members'] = [];
        if (! empty($row['member_names'])) {
            $names = explode('||', $row['member_names']);
            $ids = explode(',', $row['member_ids']);
            $photos = explode('||', $row['member_photos'] ?? '');
            foreach ($names as $i => $name) {
                $photoVal = $photos[$i] ?? '';
                $row['members'][] = [
                    'id' => (int) ($ids[$i] ?? 0),
                    'nama' => $name,
                    'foto_base64' => (strlen($photoVal) > 50) ? $photoVal : null,
                ];
            }
        }
        unset($row['member_names'], $row['member_ids'], $row['member_photos']);
    }
    unset($row);
    jsonResponse(['ok' => true, 'data' => $rows]);
}

if ($action === 'save_intern_group' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (! isAdmin()) {
        jsonResponse(['error' => 'Forbidden'], 403);
    }
    $id = (int) ($_POST['id'] ?? 0);
    $nama = trim($_POST['nama'] ?? '');
    $tanggalMulai = trim($_POST['tanggal_mulai'] ?? '');
    $tanggalSelesai = trim($_POST['tanggal_selesai'] ?? '');
    if (! $nama || ! $tanggalMulai || ! $tanggalSelesai) {
        jsonResponse(['ok' => false, 'message' => 'Nama, tanggal mulai, dan tanggal selesai wajib diisi'], 400);
    }
    if ($tanggalSelesai < $tanggalMulai) {
        jsonResponse(['ok' => false, 'message' => 'Tanggal selesai harus setelah tanggal mulai'], 400);
    }
    if ($id > 0) {
        $stmt = $pdo->prepare('UPDATE intern_groups SET nama=:nama, tanggal_mulai=:mulai, tanggal_selesai=:selesai WHERE id=:id');
        $stmt->execute([':nama' => $nama, ':mulai' => $tanggalMulai, ':selesai' => $tanggalSelesai, ':id' => $id]);
        jsonResponse(['ok' => true, 'message' => 'Kelompok berhasil diperbarui']);
    } else {
        $stmt = $pdo->prepare('INSERT INTO intern_groups (nama, tanggal_mulai, tanggal_selesai) VALUES (:nama, :mulai, :selesai)');
        $stmt->execute([':nama' => $nama, ':mulai' => $tanggalMulai, ':selesai' => $tanggalSelesai]);
        jsonResponse(['ok' => true, 'message' => 'Kelompok berhasil dibuat', 'id' => (int) $pdo->lastInsertId()]);
    }
}

if ($action === 'delete_intern_group' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (! isAdmin()) {
        jsonResponse(['error' => 'Forbidden'], 403);
    }
    $id = (int) ($_POST['id'] ?? 0);
    if (! $id) {
        jsonResponse(['ok' => false, 'message' => 'ID tidak valid'], 400);
    }
    // Check if archived
    $chk = $pdo->prepare('SELECT is_archived FROM intern_groups WHERE id=:id LIMIT 1');
    $chk->execute([':id' => $id]);
    $grp = $chk->fetch();
    if (! $grp) {
        jsonResponse(['ok' => false, 'message' => 'Kelompok tidak ditemukan'], 404);
    }
    if ($grp['is_archived']) {
        jsonResponse(['ok' => false, 'message' => 'Kelompok yang sudah di-archive tidak bisa dihapus. Unarchive terlebih dahulu.'], 400);
    }
    $pdo->prepare('DELETE FROM intern_groups WHERE id=:id')->execute([':id' => $id]);
    jsonResponse(['ok' => true, 'message' => 'Kelompok berhasil dihapus']);
}

if ($action === 'get_group_members') {
    if (! isAdmin()) {
        jsonResponse(['error' => 'Forbidden'], 403);
    }
    $groupId = (int) ($_GET['group_id'] ?? 0);
    if (! $groupId) {
        jsonResponse(['ok' => false, 'message' => 'group_id wajib'], 400);
    }
    $stmt = $pdo->prepare('SELECT u.id, u.nama, u.nim, u.prodi, u.startup FROM users u JOIN intern_group_members igm ON igm.user_id = u.id WHERE igm.group_id = :gid ORDER BY u.nama');
    $stmt->execute([':gid' => $groupId]);
    jsonResponse(['ok' => true, 'data' => $stmt->fetchAll()]);
}

if ($action === 'assign_members_to_group' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (! isAdmin()) {
        jsonResponse(['error' => 'Forbidden'], 403);
    }
    $groupId = (int) ($_POST['group_id'] ?? 0);
    if (! $groupId) {
        jsonResponse(['ok' => false, 'message' => 'group_id wajib'], 400);
    }
    // Check if archived
    $chk = $pdo->prepare('SELECT is_archived FROM intern_groups WHERE id=:id LIMIT 1');
    $chk->execute([':id' => $groupId]);
    $grp = $chk->fetch();
    if (! $grp) {
        jsonResponse(['ok' => false, 'message' => 'Kelompok tidak ditemukan'], 404);
    }
    if ($grp['is_archived']) {
        jsonResponse(['ok' => false, 'message' => 'Kelompok yang sudah di-archive tidak bisa diubah anggotanya'], 400);
    }

    $userIds = $_POST['user_ids'] ?? [];
    if (is_string($userIds)) {
        $userIds = array_filter(array_map('intval', explode(',', $userIds)));
    } else {
        $userIds = array_filter(array_map('intval', $userIds));
    }

    // Delete existing members and replace
    $pdo->prepare('DELETE FROM intern_group_members WHERE group_id=:gid')->execute([':gid' => $groupId]);
    if (! empty($userIds)) {
        $ins = $pdo->prepare('INSERT IGNORE INTO intern_group_members (group_id, user_id) VALUES (:gid, :uid)');
        foreach ($userIds as $uid) {
            $ins->execute([':gid' => $groupId, ':uid' => $uid]);
        }
    }
    // Clear KPI cache untuk semua anggota agar period start terupdate
    try {
        $allUids = array_merge($userIds, array_column(
            $pdo->prepare('SELECT user_id FROM intern_group_members WHERE group_id=:gid')->execute([':gid' => $groupId]) ? [] : [],
            'user_id'
        ));
        // Lebih aman: hapus cache semua user yang mungkin terdampak
        foreach ($userIds as $mid) {
            $pdo->prepare('DELETE FROM kpi_monthly_cache WHERE user_id=:uid')->execute([':uid' => $mid]);
        }
    } catch (PDOException $e) { /* silent */
    }
    jsonResponse(['ok' => true, 'message' => 'Anggota kelompok berhasil diperbarui']);
}

if ($action === 'archive_intern_group' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (! isAdmin()) {
        jsonResponse(['error' => 'Forbidden'], 403);
    }
    $id = (int) ($_POST['id'] ?? 0);
    if (! $id) {
        jsonResponse(['ok' => false, 'message' => 'ID tidak valid'], 400);
    }
    $chk = $pdo->prepare('SELECT is_archived, (SELECT COUNT(*) FROM intern_group_members WHERE group_id=:id2) as member_count FROM intern_groups WHERE id=:id LIMIT 1');
    $chk->execute([':id' => $id, ':id2' => $id]);
    $grp = $chk->fetch();
    if (! $grp) {
        jsonResponse(['ok' => false, 'message' => 'Kelompok tidak ditemukan'], 404);
    }
    if ($grp['is_archived']) {
        jsonResponse(['ok' => false, 'message' => 'Kelompok sudah di-archive'], 400);
    }
    $pdo->prepare('UPDATE intern_groups SET is_archived=1, archived_at=NOW() WHERE id=:id')->execute([':id' => $id]);
    // Clear KPI cache untuk semua anggota kelompok ini
    try {
        $mStmt = $pdo->prepare('SELECT user_id FROM intern_group_members WHERE group_id=:gid');
        $mStmt->execute([':gid' => $id]);
        $memberIds = array_column($mStmt->fetchAll(), 'user_id');
        foreach ($memberIds as $mid) {
            // Hapus seluruh cache user ini agar KPI terupdate
            $pdo->prepare('DELETE FROM kpi_monthly_cache WHERE user_id=:uid')->execute([':uid' => $mid]);
        }
    } catch (PDOException $e) { /* silent */
    }
    jsonResponse(['ok' => true, 'message' => 'Kelompok berhasil di-archive. Pegawai dalam kelompok ini tidak akan tampil di KPI dan daftar aktif.']);
}

if ($action === 'unarchive_intern_group' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (! isAdmin()) {
        jsonResponse(['error' => 'Forbidden'], 403);
    }
    $id = (int) ($_POST['id'] ?? 0);
    if (! $id) {
        jsonResponse(['ok' => false, 'message' => 'ID tidak valid'], 400);
    }
    $pdo->prepare('UPDATE intern_groups SET is_archived=0, archived_at=NULL WHERE id=:id')->execute([':id' => $id]);
    // Clear KPI cache untuk semua anggota kelompok ini agar muncul kembali di KPI
    try {
        $mStmt = $pdo->prepare('SELECT user_id FROM intern_group_members WHERE group_id=:gid');
        $mStmt->execute([':gid' => $id]);
        $memberIds = array_column($mStmt->fetchAll(), 'user_id');
        foreach ($memberIds as $mid) {
            $pdo->prepare('DELETE FROM kpi_monthly_cache WHERE user_id=:uid')->execute([':uid' => $mid]);
        }
    } catch (PDOException $e) { /* silent */
    }
    jsonResponse(['ok' => true, 'message' => 'Kelompok berhasil di-unarchive. Data pegawai dikembalikan ke KPI dan daftar aktif.']);
}

// Download database untuk pegawai dalam satu kelompok

if ($action === 'export_group_database') {
    if (! isAdmin()) {
        jsonResponse(['error' => 'Forbidden'], 403);
    }
    $groupId = (int) ($_GET['group_id'] ?? 0);
    if (! $groupId) {
        jsonResponse(['ok' => false, 'message' => 'group_id wajib'], 400);
    }

    @ini_set('memory_limit', '512M');
    @set_time_limit(300);

    // Get group info
    $grpStmt = $pdo->prepare('SELECT * FROM intern_groups WHERE id=:id LIMIT 1');
    $grpStmt->execute([':id' => $groupId]);
    $group = $grpStmt->fetch();
    if (! $group) {
        header('HTTP/1.1 404 Not Found');
        echo 'Kelompok tidak ditemukan';
        exit;
    }

    // Get member user_ids
    $mStmt = $pdo->prepare('SELECT user_id FROM intern_group_members WHERE group_id=:gid');
    $mStmt->execute([':gid' => $groupId]);
    $memberIds = array_column($mStmt->fetchAll(), 'user_id');

    if (empty($memberIds)) {
        jsonResponse(['ok' => false, 'message' => 'Kelompok tidak memiliki anggota']);
    }

    $idList = implode(',', array_map('intval', $memberIds));

    $sql = '-- Database Export Kelompok: '.$group['nama']."\n";
    $sql .= '-- Periode: '.$group['tanggal_mulai'].' s/d '.$group['tanggal_selesai']."\n";
    $sql .= '-- Diekspor: '.date('Y-m-d H:i:s')."\n\n";
    $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

    // Export users
    $uStmt = $pdo->query("SELECT id, role, email, nim, nama, prodi, startup, created_at FROM users WHERE id IN ($idList)");
    $users = $uStmt->fetchAll();
    if (! empty($users)) {
        $sql .= "-- USERS (tanpa password & foto)\n";
        foreach ($users as $row) {
            $vals = array_map(function ($v) use ($pdo) {
                return $v === null ? 'NULL' : $pdo->quote($v);
            }, $row);
            $sql .= 'INSERT INTO users ('.implode(', ', array_map(fn ($k) => "`$k`", array_keys($row))).') VALUES ('.implode(', ', $vals).");\n";
        }
        $sql .= "\n";
    }

    // Export attendance
    $aStmt = $pdo->query("SELECT id, user_id, jam_masuk, jam_masuk_iso, status, ket, jam_pulang, jam_pulang_iso, lokasi_masuk, lokasi_pulang, alasan_izin_sakit, created_at FROM attendance WHERE user_id IN ($idList)");
    $attends = $aStmt->fetchAll();
    if (! empty($attends)) {
        $sql .= "-- ATTENDANCE\n";
        foreach ($attends as $row) {
            $vals = array_map(function ($v) use ($pdo) {
                return $v === null ? 'NULL' : $pdo->quote($v);
            }, $row);
            $sql .= 'INSERT INTO attendance ('.implode(', ', array_map(fn ($k) => "`$k`", array_keys($row))).') VALUES ('.implode(', ', $vals).");\n";
        }
        $sql .= "\n";
    }

    // Export attendance_notes
    $nStmt = $pdo->query("SELECT * FROM attendance_notes WHERE user_id IN ($idList)");
    $notes = $nStmt->fetchAll();
    if (! empty($notes)) {
        $sql .= "-- ATTENDANCE NOTES\n";
        foreach ($notes as $row) {
            $vals = array_map(function ($v) use ($pdo) {
                return $v === null ? 'NULL' : $pdo->quote($v);
            }, $row);
            $sql .= 'INSERT INTO attendance_notes ('.implode(', ', array_map(fn ($k) => "`$k`", array_keys($row))).') VALUES ('.implode(', ', $vals).");\n";
        }
        $sql .= "\n";
    }

    // Export monthly_reports
    $mrStmt = $pdo->query("SELECT id, user_id, year, month, summary, status, created_at FROM monthly_reports WHERE user_id IN ($idList)");
    $reports = $mrStmt->fetchAll();
    if (! empty($reports)) {
        $sql .= "-- MONTHLY REPORTS\n";
        foreach ($reports as $row) {
            $vals = array_map(function ($v) use ($pdo) {
                return $v === null ? 'NULL' : $pdo->quote($v);
            }, $row);
            $sql .= 'INSERT INTO monthly_reports ('.implode(', ', array_map(fn ($k) => "`$k`", array_keys($row))).') VALUES ('.implode(', ', $vals).");\n";
        }
        $sql .= "\n";
    }

    $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";

    $groupSlug = preg_replace('/[^a-zA-Z0-9]/', '_', $group['nama']);
    $filename = 'db_kelompok_'.$groupSlug.'_'.date('Y-m-d').'.sql';

    // Clear any output buffering
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="'.$filename.'"');
    header('Content-Length: '.strlen($sql));
    header('Cache-Control: no-cache');
    echo $sql;
    exit;
}
