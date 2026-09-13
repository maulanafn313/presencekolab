<?php

// Extracted from ajax_handler.php — action group: help
// Variables in scope: $pdo, $action, $archivedExcludeQuery, $archivedExcludeUsersQuery

if ($action === 'submit_help_request' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $uid = (int) ($_SESSION['user']['id'] ?? 0);
    if (! $uid) {
        jsonResponse(['error' => 'Unauthorized'], 401);
    }

    $type = $_POST['request_type'] ?? '';

    $params = [':u' => $uid, ':t' => $type];
    $fields = ['user_id', 'request_type'];
    $values = [':u', ':t'];

    if ($type === 'past_attendance') {
        $fields = array_merge($fields, ['alasan_izin', 'jenis_izin', 'bukti_izin', 'tanggal']);
        $values = array_merge($values, [':alasan', ':jenis', ':bukti', ':tanggal']);
        $params[':alasan'] = $_POST['alasan_izin'] ?? '';
        $params[':jenis'] = $_POST['jenis_izin'] ?? 'izin';
        $bukti = $_POST['bukti_izin'] ?? null;
        if ($bukti && strpos($bukti, 'data:image/') === 0) {
            $bukti = saveBase64Image($bukti, 'help_requests');
        }
        $params[':bukti'] = $bukti;
        $params[':tanggal'] = $_POST['tanggal'] ?? date('Y-m-d');
    } elseif ($type === 'late_attendance') {
        $fields = array_merge($fields, ['tanggal', 'jam_masuk', 'jam_pulang', 'bukti_presensi', 'lokasi_presensi', 'attendance_type', 'attendance_reason']);
        $values = array_merge($values, [':tanggal', ':jm', ':jp', ':bukti', ':lokasi', ':att_type', ':att_reason']);
        $params[':tanggal'] = $_POST['tanggal'] ?? date('Y-m-d');
        $params[':jm'] = $_POST['jam_masuk'] ?? null;
        $params[':jp'] = $_POST['jam_pulang'] ?? null;
        $bukti = $_POST['bukti_presensi'] ?? null;
        if ($bukti && strpos($bukti, 'data:image/') === 0) {
            $bukti = saveBase64Image($bukti, 'help_requests');
        }
        $params[':bukti'] = $bukti;
        $params[':lokasi'] = $_POST['lokasi_presensi'] ?? '';
        $params[':att_type'] = $_POST['attendance_type'] ?? 'wfo';
        $params[':att_reason'] = $_POST['attendance_reason'] ?? null;
    } elseif ($type === 'bug_report') {
        $fields = array_merge($fields, ['bug_description', 'bug_proof']);
        $values = array_merge($values, [':desc', ':proof']);
        $params[':desc'] = $_POST['bug_description'] ?? '';
        $proof = $_POST['bug_proof'] ?? null;
        if ($proof && strpos($proof, 'data:image/') === 0) {
            $proof = saveBase64Image($proof, 'help_requests');
        }
        $params[':proof'] = $proof;
    } else {
        jsonResponse(['ok' => false, 'message' => 'Tipe request tidak valid'], 400);
    }

    $sql = 'INSERT INTO admin_help_requests ('.implode(',', $fields).') VALUES ('.implode(',', $values).')';
    try {
        $stmt = $pdo->prepare($sql);
        if ($stmt->execute($params)) {
            jsonResponse(['ok' => true, 'message' => 'Request berhasil dikirim dan menunggu persetujuan admin.']);
        } else {
            jsonResponse(['ok' => false, 'message' => 'Gagal mengirim request.'], 500);
        }
    } catch (PDOException $e) {
        error_log('submit_help_request error: '.$e->getMessage());
        if ($e->getCode() == '22001' || strpos($e->getMessage(), '1406') !== false) {
            jsonResponse(['ok' => false, 'message' => 'Gagal mengirim request: Ukuran file bukti terlalu besar. Silakan gunakan foto dengan resolusi lebih rendah atau kompres foto Anda sebelum mengunggah.'], 400);
        }
        jsonResponse(['ok' => false, 'message' => 'Gagal memuat data: '.$e->getMessage()], 500);
    }
}

