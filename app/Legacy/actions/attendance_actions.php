<?php

// Extracted from ajax_handler.php — action group: attendance
// Variables in scope: $pdo, $action, $archivedExcludeQuery, $archivedExcludeUsersQuery

if ($action === 'delete_attendance' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (! isAdmin()) {
        jsonResponse(['error' => 'Forbidden'], 403);
    }
    $id = $_POST['id'] ?? '';

    // Check if this is an attendance_notes record (starts with 'note_')
    if (strpos($id, 'note_') === 0) {
        // Extract the actual ID from 'note_123' format
        $actualId = (int) substr($id, 5);

        // Get the attendance_notes record to get user_id and date
        $stmt = $pdo->prepare('SELECT user_id, date FROM attendance_notes WHERE id=:id');
        $stmt->execute([':id' => $actualId]);
        $note = $stmt->fetch();

        if ($note) {
            // Delete related daily report
            $pdo->prepare('DELETE FROM daily_reports WHERE user_id=:user_id AND report_date=:date')->execute([
                ':user_id' => $note['user_id'],
                ':date' => $note['date'],
            ]);
            clearKpiCache($pdo, $note['user_id'], $note['date']);
        }

        $pdo->prepare('DELETE FROM attendance_notes WHERE id=:id')->execute([':id' => $actualId]);
    } else {
        // Regular attendance record
        $actualId = (int) $id;

        // Get the attendance record to get user_id and date
        $stmt = $pdo->prepare('SELECT user_id, DATE(jam_masuk_iso) as report_date FROM attendance WHERE id=:id');
        $stmt->execute([':id' => $actualId]);
        $attendance = $stmt->fetch();

        if ($attendance) {
            // Delete related daily report
            $pdo->prepare('DELETE FROM daily_reports WHERE user_id=:user_id AND report_date=:date')->execute([
                ':user_id' => $attendance['user_id'],
                ':date' => $attendance['report_date'],
            ]);
            clearKpiCache($pdo, $attendance['user_id'], $attendance['report_date']);
        }

        $pdo->prepare('DELETE FROM attendance WHERE id=:id')->execute([':id' => $actualId]);
    }

    // Trigger backup setelah menghapus attendance/notes
    triggerDatabaseBackup();

    jsonResponse(['ok' => true]);
}

// Update bukti izin/sakit
// FaceNet endpoints

