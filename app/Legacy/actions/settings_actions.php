<?php

use App\Services\SettingVisibility;

// Extracted from ajax_handler.php — action group: settings
// Variables in scope: $pdo, $action, $archivedExcludeQuery, $archivedExcludeUsersQuery

if ($action === 'get_settings' && in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'])) {
    // Allow public access for landing page face recognition
    $stmt = $pdo->prepare('SELECT setting_key, setting_value, description FROM settings ORDER BY setting_key');
    $stmt->execute();
    $settings = [];
    while ($row = $stmt->fetch()) {
        if (! SettingVisibility::canRead($row['setting_key'], isAdmin())) {
            continue;
        }
        $settings[$row['setting_key']] = [
            'value' => $row['setting_value'],
            'description' => $row['description'],
        ];
    }
    jsonResponse(['ok' => true, 'data' => $settings]);
}

// Employee: submit izin/sakit for today

if ($action === 'save_setting' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (! isAdmin()) {
        jsonResponse(['error' => 'Forbidden'], 403);
    }
    $key = $_POST['key'] ?? '';
    $value = $_POST['value'] ?? '';
    if (! $key) {
        jsonResponse(['ok' => false, 'message' => 'key kosong'], 400);
    }
    $stmt = $pdo->prepare('INSERT INTO settings(setting_key,setting_value) VALUES(:k,:v) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)');
    $stmt->execute([':k' => $key, ':v' => $value]);
    triggerDatabaseBackup();
    jsonResponse(['ok' => true, 'message' => 'Pengaturan disimpan']);
}

// Enhanced FaceNet AJAX Endpoints