// User: Get their own help request status list

if ($action === 'get_user_help_requests') {
    $uid = (int) ($_SESSION['user']['id'] ?? 0);
    if (! $uid) {
        jsonResponse(['error' => 'Unauthorized'], 401);
    }

    try {
        $stmt = $pdo->prepare(
            'SELECT id, request_type, status, admin_note, created_at,
                        tanggal, jenis_izin, alasan_izin,
                        jam_masuk, jam_pulang, attendance_type, attendance_reason,
                        bug_description, is_read_by_user
                 FROM admin_help_requests
                 WHERE user_id = :uid
                 ORDER BY created_at DESC
                 LIMIT 20'
        );
        $stmt->execute([':uid' => $uid]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        jsonResponse(['ok' => true, 'data' => $rows]);
    } catch (PDOException $e) {
        // Fallback: select only guaranteed columns if some don't exist
        error_log('get_user_help_requests error: '.$e->getMessage());
        try {
            $stmt2 = $pdo->prepare(
                'SELECT id, request_type, status, admin_note, created_at,
                            tanggal, jenis_izin, alasan_izin,
                            jam_masuk, jam_pulang, attendance_type, attendance_reason,
                            bug_description, is_read_by_user
                     FROM admin_help_requests
                     WHERE user_id = :uid
                     ORDER BY created_at DESC
                     LIMIT 20'
            );
            $stmt2->execute([':uid' => $uid]);
            $rows2 = $stmt2->fetchAll(PDO::FETCH_ASSOC);
            // Add missing attendance_type and attendance_reason as null
            foreach ($rows2 as &$row) {
                $row['attendance_type'] = $row['attendance_type'] ?? null;
                $row['attendance_reason'] = $row['attendance_reason'] ?? null;
            }
            jsonResponse(['ok' => true, 'data' => $rows2]);
        } catch (PDOException $e2) {
            error_log('get_user_help_requests fallback error: '.$e2->getMessage());
            jsonResponse(['ok' => false, 'message' => 'Gagal memuat data: '.$e2->getMessage()], 500);
        }
    }
}

if ($action === 'mark_help_requests_read') {
    $uid = (int) ($_SESSION['user']['id'] ?? 0);
    if (! $uid) {
        jsonResponse(['error' => 'Unauthorized'], 401);
    }

    try {
        // Update all reviewed requests to be 'read'
        $stmt = $pdo->prepare("UPDATE admin_help_requests SET is_read_by_user = 1 WHERE user_id = :uid AND status != 'pending'");
        $stmt->execute([':uid' => $uid]);
        jsonResponse(['ok' => true]);
    } catch (PDOException $e) {
        jsonResponse(['ok' => false, 'message' => $e->getMessage()], 500);
    }
}

if ($action === 'admin_get_help_notifications') {
    if (! isAdmin()) {
        jsonResponse(['error' => 'Forbidden'], 403);
    }

    $stmt = $pdo->query("SELECT r.id, r.user_id, r.request_type, r.tanggal, r.jam_masuk, r.jam_pulang, r.alasan_izin, r.jenis_izin, r.lokasi_presensi, r.bug_description, r.status, r.admin_note, r.created_at, u.nama, u.nim FROM admin_help_requests r JOIN users u ON u.id = r.user_id WHERE r.status = 'pending' AND r.$archivedExcludeQuery ORDER BY r.created_at DESC");
    $rows = $stmt->fetchAll();
    jsonResponse(['ok' => true, 'data' => $rows]);
}

if ($action === 'admin_get_all_help_requests') {
    if (! isAdmin()) {
        jsonResponse(['error' => 'Forbidden'], 403);
    }

    $stmt = $pdo->query("SELECT r.id, r.user_id, r.request_type, r.tanggal, r.jam_masuk, r.jam_pulang, r.alasan_izin, r.jenis_izin, r.lokasi_presensi, r.bug_description, r.status, r.admin_note, r.created_at, u.nama, u.nim FROM admin_help_requests r JOIN users u ON u.id = r.user_id WHERE r.$archivedExcludeQuery ORDER BY r.created_at DESC");
    $rows = $stmt->fetchAll();
    jsonResponse(['ok' => true, 'data' => $rows]);
}

if ($action === 'admin_get_help_request_detail') {
    if (! isAdmin()) {
        jsonResponse(['error' => 'Forbidden'], 403);
    }
    $id = (int) ($_GET['id'] ?? 0);
    $stmt = $pdo->prepare('SELECT r.*, u.nama, u.nim FROM admin_help_requests r JOIN users u ON u.id = r.user_id WHERE r.id = ? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if ($row) {
        jsonResponse(['ok' => true, 'data' => $row]);
    } else {
        jsonResponse(['ok' => false, 'message' => 'Detail tidak ditemukan'], 404);
    }
}

if ($action === 'admin_handle_help_request' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (! isAdmin()) {
        jsonResponse(['error' => 'Forbidden'], 403);
    }

    $id = (int) ($_POST['id'] ?? 0);
    $status = $_POST['status'] ?? ''; // approved / disapproved / solved
    $note = $_POST['admin_note'] ?? ($_POST['note'] ?? '');

    error_log("admin_handle_help_request: ID=$id, Status='$status', Note='$note'");

    if (! in_array($status, ['approved', 'disapproved', 'solved'])) {
        error_log("admin_handle_help_request: Invalid status '$status'");
        jsonResponse(['ok' => false, 'message' => 'Status tidak valid'], 400);
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT * FROM admin_help_requests WHERE id = :id FOR UPDATE');
        $stmt->execute([':id' => $id]);
        $req = $stmt->fetch();

        if (! $req) {
            throw new Exception('Request tidak ditemukan');
        }

        // Allow admin to re-process (change status) even if already processed
        // Only skip attendance side-effects if already approved (to avoid duplicates)
        $wasAlreadyProcessed = ($req['status'] !== 'pending');
        $skipAttendanceSideEffects = $wasAlreadyProcessed && ($req['status'] === 'approved' || $req['status'] === 'solved');

        if (($status === 'approved' || $status === 'solved') && $req['request_type'] !== 'bug_report' && ! $skipAttendanceSideEffects) {
            if ($req['request_type'] === 'past_attendance') {
                // Insert into attendance_notes
                $ins = $pdo->prepare('INSERT INTO attendance_notes (user_id, date, type, keterangan, bukti) VALUES (:u, :d, :t, :k, :b) ON DUPLICATE KEY UPDATE type=VALUES(type), keterangan=VALUES(keterangan), bukti=VALUES(bukti)');
                $ins->execute([
                    ':u' => $req['user_id'],
                    ':d' => $req['tanggal'],
                    ':t' => $req['jenis_izin'],
                    ':k' => $req['alasan_izin'],
                    ':b' => $req['bukti_izin'],
                ]);
            } elseif ($req['request_type'] === 'diff_location_checkout') {
                // Admin approves checkout with different location
                // Find today's attendance record for this user
                $check = $pdo->prepare('SELECT id FROM attendance WHERE user_id = :uid AND DATE(jam_masuk_iso) = :date AND jam_pulang IS NULL LIMIT 1');
                $check->execute([':uid' => $req['user_id'], ':date' => $req['tanggal']]);
                $existing = $check->fetch();

                if ($existing) {
                    // Strip 'diff_location|' prefix to get the clean reason
                    $rawReason = $req['attendance_reason'] ?? '';
                    $cleanReason = strpos($rawReason, 'diff_location|') === 0
                        ? substr($rawReason, strlen('diff_location|'))
                        : $rawReason;

                    $jpISO = $req['tanggal'].' '.$req['jam_pulang'];
                    $upd = $pdo->prepare('UPDATE attendance SET
                            jam_pulang       = :jp,
                            jam_pulang_iso   = :jpi,
                            ekspresi_pulang  = :exp,
                            foto_pulang      = :foto,
                            lokasi_pulang    = :lokasi,
                            lat_pulang       = :lat,
                            lng_pulang       = :lng,
                            alasan_lokasi_berbeda = :alasan
                            WHERE id = :id');
                    $upd->execute([
                        ':jp' => substr($req['jam_pulang'], 0, 5),
                        ':jpi' => $jpISO,
                        ':exp' => $req['ekspresi_pulang'] ?? null,
                        ':foto' => $req['bukti_presensi'],
                        ':lokasi' => $req['lokasi_presensi'],
                        ':lat' => $req['lat_pulang'] ?? null,
                        ':lng' => $req['lng_pulang'] ?? null,
                        ':alasan' => $cleanReason,
                        ':id' => $existing['id'],
                    ]);
                    clearKpiCache($pdo, $req['user_id'], $req['tanggal']);
                    triggerDatabaseBackup();
                }
            } elseif ($req['request_type'] === 'late_attendance') {
                // Determine ket and reason based on request
                $ket = $req['attendance_type'] ?? 'wfo';
                $rawReason = $req['attendance_reason'] ?? null;

                // Strip 'pulang_lebih_awal|' prefix if present (used for label differentiation)
                $isPulangLebihAwal = $rawReason && strpos($rawReason, 'pulang_lebih_awal|') === 0;
                $reason = $isPulangLebihAwal ? substr($rawReason, strlen('pulang_lebih_awal|')) : $rawReason;

                $alasanWfa = ($ket === 'wfa') ? $reason : null;
                $alasanOvertime = ($ket === 'overtime') ? $reason : null;
                $alasanPulang = ($ket !== 'wfa' && $ket !== 'overtime') ? $reason : null;

                $check = $pdo->prepare('SELECT id FROM attendance WHERE user_id = :uid AND DATE(jam_masuk_iso) = :date LIMIT 1');
                $check->execute([':uid' => $req['user_id'], ':date' => $req['tanggal']]);
                $existing = $check->fetch();

                $jmi = $req['tanggal'].' '.$req['jam_masuk'];
                $jpi = $req['jam_pulang'] ? ($req['tanggal'].' '.$req['jam_pulang']) : null;

                // Calculate status
                $calculatedStatus = 'ontime';
                if ($ket !== 'overtime') {
                    $maxOntimeSetting = getSetting($pdo, 'max_ontime_hour', '08:00');
                    if (strpos($maxOntimeSetting, ':') === false) {
                        $maxOntimeSetting = sprintf('%02d:00', (int) $maxOntimeSetting);
                    }
                    if (strlen($req['jam_masuk']) >= 5) {
                        $jmTime = substr($req['jam_masuk'], 0, 5); // Assumes HH:MM format
                        if ($jmTime > $maxOntimeSetting) {
                            $calculatedStatus = 'terlambat';
                        }
                    }
                }

                if ($existing) {
                    if ($req['jam_pulang']) {
                        // Pulang Lebih Awal approval - only update exit details and reasons
                        $upd = $pdo->prepare('UPDATE attendance SET 
                                jam_pulang = :jp, 
                                jam_pulang_iso = :jpi, 
                                foto_pulang = :sp, 
                                lokasi_pulang = :lp, 
                                alasan_pulang_awal = :ap 
                                WHERE id = :id');
                        $upd->execute([
                            ':jp' => substr($req['jam_pulang'], 0, 5),
                            ':jpi' => $jpi,
                            ':sp' => $req['bukti_presensi'],
                            ':lp' => $req['lokasi_presensi'],
                            ':ap' => $reason,
                            ':id' => $existing['id'],
                        ]);
                    } else {
                        // Check-in update or general adjustment
                        $upd = $pdo->prepare('UPDATE attendance SET 
                                ket = :ket, 
                                alasan_wfa = :aw, 
                                alasan_overtime = :ao 
                                WHERE id = :id');
                        $upd->execute([
                            ':ket' => $ket,
                            ':aw' => $alasanWfa,
                            ':ao' => $alasanOvertime,
                            ':id' => $existing['id'],
                        ]);
                    }
                } else {
                    // Insert new record (e.g. WFA, Overtime, Lupa Presensi)
                    $ins = $pdo->prepare('INSERT INTO attendance (user_id, jam_masuk, jam_masuk_iso, foto_masuk, lokasi_masuk, jam_pulang, jam_pulang_iso, foto_pulang, lokasi_pulang, ket, alasan_wfa, alasan_overtime, alasan_pulang_awal, status) 
                            VALUES (:uid, :jm, :jmi, :sm, :lm, :jp, :jpi, :sp, :lp, :ket, :aw, :ao, :ap, :status)');
                    $ins->execute([
                        ':uid' => $req['user_id'],
                        ':jm' => substr($req['jam_masuk'], 0, 5),
                        ':jmi' => $jmi,
                        ':sm' => $req['bukti_presensi'],
                        ':lm' => $req['lokasi_presensi'],
                        ':jp' => $req['jam_pulang'] ? substr($req['jam_pulang'], 0, 5) : null,
                        ':jpi' => $jpi,
                        ':sp' => $req['jam_pulang'] ? $req['bukti_presensi'] : null,
                        ':lp' => $req['jam_pulang'] ? $req['lokasi_presensi'] : null,
                        ':ket' => $ket,
                        ':aw' => $alasanWfa,
                        ':ao' => $alasanOvertime,
                        ':ap' => $alasanPulang,
                        ':status' => $calculatedStatus,
                    ]);
                }
            }
        }

        $upd = $pdo->prepare('UPDATE admin_help_requests SET status = :s, admin_note = :n WHERE id = :id');
        $upd->execute([':s' => $status, ':n' => $note, ':id' => $id]);

        $pdo->commit();

        if ($status === 'approved' || $status === 'solved') {
            clearKpiCache($pdo, $req['user_id'], $req['tanggal']);
        }
        $msg = 'Request berhasil ';
        if ($status === 'solved') {
            $msg .= 'diselesaikan';
        } elseif ($status === 'approved') {
            $msg .= 'disetujui';
        } else {
            $msg .= 'ditolak';
        }
        jsonResponse(['ok' => true, 'message' => $msg]);
    } catch (Exception $e) {
        $pdo->rollBack();
        jsonResponse(['ok' => false, 'message' => 'Gagal memproses: '.$e->getMessage()], 500);
    }
}

// Admin: Manage Manual Holidays

if ($action === 'pegawai_get_notifications') {
    $uid = (int) ($_SESSION['user']['id'] ?? 0);
    if (! $uid) {
        jsonResponse(['error' => 'Unauthorized'], 401);
    }

    $filter = $_GET['filter'] ?? 'unread'; // unread, read

    $sql = "SELECT r.* FROM admin_help_requests r WHERE r.user_id = :u AND r.status != 'pending' ";
    $params = [':u' => $uid];

    if ($filter === 'unread') {
        $sql .= ' AND r.is_read_by_user = 0 ';
    } elseif ($filter === 'read') {
        $sql .= ' AND r.is_read_by_user = 1 ';
    }

    $sql .= ' ORDER BY r.created_at DESC LIMIT 50';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    jsonResponse(['ok' => true, 'data' => $rows]);
}

if ($action === 'pegawai_mark_notifications_read' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $uid = (int) ($_SESSION['user']['id'] ?? 0);
    if (! $uid) {
        jsonResponse(['error' => 'Unauthorized'], 401);
    }

    $stmt = $pdo->prepare("UPDATE admin_help_requests SET is_read_by_user = 1 WHERE user_id = :u AND status != 'pending'");
    if ($stmt->execute([':u' => $uid])) {
        jsonResponse(['ok' => true]);
    } else {
        jsonResponse(['ok' => false, 'message' => 'Gagal memperbarui notifikasi'], 500);
    }
}