if ($action === 'get_attendance') {
    try {
        // Check memory usage before heavy operation
        $memoryUsage = memory_get_usage(true);
        $memoryLimit = ini_get('memory_limit');
        $memoryLimitBytes = return_bytes($memoryLimit);

        if ($memoryUsage > $memoryLimitBytes * 0.8) {
            error_log('Memory usage high before get_attendance: '.round($memoryUsage / 1024 / 1024, 2).'MB');
            jsonResponse(['error' => 'Sistem sedang sibuk, coba lagi dalam beberapa saat'], 503);
        }

        // Get pagination parameters
        $limit = min((int) ($_GET['limit'] ?? 100), 1000); // Reduced default to 100 for safety
        $offset = max((int) ($_GET['offset'] ?? 0), 0);

        // Get date filters if provided
        $startDate = $_GET['start_date'] ?? null;
        $endDate = $_GET['end_date'] ?? null;

        // Admin: all; Pegawai: only their records
        if (isAdmin()) {
            // Build WHERE clause for date filtering
            $whereClause = '1=1';
            $params = [];

            if ($startDate && $endDate) {
                $whereClause .= ' AND DATE(a.jam_masuk_iso) BETWEEN :start_date AND :end_date';
                $params[':start_date'] = $startDate;
                $params[':end_date'] = $endDate;
            }

            // Get regular attendance records with pagination
            // PERFORMANCE: Exclude foto_masuk/foto_pulang/screenshot (large base64 blobs)
            // Photos are lazy-loaded via get_attendance_evidence endpoint
            $sql = "SELECT a.id, a.user_id, a.jam_masuk, a.jam_masuk_iso, a.ekspresi_masuk, a.landmark_masuk, a.lokasi_masuk, a.lat_masuk, a.lng_masuk,
                    a.jam_pulang, a.jam_pulang_iso, a.ekspresi_pulang, a.landmark_pulang, a.lokasi_pulang, a.lat_pulang, a.lng_pulang,
                    a.status, a.ket, a.alasan_wfa, a.alasan_overtime, a.lokasi_overtime, a.alasan_izin_sakit, a.daily_report_id, a.created_at,
                    u.nim, u.nama, u.startup,
                    IF((a.foto_masuk IS NOT NULL AND a.foto_masuk != '') OR (a.screenshot_masuk IS NOT NULL AND a.screenshot_masuk != '') OR (a.landmark_masuk IS NOT NULL AND a.landmark_masuk != ''), 1, 0) as has_sm,
                    IF((a.foto_pulang IS NOT NULL AND a.foto_pulang != '') OR (a.screenshot_pulang IS NOT NULL AND a.screenshot_pulang != '') OR (a.landmark_pulang IS NOT NULL AND a.landmark_pulang != ''), 1, 0) as has_sp,
                    IF(a.bukti_izin_sakit IS NOT NULL AND a.bukti_izin_sakit != '', 1, 0) as has_bis,
                    (SELECT dr.status FROM daily_reports dr WHERE dr.user_id=a.user_id AND dr.report_date=DATE(a.jam_masuk_iso) LIMIT 1) AS daily_report_status
                    FROM attendance a 
                    JOIN users u ON u.id=a.user_id 
                    WHERE $whereClause
                    AND a.$archivedExcludeQuery
                    ORDER BY a.jam_masuk_iso DESC 
                    LIMIT :limit OFFSET :offset";

            $stmt = $pdo->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $attendanceData = $stmt->fetchAll();

            // DIAGNOSTIC: Log data to check for foto_masuk presence
            if (count($attendanceData) > 0) {
                $testRow = $attendanceData[0];
                $hasPhoto = ! empty($testRow['foto_masuk']) || ! empty($testRow['foto_pulang']);
                error_log('get_attendance: Found '.count($attendanceData)." rows. First row ID: {$testRow['id']}, Has photo: ".($hasPhoto ? 'YES' : 'NO'));
                if ($hasPhoto) {
                    error_log('Photo preview: '.substr($testRow['foto_masuk'] ?? $testRow['foto_pulang'], 0, 50).'...');
                }
            } else {
                error_log('get_attendance: No records found for the current query.');
            }

            // Add translated expressions for UI (no photo validation needed - photos excluded from list)
            foreach ($attendanceData as &$row) {
                $row['ekspresi_masuk_label'] = translateExpression($row['ekspresi_masuk'] ?? null);
                $row['ekspresi_masuk_class'] = getExpressionClass($row['ekspresi_masuk'] ?? null);
                $row['ekspresi_pulang_label'] = translateExpression($row['ekspresi_pulang'] ?? null);
                $row['ekspresi_pulang_class'] = getExpressionClass($row['ekspresi_pulang'] ?? null);
            }

            // Get izin/sakit records from attendance_notes with pagination
            $notesWhereClause = '1=1';
            $notesParams = [];

            if ($startDate && $endDate) {
                $notesWhereClause .= ' AND an.date BETWEEN :start_date AND :end_date';
                $notesParams[':start_date'] = $startDate;
                $notesParams[':end_date'] = $endDate;
            }

            // EXCLUDING: bukti to save memory
            $notesSql = "SELECT an.id, an.user_id, an.type, an.date, an.keterangan, an.created_at, u.nim, u.nama, u.startup,
                    IF(an.bukti IS NOT NULL AND an.bukti != '', 1, 0) as has_bukti,
                    (SELECT dr.status FROM daily_reports dr WHERE dr.user_id=an.user_id AND dr.report_date=an.date LIMIT 1) AS daily_report_status
                    FROM attendance_notes an 
                    JOIN users u ON u.id=an.user_id 
                    WHERE $notesWhereClause
                    AND an.$archivedExcludeQuery
                    ORDER BY an.date DESC 
                    LIMIT :limit OFFSET :offset";

            $notesStmt = $pdo->prepare($notesSql);
            foreach ($notesParams as $key => $value) {
                $notesStmt->bindValue($key, $value);
            }
            $notesStmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $notesStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $notesStmt->execute();
            $notesData = $notesStmt->fetchAll();

        } else {
            $uid = (int) $_SESSION['user']['id'];

            // Build WHERE clause for date filtering
            $whereClause = 'a.user_id=:uid';
            $params = [':uid' => $uid];

            if ($startDate && $endDate) {
                $whereClause .= ' AND DATE(a.jam_masuk_iso) BETWEEN :start_date AND :end_date';
                $params[':start_date'] = $startDate;
                $params[':end_date'] = $endDate;
            }

            // Get regular attendance records with pagination
            // PERFORMANCE: Exclude foto_masuk/foto_pulang/screenshot (large base64 blobs)
            $sql = "SELECT a.id, a.user_id, a.jam_masuk, a.jam_masuk_iso, a.ekspresi_masuk, a.landmark_masuk, a.lokasi_masuk, a.lat_masuk, a.lng_masuk,
                    a.jam_pulang, a.jam_pulang_iso, a.ekspresi_pulang, a.landmark_pulang, a.lokasi_pulang, a.lat_pulang, a.lng_pulang,
                    a.status, a.ket, a.alasan_wfa, a.alasan_overtime, a.lokasi_overtime, a.alasan_izin_sakit, a.daily_report_id, a.created_at,
                    u.nim, u.nama, u.startup,
                    IF((a.foto_masuk IS NOT NULL AND a.foto_masuk != '') OR (a.screenshot_masuk IS NOT NULL AND a.screenshot_masuk != '') OR (a.landmark_masuk IS NOT NULL AND a.landmark_masuk != ''), 1, 0) as has_sm,
                    IF((a.foto_pulang IS NOT NULL AND a.foto_pulang != '') OR (a.screenshot_pulang IS NOT NULL AND a.screenshot_pulang != '') OR (a.landmark_pulang IS NOT NULL AND a.landmark_pulang != ''), 1, 0) as has_sp,
                    IF(a.bukti_izin_sakit IS NOT NULL AND a.bukti_izin_sakit != '', 1, 0) as has_bis,
                    (SELECT dr.status FROM daily_reports dr WHERE dr.user_id=a.user_id AND dr.report_date=DATE(a.jam_masuk_iso) LIMIT 1) AS daily_report_status
                    FROM attendance a 
                    JOIN users u ON u.id=a.user_id 
                    WHERE $whereClause
                    ORDER BY a.jam_masuk_iso DESC 
                    LIMIT :limit OFFSET :offset";

            $stmt = $pdo->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $attendanceData = $stmt->fetchAll();

            // Get izin/sakit records from attendance_notes for this user with pagination
            $notesWhereClause = 'an.user_id=:uid';
            $notesParams = [':uid' => $uid];

            if ($startDate && $endDate) {
                $notesWhereClause .= ' AND an.date BETWEEN :start_date AND :end_date';
                $notesParams[':start_date'] = $startDate;
                $notesParams[':end_date'] = $endDate;
            }

            // EXCLUDING: bukti to save memory
            $notesSql = "SELECT an.id, an.user_id, an.type, an.date, an.keterangan, an.created_at, u.nim, u.nama, u.startup,
                    IF(an.bukti IS NOT NULL AND an.bukti != '', 1, 0) as has_bukti,
                    (SELECT dr.status FROM daily_reports dr WHERE dr.user_id=an.user_id AND dr.report_date=an.date LIMIT 1) AS daily_report_status
                    FROM attendance_notes an 
                    JOIN users u ON u.id=an.user_id 
                    WHERE $notesWhereClause
                    ORDER BY an.date DESC 
                    LIMIT :limit OFFSET :offset";

            $notesStmt = $pdo->prepare($notesSql);
            foreach ($notesParams as $key => $value) {
                $notesStmt->bindValue($key, $value);
            }
            $notesStmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $notesStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $notesStmt->execute();
            $notesData = $notesStmt->fetchAll();
        }

        // Convert notes data to attendance format (only if notes exist)
        if (! empty($notesData)) {
            foreach ($notesData as $note) {
                $attendanceData[] = [
                    'id' => 'note_'.$note['id'],
                    'user_id' => $note['user_id'],
                    'nim' => $note['nim'],
                    'nama' => $note['nama'],
                    'startup' => $note['startup'],
                    'jam_masuk' => '08:00',
                    'jam_masuk_iso' => $note['date'].' 08:00:00',
                    'ekspresi_masuk' => null,
                    'landmark_masuk' => null,
                    'lokasi_masuk' => null,
                    'lat_masuk' => null,
                    'lng_masuk' => null,
                    'jam_pulang' => '17:00',
                    'jam_pulang_iso' => $note['date'].' 17:00:00',
                    'ekspresi_pulang' => null,
                    'landmark_pulang' => null,
                    'lokasi_pulang' => null,
                    'lat_pulang' => null,
                    'lng_pulang' => null,
                    'status' => 'ontime',
                    'ket' => $note['type'],
                    'alasan_wfa' => null,
                    'alasan_izin_sakit' => $note['keterangan'],
                    'has_bukti' => (bool) $note['has_bukti'],
                    'daily_report_id' => null,
                    'created_at' => $note['created_at'],
                    'daily_report_status' => $note['daily_report_status'],
                    'is_note' => true,
                ];
            }

            // Sort combined data by date descending (only if we have notes)
            usort($attendanceData, function ($a, $b) {
                return strtotime($b['jam_masuk_iso']) - strtotime($a['jam_masuk_iso']);
            });
        }

        jsonResponse(['ok' => true, 'data' => $attendanceData, 'limit' => $limit, 'offset' => $offset]);

    } catch (PDOException $e) {
        error_log('Database error in get_attendance: '.$e->getMessage());
        jsonResponse(['error' => 'Gagal memuat data presensi. Silakan refresh halaman.'], 500);
    } catch (Exception $e) {
        error_log('Error in get_attendance: '.$e->getMessage());
        jsonResponse(['error' => 'Terjadi kesalahan. Silakan coba lagi.'], 500);
    }
}