if ($action === 'update_settings' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (! isAdmin()) {
        jsonResponse(['error' => 'Forbidden'], 403);
    }
    $maxOntimeHour = trim($_POST['max_ontime_hour'] ?? '');
    $minCheckinHour = trim($_POST['min_checkin_hour'] ?? '');
    $minCheckoutHour = trim($_POST['min_checkout_hour'] ?? '');
    $wfoAddress = trim($_POST['wfo_address'] ?? '');
    $wfoLat = trim($_POST['wfo_lat'] ?? '');
    $wfoLng = trim($_POST['wfo_lng'] ?? '');
    $wfoRadius = trim($_POST['wfo_radius_m'] ?? '');
    $periodEnd = trim($_POST['attendance_period_end'] ?? '');
    $kpiLatePenalty = trim($_POST['kpi_late_penalty'] ?? '');
    $kpiIzinSakit = trim($_POST['kpi_izin_sakit'] ?? '');
    $kpiAlpha = trim($_POST['kpi_alpha'] ?? '');
    $kpiOvertimeBonus = trim($_POST['kpi_overtime_bonus'] ?? '');
    $kpiLateMaxDeduction = trim($_POST['kpi_late_max_deduction'] ?? '');
    $kpiLateTolerance = trim($_POST['kpi_late_tolerance'] ?? '');
    $maxDailyReportDaysBack = trim($_POST['max_daily_report_days_back'] ?? '');
    $maxMonthlyReportMonthsBack = trim($_POST['max_monthly_report_months_back'] ?? '');
    $monthlyReportEndYear = trim($_POST['monthly_report_end_year'] ?? '');
    $faceRecognitionThreshold = trim($_POST['face_recognition_threshold'] ?? '');
    $faceRecognitionMinConfidence = trim($_POST['face_recognition_min_confidence'] ?? '');
    $faceRecognitionInputSize = trim($_POST['face_recognition_input_size'] ?? '');
    $faceRecognitionScoreThreshold = trim($_POST['face_recognition_score_threshold'] ?? '');
    $faceRecognitionQualityThreshold = trim($_POST['face_recognition_quality_threshold'] ?? '');
    $geocodeTimeout = trim($_POST['geocode_timeout'] ?? '');
    $geocodeAccuracyRadius = trim($_POST['geocode_accuracy_radius'] ?? '');

    // WFO API settings
    $wfoMode = trim($_POST['wfo_mode'] ?? '');
    $wfoApiProvider = trim($_POST['wfo_api_provider'] ?? '');
    $wfoApiToken = trim($_POST['wfo_api_token'] ?? '');
    $wfoApiOrgKeywords = trim($_POST['wfo_api_org_keywords'] ?? '');
    $wfoApiAsnList = trim($_POST['wfo_api_asn_list'] ?? '');
    $wfoApiCidrList = trim($_POST['wfo_api_cidr_list'] ?? '');
    $wfoWifiSSIDs = trim($_POST['wfo_wifi_ssids'] ?? '');
    $wfoRequireWifi = trim($_POST['wfo_require_wifi'] ?? '');

    $defaultCheckoutTime = trim($_POST['default_checkout_time'] ?? '');

    if ($maxOntimeHour !== '' && !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $maxOntimeHour) && !is_numeric($maxOntimeHour)) {
        jsonResponse(['ok' => false, 'message' => 'Jam maksimal ontime harus berformat HH:MM'], 400);
    }
    if ($minCheckinHour !== '' && !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $minCheckinHour) && !is_numeric($minCheckinHour)) {
        jsonResponse(['ok' => false, 'message' => 'Jam minimal masuk harus berformat HH:MM'], 400);
    }
    if ($minCheckoutHour !== '' && !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $minCheckoutHour) && !is_numeric($minCheckoutHour)) {
        jsonResponse(['ok' => false, 'message' => 'Jam minimal checkout harus berformat HH:MM'], 400);
    }
    if ($defaultCheckoutTime !== '' && !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $defaultCheckoutTime)) {
        jsonResponse(['ok' => false, 'message' => 'Jam pulang default harus berformat HH:MM'], 400);
    }
    if ($kpiLatePenalty !== '' && (! is_numeric($kpiLatePenalty) || $kpiLatePenalty < 0 || $kpiLatePenalty > 100)) {
        jsonResponse(['ok' => false, 'message' => 'Pengurangan KPI per menit terlambat harus berupa angka 0-100'], 400);
    }
    if ($kpiIzinSakit !== '' && (! is_numeric($kpiIzinSakit) || $kpiIzinSakit < 0 || $kpiIzinSakit > 100)) {
        jsonResponse(['ok' => false, 'message' => 'Nilai KPI izin/sakit harus berupa angka 0-100'], 400);
    }
    if ($kpiAlpha !== '' && (! is_numeric($kpiAlpha) || $kpiAlpha < 0 || $kpiAlpha > 100)) {
        jsonResponse(['ok' => false, 'message' => 'Nilai KPI alpha harus berupa angka 0-100'], 400);
    }
    if ($kpiOvertimeBonus !== '' && (! is_numeric($kpiOvertimeBonus) || $kpiOvertimeBonus < 0 || $kpiOvertimeBonus > 100)) {
        jsonResponse(['ok' => false, 'message' => 'Bonus KPI untuk overtime harus berupa angka 0-100'], 400);
    }
    if ($kpiLateMaxDeduction !== '' && (! is_numeric($kpiLateMaxDeduction) || $kpiLateMaxDeduction < 0 || $kpiLateMaxDeduction > 100)) {
        jsonResponse(['ok' => false, 'message' => 'Maks pengurangan terlambat harus berupa angka 0-100'], 400);
    }
    if ($kpiLateTolerance !== '' && (! is_numeric($kpiLateTolerance) || $kpiLateTolerance < 0 || $kpiLateTolerance > 120)) {
        jsonResponse(['ok' => false, 'message' => 'Toleransi keterlambatan harus berupa angka 0-120 menit'], 400);
    }

    setSetting($pdo, 'max_ontime_hour', $maxOntimeHour);
    setSetting($pdo, 'min_checkin_hour', $minCheckinHour);
    setSetting($pdo, 'min_checkout_hour', $minCheckoutHour);
    if ($defaultCheckoutTime !== '') {
        setSetting($pdo, 'default_checkout_time', $defaultCheckoutTime);
    }
    if ($wfoAddress !== '') {
        setSetting($pdo, 'wfo_address', $wfoAddress);
        // Best-effort geocode; don't fail settings if geocode fails
        $geo = geocodeAddress($wfoAddress);
        if ($geo) {
            setSetting($pdo, 'wfo_lat', (string) $geo['lat']);
            setSetting($pdo, 'wfo_lng', (string) $geo['lng']);
        }
    }
    if ($wfoLat !== '' && is_numeric($wfoLat)) {
        setSetting($pdo, 'wfo_lat', $wfoLat);
    }
    if ($wfoLng !== '' && is_numeric($wfoLng)) {
        setSetting($pdo, 'wfo_lng', $wfoLng);
    }
    if ($wfoRadius !== '' && is_numeric($wfoRadius)) {
        setSetting($pdo, 'wfo_radius_m', $wfoRadius);
    }
    if ($periodEnd !== '') {
        setSetting($pdo, 'attendance_period_end', $periodEnd);
    }
    if ($kpiLatePenalty !== '') {
        setSetting($pdo, 'kpi_late_penalty_per_minute', $kpiLatePenalty);
    }
    if ($kpiIzinSakit !== '') {
        setSetting($pdo, 'kpi_izin_sakit_score', $kpiIzinSakit);
    }
    if ($kpiAlpha !== '') {
        setSetting($pdo, 'kpi_alpha_score', $kpiAlpha);
    }
    if ($kpiOvertimeBonus !== '') {
        setSetting($pdo, 'kpi_overtime_bonus', $kpiOvertimeBonus);
    }
    if ($kpiLateMaxDeduction !== '') {
        setSetting($pdo, 'kpi_late_max_deduction', $kpiLateMaxDeduction);
    }
    if ($kpiLateTolerance !== '') {
        setSetting($pdo, 'kpi_late_tolerance_minutes', $kpiLateTolerance);
    }

    // Bersihkan KPI cache setelah perubahan setting karena akan mempengaruhi hasil
    clearAllKpiCache($pdo);

    // Save WFO API settings
    if ($wfoMode !== '') {
        setSetting($pdo, 'wfo_mode', $wfoMode);
    }
    if ($wfoApiProvider !== '') {
        setSetting($pdo, 'wfo_api_provider', $wfoApiProvider);
    }
    if ($wfoApiToken !== '') {
        setSetting($pdo, 'wfo_api_token', $wfoApiToken);
    }
    if ($wfoApiOrgKeywords !== '') {
        setSetting($pdo, 'wfo_api_org_keywords', $wfoApiOrgKeywords);
    }
    if ($wfoApiAsnList !== '') {
        setSetting($pdo, 'wfo_api_asn_list', $wfoApiAsnList);
    }
    if ($wfoApiCidrList !== '') {
        setSetting($pdo, 'wfo_api_cidr_list', $wfoApiCidrList);
    }
    if ($wfoWifiSSIDs !== '') {
        setSetting($pdo, 'wfo_wifi_ssids', $wfoWifiSSIDs);
    }
    if ($wfoRequireWifi !== '') {
        setSetting($pdo, 'wfo_require_wifi', $wfoRequireWifi);
    }

    // Save report settings
    if ($maxDailyReportDaysBack !== '') {
        setSetting($pdo, 'max_daily_report_days_back', $maxDailyReportDaysBack);
    }
    if ($maxMonthlyReportMonthsBack !== '') {
        setSetting($pdo, 'max_monthly_report_months_back', $maxMonthlyReportMonthsBack);
    }
    if ($monthlyReportEndYear !== '') {
        setSetting($pdo, 'monthly_report_end_year', $monthlyReportEndYear);
    }

    // Save face recognition settings
    if ($faceRecognitionThreshold !== '' && is_numeric($faceRecognitionThreshold) && $faceRecognitionThreshold >= 0 && $faceRecognitionThreshold <= 1) {
        setSetting($pdo, 'face_recognition_threshold', $faceRecognitionThreshold);
    }
    if ($faceRecognitionMinConfidence !== '' && is_numeric($faceRecognitionMinConfidence) && $faceRecognitionMinConfidence >= 50 && $faceRecognitionMinConfidence <= 100) {
        setSetting($pdo, 'face_recognition_min_confidence', $faceRecognitionMinConfidence);
    }
    if ($faceRecognitionInputSize !== '' && is_numeric($faceRecognitionInputSize) && $faceRecognitionInputSize >= 224 && $faceRecognitionInputSize <= 640) {
        setSetting($pdo, 'face_recognition_input_size', $faceRecognitionInputSize);
    }
    if ($faceRecognitionScoreThreshold !== '' && is_numeric($faceRecognitionScoreThreshold) && $faceRecognitionScoreThreshold >= 0 && $faceRecognitionScoreThreshold <= 1) {
        setSetting($pdo, 'face_recognition_score_threshold', $faceRecognitionScoreThreshold);
    }
    if ($faceRecognitionQualityThreshold !== '' && is_numeric($faceRecognitionQualityThreshold) && $faceRecognitionQualityThreshold >= 0 && $faceRecognitionQualityThreshold <= 1) {
        setSetting($pdo, 'face_recognition_quality_threshold', $faceRecognitionQualityThreshold);
    }

    // Save geocode settings
    if ($geocodeTimeout !== '' && is_numeric($geocodeTimeout) && $geocodeTimeout >= 1 && $geocodeTimeout <= 10) {
        setSetting($pdo, 'geocode_timeout', $geocodeTimeout);
    }
    if ($geocodeAccuracyRadius !== '' && is_numeric($geocodeAccuracyRadius) && $geocodeAccuracyRadius >= 10 && $geocodeAccuracyRadius <= 200) {
        setSetting($pdo, 'geocode_accuracy_radius', $geocodeAccuracyRadius);
    }

    // Trigger backup setelah update settings
    triggerDatabaseBackup();

    jsonResponse(['ok' => true, 'message' => 'Settings berhasil disimpan']);
}