if ($action === 'get_today_attendance') {
    $type = $_POST['type'] ?? 'masuk';
    $today = getNetworkTime()->format('Y-m-d');

    if ($type === 'masuk') {
        $stmt = $pdo->prepare("
                SELECT a.id, a.jam_masuk, a.jam_masuk_iso, a.lokasi_masuk, a.ekspresi_masuk, u.nama, u.startup,
                a.landmark_masuk, a.foto_masuk, a.screenshot_masuk,
                IF((a.foto_masuk IS NOT NULL AND a.foto_masuk != '') OR (a.screenshot_masuk IS NOT NULL AND a.screenshot_masuk != '') OR (a.landmark_masuk IS NOT NULL AND a.landmark_masuk != ''), 1, 0) as has_sm
                FROM attendance a 
                JOIN users u ON u.id = a.user_id 
                WHERE DATE(a.jam_masuk_iso) = :today 
                AND a.jam_masuk IS NOT NULL 
                AND a.jam_masuk != ''
                AND a.$archivedExcludeQuery
                ORDER BY a.jam_masuk_iso DESC
            ");
    } else {
        $stmt = $pdo->prepare("
                SELECT a.id, a.jam_pulang, a.jam_pulang_iso, a.lokasi_pulang, a.ekspresi_pulang, u.nama, u.startup,
                a.landmark_pulang, a.foto_pulang, a.screenshot_pulang,
                IF((a.foto_pulang IS NOT NULL AND a.foto_pulang != '') OR (a.screenshot_pulang IS NOT NULL AND a.screenshot_pulang != '') OR (a.landmark_pulang IS NOT NULL AND a.landmark_pulang != ''), 1, 0) as has_sp
                FROM attendance a 
                JOIN users u ON u.id = a.user_id 
                WHERE DATE(a.jam_pulang_iso) = :today 
                AND a.jam_pulang IS NOT NULL 
                AND a.jam_pulang != ''
                AND a.$archivedExcludeQuery
                ORDER BY a.jam_pulang_iso DESC
            ");
    }

    $stmt->execute([':today' => $today]);
    $rows = $stmt->fetchAll();

    foreach ($rows as &$row) {
        $placeholder = 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIyMDAiIGhlaWdodD0iMjAwIiB2aWV3Qm94PSIwIDAgMjAwIDIwMCI+PHJlY3Qgd2lkdGg9IjEwMCUiIGhlaWdodD0iMTAwJSIgZmlsbD0iI2Y4ZDdkNyIvPjx0ZXh0IHg9IjUwJSIgeT0iNTAlIiBmb250LXNpemU9IjE2IiB0ZXh0LWFuY2hvcj0ibWlkZGxlIiBmaWxsPSIjNzIxYzI0IiBkeT0iLjNlbSI+RGF0YSBDb3JydXB0ZWQ8L3RleHQ+PC9zdmc+';

        // Validate all potential photo columns
        if (isset($row['foto_masuk']) && strpos($row['foto_masuk'], 'data:image/') === 0 && strlen($row['foto_masuk']) < 500) {
            $row['foto_masuk'] = $placeholder;
        }
        if (isset($row['screenshot_masuk']) && strpos($row['screenshot_masuk'], 'data:image/') === 0 && strlen($row['screenshot_masuk']) < 500) {
            $row['screenshot_masuk'] = $placeholder;
        }
        if (isset($row['foto_pulang']) && strpos($row['foto_pulang'], 'data:image/') === 0 && strlen($row['foto_pulang']) < 500) {
            $row['foto_pulang'] = $placeholder;
        }
        if (isset($row['screenshot_pulang']) && strpos($row['screenshot_pulang'], 'data:image/') === 0 && strlen($row['screenshot_pulang']) < 500) {
            $row['screenshot_pulang'] = $placeholder;
        }

        if ($type === 'masuk') {
            $row['ekspresi_masuk_label'] = translateExpression($row['ekspresi_masuk'] ?? null);
            $row['ekspresi_masuk_class'] = getExpressionClass($row['ekspresi_masuk'] ?? null);
        } else {
            $row['ekspresi_pulang_label'] = translateExpression($row['ekspresi_pulang'] ?? null);
            $row['ekspresi_pulang_class'] = getExpressionClass($row['ekspresi_pulang'] ?? null);
        }
    }

    // Diagnostic logs dihapus — endpoint ini sering dipanggil dan error_log menyebabkan I/O overhead.

    jsonResponse(['ok' => true, 'data' => $rows]);
}

if ($action === 'get_clockin_location') {
    $nim = $_GET['nim'] ?? '';
    if (! $nim) {
        jsonResponse(['ok' => false, 'message' => 'NIM required'], 400);
    }

    $today = date('Y-m-d');
    $stmt = $pdo->prepare('SELECT lat_masuk, lng_masuk FROM attendance a JOIN users u ON u.id = a.user_id WHERE u.nim = :nim AND DATE(a.jam_masuk_iso) = :today AND a.jam_masuk IS NOT NULL LIMIT 1');
    $stmt->execute([':nim' => $nim, ':today' => $today]);
    $row = $stmt->fetch();

    if ($row) {
        jsonResponse(['ok' => true, 'lat' => (float) $row['lat_masuk'], 'lng' => (float) $row['lng_masuk']]);
    } else {
        jsonResponse(['ok' => false, 'message' => 'No clock-in found today']);
    }
}

if ($action === 'get_attendance_evidence') {
    $id = $_GET['id'] ?? null;
    $type = $_GET['type'] ?? ''; // 'masuk', 'pulang', 'bukti', 'note'

    if (! $id || ! $type) {
        jsonResponse(['ok' => false, 'message' => 'Parameter tidak lengkap'], 400);
    }

    if ($type === 'note') {
        $id = str_replace('note_', '', $id);
        $stmt = $pdo->prepare('SELECT bukti FROM attendance_notes WHERE id = ?');
        $stmt->execute([$id]);
        $res = $stmt->fetch();
        jsonResponse(['ok' => true, 'image' => $res ? $res['bukti'] : null]);
    } else {
        $column = '';
        $altColumn = '';
        $lmCol = '';
        if ($type === 'masuk') {
            $column = 'foto_masuk';
            $altColumn = 'screenshot_masuk';
            $lmCol = 'landmark_masuk';
        } elseif ($type === 'pulang') {
            $column = 'foto_pulang';
            $altColumn = 'screenshot_pulang';
            $lmCol = 'landmark_pulang';
        } elseif ($type === 'bukti') {
            $column = 'bukti_izin_sakit';
        }

        if (! $column) {
            jsonResponse(['ok' => false, 'message' => 'Tipe tidak valid'], 400);
        }

        $sql = "SELECT $column".($altColumn ? ", $altColumn" : '').($lmCol ? ", $lmCol" : '').' FROM attendance WHERE id = :id';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => (int) $id]);
        $row = $stmt->fetch();

        $img = $row[$column] ?? ($altColumn ? ($row[$altColumn] ?? null) : null);

        // Check for legacy truncated data (Base64 strings < 500 chars are usually corrupted)
        if ($img && strpos($img, 'data:image/') === 0 && strlen($img) < 500) {
            // Serve a "Data Corrupted" SVG placeholder
            $img = 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIyMDAiIGhlaWdodD0iMjAwIiB2aWV3Qm94PSIwIDAgMjAwIDIwMCI+PHJlY3Qgd2lkdGg9IjEwMCUiIGhlaWdodD0iMTAwJSIgZmlsbD0iI2Y4ZDdkNyIvPjx0ZXh0IHg9IjUwJSIgeT0iNTAlIiBmb250LXNpemU9IjE2IiB0ZXh0LWFuY2hvcj0ibWlkZGxlIiBmaWxsPSIjNzIxYzI0IiBkeT0iLjNlbSI+RGF0YSBDb3JydXB0ZWQ8L3RleHQ+PC9zdmc+';
        }

        $landmark = $lmCol ? ($row[$lmCol] ?? null) : null;

        // If image is a path, try to read it
        if ($img && strpos($img, 'data:image/') !== 0) {
            // Strip public/ if present because public_path() already points to public folder
            $cleanPath = strpos($img, 'public/') === 0 ? substr($img, 7) : $img;
            $fullPath = public_path($cleanPath);

            if (file_exists($fullPath)) {
                $ext = pathinfo($fullPath, PATHINFO_EXTENSION);
                $data = file_get_contents($fullPath);
                $img = 'data:image/'.($ext === 'jpg' || $ext === 'jpeg' ? 'jpeg' : $ext).';base64,'.base64_encode($data);
            }
        }

        jsonResponse([
            'ok' => true,
            'image' => $img,
            'landmark' => $landmark,
            'has_landmark' => ! empty($landmark),
        ]);
    }
}

if ($action === 'admin_add_absence' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (! isAdmin()) {
        jsonResponse(['error' => 'Forbidden'], 403);
    }

    $date = $_POST['date'] ?? date('Y-m-d');
    $bulkDataRaw = $_POST['bulk_data'] ?? null;
    $dataToProcess = [];

    if ($bulkDataRaw) {
        $dataToProcess = json_decode($bulkDataRaw, true);
        if (! is_array($dataToProcess)) {
            jsonResponse(['ok' => false, 'message' => 'Format data bulk tidak valid'], 400);
        }
    } else {
        // Legacy support for single type/time for multiple users
        $userIdsRaw = $_POST['user_ids'] ?? $_POST['user_id'] ?? '';
        $user_ids = is_array($userIdsRaw) ? $userIdsRaw : explode(',', $userIdsRaw);
        $user_ids = array_filter(array_map('intval', $user_ids));

        $type = $_POST['type'] ?? 'izin';
        $jam_masuk = $_POST['jam_masuk'] ?? null;
        $jam_pulang = $_POST['jam_pulang'] ?? null;
        $alasan = $_POST['alasan_izin_sakit'] ?? $_POST['alasan_wfa'] ?? $_POST['alasan_overtime'] ?? '';
        $lokasi = $_POST['lokasi_overtime'] ?? '';

        foreach ($user_ids as $uid) {
            $dataToProcess[] = [
                'user_id' => $uid,
                'type' => $type,
                'jam_masuk' => $jam_masuk,
                'jam_pulang' => $jam_pulang,
                'alasan' => $alasan,
                'lokasi' => $lokasi,
            ];
        }
    }

    if (empty($dataToProcess)) {
        jsonResponse(['ok' => false, 'message' => 'Pilih minimal satu pegawai'], 400);
    }

    $successCount = 0;
    $errorCount = 0;
    $messages = [];

    foreach ($dataToProcess as $item) {
        $user_id = (int) $item['user_id'];
        $type = $item['type'] ?? 'izin';
        $jam_masuk = $item['jam_masuk'] ?? null;
        $jam_pulang = $item['jam_pulang'] ?? null;
        $alasan = $item['alasan'] ?? '';
        $lokasi = $item['lokasi'] ?? '';

        if (! in_array($type, ['wfo', 'izin', 'sakit', 'wfa', 'overtime'], true)) {
            $errorCount++;

            continue;
        }

        // Logic for setting time based on type
        $jam_masuk_iso = null;
        $jam_pulang_iso = null;
        $status = 'ontime';

        if (in_array($type, ['wfo', 'wfa', 'overtime'])) {
            // If times are missing, use default 08:00 - 17:00
            if (! $jam_masuk) {
                $jam_masuk = '08:00';
            }
            if (! $jam_pulang) {
                $jam_pulang = '17:00';
            }

            $jam_masuk_iso = $date.' '.$jam_masuk.':00';
            $jam_pulang_iso = $date.' '.$jam_pulang.':00';
        } else {
            // For Izin/Sakit, use the selected date with default times
            $jam_masuk_iso = $date.' 08:00:00';
            $jam_pulang_iso = $date.' 17:00:00';
            $jam_masuk = '08:00';
            $jam_pulang = '17:00';
        }

        try {
            // Avoid duplicates for day
            $check = $pdo->prepare('SELECT id FROM attendance WHERE user_id=:u AND DATE(jam_masuk_iso)=:d');
            $check->execute([':u' => $user_id, ':d' => $date]);
            if ($check->fetch()) {
                $errorCount++;

                continue;
            }

            // Double check attendance_notes
            $checkNote = $pdo->prepare('SELECT id FROM attendance_notes WHERE user_id=:u AND `date`=:d');
            $checkNote->execute([':u' => $user_id, ':d' => $date]);
            if ($checkNote->fetch()) {
                $errorCount++;

                continue;
            }

            if (in_array($type, ['izin', 'sakit'])) {
                $sql = 'INSERT INTO attendance_notes (user_id, date, type, keterangan, bukti, created_at) VALUES (:u, :date, :type, :keterangan, :bukti, NOW())';
                $ins = $pdo->prepare($sql);
                $result = $ins->execute([
                    ':u' => $user_id,
                    ':date' => $date,
                    ':type' => $type,
                    ':keterangan' => $alasan ?: 'Tidak ada keterangan',
                    ':bukti' => null,
                ]);
                if ($result) {
                    $successCount++;
                    clearKpiCache($pdo, $user_id, $date);
                } else {
                    $errorCount++;
                }
            } else {
                $sql = 'INSERT INTO attendance (user_id, jam_masuk, jam_masuk_iso, jam_pulang, jam_pulang_iso, status, ket, alasan_wfa, alasan_overtime, lokasi_overtime, created_at) VALUES (:u, :jm, :jmiso, :jp, :jpiso, :s, :ket, :alasan_wfa, :alasan_ot, :lokasi_ot, NOW())';
                $ins = $pdo->prepare($sql);
                $result = $ins->execute([
                    ':u' => $user_id,
                    ':jm' => $jam_masuk,
                    ':jmiso' => $jam_masuk_iso,
                    ':jp' => $jam_pulang,
                    ':jpiso' => $jam_pulang_iso,
                    ':s' => $status,
                    ':ket' => $type,
                    ':alasan_wfa' => ($type === 'wfa' ? $alasan : null),
                    ':alasan_ot' => ($type === 'overtime' ? $alasan : null),
                    ':lokasi_ot' => ($type === 'overtime' ? $lokasi : null),
                ]);
                if ($result) {
                    $successCount++;
                    clearKpiCache($pdo, $user_id, $date);
                } else {
                    $errorCount++;
                }
            }
        } catch (Exception $e) {
            error_log("Bulk absence error for user $user_id: ".$e->getMessage());
            $errorCount++;
        }
    }

    triggerDatabaseBackup();

    if ($successCount > 0) {
        $msg = "Berhasil menyimpan data untuk $successCount pegawai.";
        if ($errorCount > 0) {
            $msg .= " ($errorCount data gagal atau dilewati)";
        }
        jsonResponse(['ok' => true, 'message' => $msg]);
    } else {
        jsonResponse(['ok' => false, 'message' => 'Gagal menyimpan data. Mungkin data sudah ada untuk semua pegawai terpilih.']);
    }
}