// Admin: auto-detect WFO from current IP

if ($action === 'admin_get_work_schedule' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (! isAdmin()) {
        jsonResponse(['error' => 'Forbidden'], 403);
    }

    $userId = (int) ($_POST['user_id'] ?? 0);
    if (! $userId) {
        jsonResponse(['ok' => false, 'message' => 'User ID tidak valid'], 400);
    }

    $schedule = getEmployeeWorkSchedule($pdo, $userId);

    // Default schedule if none exists
    if (empty($schedule)) {
        $defaultDays = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        foreach ($defaultDays as $day) {
            $schedule[$day] = [
                'is_working_day' => in_array($day, ['monday', 'tuesday', 'wednesday', 'thursday', 'friday']),
                'start_time' => '08:00:00',
                'end_time' => '17:00:00',
            ];
        }
    }

    jsonResponse(['ok' => true, 'data' => $schedule]);
}

// Admin: save employee work schedule

if ($action === 'admin_save_work_schedule' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (! isAdmin()) {
        jsonResponse(['error' => 'Forbidden'], 403);
    }

    $userId = (int) ($_POST['user_id'] ?? 0);
    $scheduleData = $_POST['schedule'] ?? [];

    if (! $userId) {
        jsonResponse(['ok' => false, 'message' => 'User ID tidak valid'], 400);
    }

    try {
        // Delete existing schedule
        $pdo->prepare('DELETE FROM employee_work_schedule WHERE user_id = :user_id')
            ->execute([':user_id' => $userId]);

        // Insert new schedule
        $stmt = $pdo->prepare('
                INSERT INTO employee_work_schedule (user_id, day_of_week, is_working_day, start_time, end_time) 
                VALUES (:user_id, :day_of_week, :is_working_day, :start_time, :end_time)
            ');

        foreach ($scheduleData as $day => $data) {
            $stmt->execute([
                ':user_id' => $userId,
                ':day_of_week' => $day,
                ':is_working_day' => $data['is_working_day'] ? 1 : 0,
                ':start_time' => $data['start_time'],
                ':end_time' => $data['end_time'],
            ]);
        }

        // Trigger backup
        triggerDatabaseBackup();

        jsonResponse(['ok' => true, 'message' => 'Jadwal kerja berhasil disimpan']);

    } catch (PDOException $e) {
        error_log('Error saving work schedule: '.$e->getMessage());
        jsonResponse(['ok' => false, 'message' => 'Gagal menyimpan jadwal kerja'], 500);
    }
}

// Dashboard endpoints