// Admin: update attendance row

if ($action === 'admin_update_attendance' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (! isAdmin()) {
        jsonResponse(['error' => 'Forbidden'], 403);
    }
    $id = (int) ($_POST['id'] ?? 0);
    if (! $id) {
        jsonResponse(['ok' => false, 'message' => 'ID tidak valid'], 400);
    }

    // Get current attendance record to check if ket is being changed to izin/sakit
    $currentStmt = $pdo->prepare('SELECT user_id, DATE(jam_masuk_iso) as attendance_date, ket FROM attendance WHERE id = :id');
    $currentStmt->execute([':id' => $id]);
    $currentRecord = $currentStmt->fetch();

    // Debug logging for current record
    error_log('Admin update attendance - Current record query result: '.print_r($currentRecord, true));

    $fields = ['jam_masuk', 'jam_pulang', 'ekspresi_masuk', 'ekspresi_pulang', 'status', 'ket', 'foto_masuk', 'foto_pulang', 'alasan_wfa', 'alasan_overtime', 'lokasi_overtime', 'alasan_izin_sakit', 'bukti_izin_sakit'];
    $set = [];
    $params = [':id' => $id];

    // Get date from current record for ISO time construction
    $datePart = $currentRecord ? date('Y-m-d', strtotime($currentRecord['attendance_date'])) : date('Y-m-d');

    // Handle jam_masuk and jam_masuk_iso
    // Frontend sends jam_masuk in HH:MM:SS format
    if (isset($_POST['jam_masuk']) && $_POST['jam_masuk'] !== '') {
        $jam_masuk_value = $_POST['jam_masuk'];
        // Extract HH:MM for jam_masuk field (remove seconds if present)
        $jam_masuk_hhmm = preg_match('/^(\d{2}:\d{2})/', $jam_masuk_value, $matches) ? $matches[1] : $jam_masuk_value;
        $set[] = 'jam_masuk = :jam_masuk';
        $params[':jam_masuk'] = $jam_masuk_hhmm;

        // Construct ISO time from jam_masuk if not explicitly provided
        if (! isset($_POST['jam_masuk_iso']) || $_POST['jam_masuk_iso'] === '') {
            // Ensure we have full time format (HH:MM:SS)
            $time_part = preg_match('/^(\d{2}:\d{2})(:?\d{2})?$/', $jam_masuk_value, $time_matches)
                ? ($time_matches[2] ? $jam_masuk_value : $jam_masuk_value.':00')
                : $jam_masuk_value;
            $jam_masuk_iso = $datePart.' '.$time_part;
            $set[] = 'jam_masuk_iso = :jmiso';
            $params[':jmiso'] = $jam_masuk_iso;
        }
    }
    // If jam_masuk_iso is explicitly provided, use it
    if (isset($_POST['jam_masuk_iso']) && $_POST['jam_masuk_iso'] !== '' && (! isset($_POST['jam_masuk']) || $_POST['jam_masuk'] === '')) {
        $set[] = 'jam_masuk_iso = :jmiso';
        $params[':jmiso'] = $_POST['jam_masuk_iso'];
    }

    // Handle jam_pulang and jam_pulang_iso
    // Frontend sends jam_pulang in HH:MM:SS format
    if (isset($_POST['jam_pulang']) && $_POST['jam_pulang'] !== '') {
        $jam_pulang_value = $_POST['jam_pulang'];
        // Extract HH:MM for jam_pulang field (remove seconds if present)
        $jam_pulang_hhmm = preg_match('/^(\d{2}:\d{2})/', $jam_pulang_value, $matches) ? $matches[1] : $jam_pulang_value;
        $set[] = 'jam_pulang = :jam_pulang';
        $params[':jam_pulang'] = $jam_pulang_hhmm;

        // Construct ISO time from jam_pulang if not explicitly provided
        if (! isset($_POST['jam_pulang_iso']) || $_POST['jam_pulang_iso'] === '') {
            // Ensure we have full time format (HH:MM:SS)
            $time_part = preg_match('/^(\d{2}:\d{2})(:?\d{2})?$/', $jam_pulang_value, $time_matches)
                ? ($time_matches[2] ? $jam_pulang_value : $jam_pulang_value.':00')
                : $jam_pulang_value;
            $jam_pulang_iso = $datePart.' '.$time_part;
            $set[] = 'jam_pulang_iso = :jpiso';
            $params[':jpiso'] = $jam_pulang_iso;
        }
    }
    // If jam_pulang_iso is explicitly provided, use it
    if (isset($_POST['jam_pulang_iso']) && $_POST['jam_pulang_iso'] !== '' && (! isset($_POST['jam_pulang']) || $_POST['jam_pulang'] === '')) {
        $set[] = 'jam_pulang_iso = :jpiso';
        $params[':jpiso'] = $_POST['jam_pulang_iso'];
    }

    // Handle other fields
    foreach ($fields as $f) {
        if ($f !== 'jam_masuk' && $f !== 'jam_pulang') { // Skip jam_masuk and jam_pulang as they're handled above
            if (isset($_POST[$f])) {
                // Prevent overwriting existing photos/evidence with empty data
                if (in_array($f, ['foto_masuk', 'foto_pulang', 'bukti_izin_sakit']) && $_POST[$f] === '') {
                    continue;
                }
                $set[] = "$f = :$f";
                $params[":$f"] = $_POST[$f] !== '' ? $_POST[$f] : null;
            }
        }
    }

    if (! $set) {
        jsonResponse(['ok' => false, 'message' => 'Tidak ada perubahan'], 400);
    }

    // Check if ket is being changed to izin or sakit
    $newKet = $_POST['ket'] ?? '';
    $isChangingToIzinSakit = in_array($newKet, ['izin', 'sakit']) && $currentRecord;

    // Debug logging
    error_log("Admin update attendance - ID: $id, New ket: '$newKet', Current ket: '{$currentRecord['ket']}', Is changing to izin/sakit: ".($isChangingToIzinSakit ? 'YES' : 'NO'));
    error_log('Admin update attendance - POST data: '.print_r($_POST, true));
    error_log('Admin update attendance - Current record: '.print_r($currentRecord, true));

    if ($isChangingToIzinSakit) {
        // Check if record already exists in attendance_notes
        $checkStmt = $pdo->prepare('SELECT id FROM attendance_notes WHERE user_id = :user_id AND date = :date');
        $checkStmt->execute([
            ':user_id' => $currentRecord['user_id'],
            ':date' => $currentRecord['attendance_date'],
        ]);
        $existingNote = $checkStmt->fetch();

        if ($existingNote) {
            // Update existing record in attendance_notes
            $updateStmt = $pdo->prepare('
                    UPDATE attendance_notes 
                    SET type = :type, keterangan = :keterangan, bukti = :bukti, created_at = NOW()
                    WHERE id = :id
                ');
            $result = $updateStmt->execute([
                ':id' => $existingNote['id'],
                ':type' => $newKet,
                ':keterangan' => $_POST['alasan_izin_sakit'] ?: 'Tidak ada keterangan',
                ':bukti' => $_POST['bukti_izin_sakit'] ?? '',
            ]);

            if ($result) {
                // Delete from attendance table
                $deleteStmt = $pdo->prepare('DELETE FROM attendance WHERE id = :id');
                $deleteStmt->execute([':id' => $id]);

                error_log("Admin successfully updated attendance_notes record {$existingNote['id']} as $newKet for user {$currentRecord['user_id']} on date {$currentRecord['attendance_date']}");
            } else {
                error_log('Admin failed to update attendance_notes record. Error: '.print_r($updateStmt->errorInfo(), true));
            }
        } else {
            // Insert new record to attendance_notes
            $notesStmt = $pdo->prepare('
                    INSERT INTO attendance_notes (user_id, date, type, keterangan, bukti, created_at) 
                    VALUES (:user_id, :date, :type, :keterangan, :bukti, NOW())
                ');
            $result = $notesStmt->execute([
                ':user_id' => $currentRecord['user_id'],
                ':date' => $currentRecord['attendance_date'],
                ':type' => $newKet,
                ':keterangan' => $_POST['alasan_izin_sakit'] ?: 'Tidak ada keterangan',
                ':bukti' => $_POST['bukti_izin_sakit'] ?? '',
            ]);

            if ($result) {
                // Delete from attendance table
                $deleteStmt = $pdo->prepare('DELETE FROM attendance WHERE id = :id');
                $deleteStmt->execute([':id' => $id]);

                error_log("Admin successfully moved attendance record $id to attendance_notes as $newKet for user {$currentRecord['user_id']} on date {$currentRecord['attendance_date']}");
            } else {
                error_log("Admin failed to move attendance record $id to attendance_notes. Error: ".print_r($notesStmt->errorInfo(), true));
            }
        }
    } else {
        // Normal update in attendance table
        error_log("Admin update attendance - Performing normal update in attendance table for ID: $id");
        $sql = 'UPDATE attendance SET '.implode(',', $set).' WHERE id=:id';
        $pdo->prepare($sql)->execute($params);
        error_log("Admin update attendance - Normal update completed for ID: $id");
    }

    if ($currentRecord) {
        clearKpiCache($pdo, $currentRecord['user_id'], $currentRecord['attendance_date']);
    }

    // Trigger backup setelah update attendance
    triggerDatabaseBackup();

    jsonResponse(['ok' => true]);
}

// Admin: update WFA location data to use readable addresses

if ($action === 'admin_bulk_fix_empty_checkout') {
    if (! isAdmin()) {
        jsonResponse(['error' => 'Forbidden'], 403);
    }
    $date = $_POST['date'] ?? date('Y-m-d');

    // Get fallback jam_pulang from settings
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'default_checkout_time'");
    $stmt->execute();
    $fallbackTime = $stmt->fetchColumn() ?: '17:00';

    $stmt = $pdo->prepare("UPDATE attendance SET jam_pulang = :time, jam_pulang_iso = :iso 
                              WHERE DATE(jam_masuk_iso) = :date AND jam_pulang IS NULL AND status_masuk != 'alpha'");
    // Wait, check status columns
    // Let's use simple query for now. Alpha is alpha.
    // Update ALL records with missing clock-outs
    // We use a CASE or CONCAT to ensure jam_pulang_iso matches the original date of attendance
    $stmt = $pdo->prepare("UPDATE attendance 
                              SET jam_pulang = :time, 
                                  jam_pulang_iso = CONCAT(DATE(jam_masuk_iso), ' ', :time2)
                              WHERE (jam_pulang IS NULL OR jam_pulang = '' OR jam_pulang = '-') 
                              AND ket IN ('wfo', 'wfa', 'overtime')");

    $res = $stmt->execute([':time' => $fallbackTime, ':time2' => $fallbackTime.':00']);

    if ($res) {
        jsonResponse(['ok' => true, 'message' => 'Berhasil mengisi jam pulang kosong untuk tanggal '.$date]);
    } else {
        jsonResponse(['ok' => false, 'message' => 'Gagal memperbarui data']);
    }
}

if ($action === 'update_bukti_izin_sakit' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (! isset($_SESSION['user'])) {
        jsonResponse(['error' => 'Unauthorized'], 401);
    }

    $user_id = (int) $_SESSION['user']['id'];
    $date = $_POST['date'] ?? '';
    $bukti = $_POST['bukti'] ?? null;
    $action_type = $_POST['action_type'] ?? ''; // 'update' or 'delete'

    if (! $date) {
        jsonResponse(['ok' => false, 'message' => 'Tanggal diperlukan'], 400);
    }

    if ($action_type === 'delete') {
        // Delete bukti (set to null)
        $stmt = $pdo->prepare('UPDATE attendance_notes SET bukti = NULL WHERE user_id = :user_id AND `date` = :date');
        $stmt->execute([':user_id' => $user_id, ':date' => $date]);
    } elseif ($action_type === 'update' && $bukti) {
        // Validate image data URL and size (<= 5MB)
        if (strpos($bukti, 'data:image/') !== 0) {
            jsonResponse(['ok' => false, 'message' => 'Format bukti tidak valid. Harus berupa gambar.'], 400);
        }
        $sizeCheck = checkImageSize($bukti, 5);
        if (! $sizeCheck['valid']) {
            jsonResponse(['ok' => false, 'message' => $sizeCheck['message']], 400);
        }

        // Update bukti
        $stmt = $pdo->prepare('UPDATE attendance_notes SET bukti = :bukti WHERE user_id = :user_id AND `date` = :date');
        $stmt->execute([':bukti' => $bukti, ':user_id' => $user_id, ':date' => $date]);
    } else {
        jsonResponse(['ok' => false, 'message' => 'Data tidak valid'], 400);
    }

    // Trigger backup setelah update
    triggerDatabaseBackup();

    jsonResponse(['ok' => true, 'message' => 'Bukti berhasil diperbarui']);
}

// Admin: add manual record (Support Bulk)

if ($action === 'submit_izin_sakit' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isAdmin()) {
        jsonResponse(['error' => 'Forbidden'], 403);
    }

    // Ensure authenticated session
    if (! isset($_SESSION['user']) || ! isset($_SESSION['user']['id'])) {
        jsonResponse(['ok' => false, 'message' => 'Unauthorized'], 401);
    }

    // Debug logging
    error_log('submit_izin_sakit: Starting process');
    error_log('submit_izin_sakit: POST data: '.print_r($_POST, true));

    // Test database connection
    try {
        $pdo->query('SELECT 1');
        error_log('submit_izin_sakit: Database connection OK');
    } catch (PDOException $e) {
        error_log('submit_izin_sakit: Database connection failed: '.$e->getMessage());
        jsonResponse(['ok' => false, 'message' => 'Database connection failed'], 500);
    }

    $user_id = (int) $_SESSION['user']['id'];
    $type = $_POST['type'] ?? ''; // izin/sakit
    $alasan = trim($_POST['alasan'] ?? '');
    $bukti = $_POST['bukti'] ?? null; // base64 image

    error_log("submit_izin_sakit: Parsed data - user_id: $user_id, type: $type, alasan: $alasan, bukti length: ".(is_string($bukti) ? strlen($bukti) : 'null'));

    if (! in_array($type, ['izin', 'sakit'], true)) {
        jsonResponse(['ok' => false, 'message' => 'Tipe tidak valid'], 400);
    }

    if (! $alasan) {
        jsonResponse(['ok' => false, 'message' => 'Alasan harus diisi'], 400);
    }

    if (! $bukti || empty($bukti)) {
        jsonResponse(['ok' => false, 'message' => 'Bukti harus diupload'], 400);
    }
    // Validate image data URL and size (<= 5MB)
    if (strpos($bukti, 'data:image/') !== 0) {
        jsonResponse(['ok' => false, 'message' => 'Format bukti tidak valid. Harus berupa gambar.'], 400);
    }
    $sizeCheck = checkImageSize($bukti, 5);
    if (! $sizeCheck['valid']) {
        jsonResponse(['ok' => false, 'message' => $sizeCheck['message']], 400);
    }

    // Validate user exists to avoid foreign key error
    try {
        $chkUser = $pdo->prepare('SELECT id FROM users WHERE id=:id LIMIT 1');
        $chkUser->execute([':id' => $user_id]);
        if (! $chkUser->fetch()) {
            jsonResponse(['ok' => false, 'message' => 'User tidak ditemukan'], 401);
        }
    } catch (PDOException $_) {
        jsonResponse(['ok' => false, 'message' => 'Database error saat validasi user'], 500);
    }

    // Check if already has attendance or notes for today
    $today = date('Y-m-d');
    error_log("submit_izin_sakit: Checking for existing records for user $user_id on $today");

    $checkAttendance = $pdo->prepare('SELECT id FROM attendance WHERE user_id=:uid AND DATE(jam_masuk_iso)=:today');
    $checkAttendance->execute([':uid' => $user_id, ':today' => $today]);
    $existingAttendance = $checkAttendance->fetch();
    error_log('submit_izin_sakit: Existing attendance: '.($existingAttendance ? 'found' : 'none'));

    // Optional: check notes existence (for logging only)
    try {
        $checkNotes = $pdo->prepare('SELECT id FROM attendance_notes WHERE user_id=:uid AND `date`=:today');
        $checkNotes->execute([':uid' => $user_id, ':today' => $today]);
        $hasNotesRow = $checkNotes->fetch();
        error_log('submit_izin_sakit: Existing notes: '.($hasNotesRow ? 'found' : 'none'));
    } catch (PDOException $e) {
        // Table doesn't exist yet, continue
        error_log('Attendance notes table not found when checking existence: '.$e->getMessage());
    }

    // Block ONLY if there is already attendance today
    if ($existingAttendance) {
        error_log('submit_izin_sakit: Blocked - already has attendance today');
        jsonResponse(['ok' => false, 'message' => 'Sudah ada presensi untuk hari ini'], 400);
    }

    // Check if there is already an approved note for today
    try {
        $checkNotes = $pdo->prepare('SELECT id FROM attendance_notes WHERE user_id=:uid AND `date`=:today');
        $checkNotes->execute([':uid' => $user_id, ':today' => $today]);
        if ($checkNotes->fetch()) {
            jsonResponse(['ok' => false, 'message' => 'Data izin/sakit untuk hari ini sudah disetujui oleh admin.'], 400);
        }
    } catch (PDOException $e) {
    }

    // Check if there is already a pending request for today's permission/sickness
    try {
        $checkPending = $pdo->prepare("SELECT id FROM admin_help_requests WHERE user_id=:uid AND tanggal=:today AND status='pending' AND request_type='past_attendance'");
        $checkPending->execute([':uid' => $user_id, ':today' => $today]);
        if ($checkPending->fetch()) {
            jsonResponse(['ok' => false, 'message' => 'Request izin/sakit untuk hari ini sudah terkirim dan sedang menunggu persetujuan admin.'], 400);
        }
    } catch (PDOException $e) {
    }

    // Process proof image - save base64 to file
    if ($bukti && strpos($bukti, 'data:image/') === 0) {
        $buktiPath = saveBase64Image($bukti, 'help_requests');
    } else {
        $buktiPath = $bukti;
    }

    // Insert into admin_help_requests as a draft/request (pending)
    try {
        $sql = "INSERT INTO admin_help_requests (user_id, request_type, tanggal, jenis_izin, alasan_izin, bukti_izin, status) 
                    VALUES (:uid, 'past_attendance', :date, :jenis, :alasan, :bukti, 'pending')";
        $ins = $pdo->prepare($sql);
        $result = $ins->execute([
            ':uid' => $user_id,
            ':date' => $today,
            ':jenis' => $type,
            ':alasan' => $alasan,
            ':bukti' => $buktiPath,
        ]);

        triggerDatabaseBackup();
        jsonResponse(['ok' => true, 'message' => 'Request izin/sakit berhasil dikirim dan menunggu persetujuan admin.']);
    } catch (PDOException $e) {
        error_log('submit_izin_sakit error: '.$e->getMessage());
        jsonResponse(['ok' => false, 'message' => 'Gagal mengirim request. Silakan coba lagi.'], 500);
    }
}

// Admin: update settings

if ($action === 'admin_update_wfa_locations' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (! isAdmin()) {
        jsonResponse(['error' => 'Forbidden'], 403);
    }

    // Get all WFA records with coordinate-based locations
    $stmt = $pdo->prepare("SELECT id, lat_masuk, lng_masuk, lokasi_masuk, lat_pulang, lng_pulang, lokasi_pulang FROM attendance WHERE ket = 'wfa' AND (lokasi_masuk LIKE 'Lokasi:%' OR lokasi_pulang LIKE 'Lokasi:%')");
    $stmt->execute();
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $updated = 0;
    foreach ($records as $record) {
        $updates = [];
        $params = [':id' => $record['id']];

        // Update masuk location if needed
        if ($record['lat_masuk'] && $record['lng_masuk'] && strpos($record['lokasi_masuk'], 'Lokasi:') === 0) {
            $newLocation = reverseGeocodeAddress($record['lat_masuk'], $record['lng_masuk']);
            if ($newLocation) {
                $updates[] = 'lokasi_masuk = :lokasi_masuk';
                $params[':lokasi_masuk'] = $newLocation;
            }
        }

        // Update pulang location if needed
        if ($record['lat_pulang'] && $record['lng_pulang'] && strpos($record['lokasi_pulang'], 'Lokasi:') === 0) {
            $newLocation = reverseGeocodeAddress($record['lat_pulang'], $record['lng_pulang']);
            if ($newLocation) {
                $updates[] = 'lokasi_pulang = :lokasi_pulang';
                $params[':lokasi_pulang'] = $newLocation;
            }
        }

        if (! empty($updates)) {
            $sql = 'UPDATE attendance SET '.implode(', ', $updates).' WHERE id = :id';
            $upd = $pdo->prepare($sql);
            $upd->execute($params);
            $updated++;
        }
    }

    jsonResponse(['ok' => true, 'message' => "Berhasil memperbarui {$updated} lokasi WFA menjadi nama jalan"]);
}

// Admin: get backup status

if ($action === 'auto_detect_wfo' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (! isAdmin()) {
        jsonResponse(['error' => 'Forbidden'], 403);
    }

    $provider = trim($_POST['provider'] ?? 'ipinfo');
    $token = trim($_POST['token'] ?? '');

    // Get current IP
    $publicIp = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
    if ($publicIp && strpos($publicIp, ',') !== false) {
        $parts = explode(',', $publicIp);
        $publicIp = trim($parts[0]);
    }

    if (! $publicIp || ! filter_var($publicIp, FILTER_VALIDATE_IP)) {
        jsonResponse(['ok' => false, 'message' => 'Tidak dapat menentukan IP publik'], 400);
    }

    $info = fetchPublicIpInfo($publicIp, $provider, $token);
    $org = $info['org'] ?? '';
    $asn = $info['asn'] ?? '';

    jsonResponse([
        'ok' => true,
        'data' => [
            'ip' => $publicIp,
            'org' => $org,
            'asn' => $asn,
            'raw' => $info['raw'] ?? [],
        ],
    ]);
}

// Admin: daily report detail and approval
