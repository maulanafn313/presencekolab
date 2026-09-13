<?php

// Extracted from ajax_handler.php — action group: report
// Variables in scope: $pdo, $action, $archivedExcludeQuery, $archivedExcludeUsersQuery

if ($action === 'get_kpi_data') {
    try {
        // Check if this is for admin dashboard (filter_type parameter)
        $filterType = $_REQUEST['filter_type'] ?? '';
        $isAdminDashboard = isAdmin() && $filterType !== '';

        // Support force manual cache rebuild
        $forceRefresh = isset($_REQUEST['force_refresh']) && $_REQUEST['force_refresh'] === '1';
        if ($forceRefresh) {
            if ($isAdminDashboard) {
                if ($filterType === 'monthly') {
                    $month = (int) ($_REQUEST['month'] ?? date('n'));
                    $year = (int) ($_REQUEST['year'] ?? date('Y'));
                    $stmt = $pdo->prepare('DELETE FROM kpi_monthly_cache WHERE year = :year AND month = :month');
                    $stmt->execute([':year' => $year, ':month' => $month]);
                } else {
                    clearAllKpiCache($pdo);
                }
            } else {
                $userId = isAdmin() ? (int) ($_REQUEST['user_id'] ?? 0) : (int) $_SESSION['user']['id'];
                if (! $userId) {
                    $userId = (int) $_SESSION['user']['id'];
                }
                $periodStart = $_REQUEST['period_start'] ?? date('Y-m-01');
                $periodEnd = $_REQUEST['period_end'] ?? date('Y-m-t');

                $startYear = (int) date('Y', strtotime($periodStart));
                $startMonth = (int) date('m', strtotime($periodStart));
                $endYear = (int) date('Y', strtotime($periodEnd));
                $endMonth = (int) date('m', strtotime($periodEnd));

                $stmt = $pdo->prepare('DELETE FROM kpi_monthly_cache WHERE user_id = :user_id AND ((year > :sy OR (year = :sy AND month >= :sm)) AND (year < :ey OR (year = :ey AND month <= :em)))');
                $stmt->execute([
                    ':user_id' => $userId,
                    ':sy' => $startYear,
                    ':sm' => $startMonth,
                    ':ey' => $endYear,
                    ':em' => $endMonth,
                ]);
            }
        }

        if ($isAdminDashboard) {
            // Admin dashboard - get all KPI data with optional monthly filter
            $customPeriodStart = null;
            $customPeriodEnd = null;

            if ($filterType === 'monthly') {
                $month = (int) ($_REQUEST['month'] ?? date('n'));
                $year = (int) ($_REQUEST['year'] ?? date('Y'));
                $customPeriodStart = date('Y-m-01', mktime(0, 0, 0, $month, 1, $year));
                $customPeriodEnd = date('Y-m-t', mktime(0, 0, 0, $month, 1, $year));
                error_log("get_kpi_data - Monthly filter: $month/$year ($customPeriodStart to $customPeriodEnd)");
            } else {
                $customPeriodStart = $_REQUEST['period_start'] ?? null;
                $customPeriodEnd = $_REQUEST['period_end'] ?? null;
                error_log("get_kpi_data - Period filter: $customPeriodStart to $customPeriodEnd");
            }

            $includePhotos = isset($_REQUEST['include_photos']) && $_REQUEST['include_photos'] === '1';
            $kpiData = getAllKPIData($pdo, $customPeriodStart, $customPeriodEnd, $includePhotos);
            error_log('get_kpi_data - Admin dashboard, returning all KPI data');
            jsonResponse(['ok' => true, 'data' => $kpiData]);
        } else {
            // Individual employee KPI - get specific user
            $userId = isAdmin() ? (int) ($_REQUEST['user_id'] ?? 0) : (int) $_SESSION['user']['id'];

            error_log("get_kpi_data - User ID: $userId, Is Admin: ".(isAdmin() ? 'Yes' : 'No'));
            error_log('get_kpi_data - Session user: '.print_r($_SESSION['user'] ?? 'No session', true));
            error_log('get_kpi_data - REQUEST user_id: '.($_REQUEST['user_id'] ?? 'Not set'));

            if (! $userId && ! isAdmin()) {
                error_log('get_kpi_data - No user ID found');
                jsonResponse(['ok' => false, 'message' => 'User tidak ditemukan'], 400);
            }

            // If admin but no user_id specified, use logged-in user
            if (! $userId) {
                $userId = (int) $_SESSION['user']['id'];
                error_log("get_kpi_data - Using logged-in user ID: $userId");
            }

            // Get period start and end
            $periodStart = $_REQUEST['period_start'] ?? date('Y-m-01');
            $periodEnd = $_REQUEST['period_end'] ?? date('Y-m-t');

            error_log("get_kpi_data - Period: $periodStart to $periodEnd");
            error_log("get_kpi_data - Individual employee KPI for user: $userId");

            // Calculate KPI for individual employee
            $kpiData = calculateKPIForEmployee($pdo, $userId, $periodStart, $periodEnd);

            error_log('get_kpi_data - Individual KPI calculation result: '.print_r($kpiData, true));

            if ($kpiData) {
                jsonResponse(['ok' => true, 'data' => $kpiData]);
            } else {
                error_log('get_kpi_data - Individual KPI calculation returned null/empty');
                jsonResponse(['ok' => false, 'message' => 'Gagal menghitung KPI'], 500);
            }
        }
    } catch (Exception $e) {
        error_log('get_kpi_data - Exception: '.$e->getMessage());
        jsonResponse(['ok' => false, 'message' => 'Error: '.$e->getMessage()], 500);
    }
}

if ($action === 'get_rekap' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (! isset($_SESSION['user'])) {
        jsonResponse(['error' => 'Unauthorized'], 401);
    }
    $uid = (int) $_SESSION['user']['id'];
    $year = (int) ($_POST['year'] ?? date('Y'));
    $month = (int) ($_POST['month'] ?? date('n'));
    $start = sprintf('%04d-%02d-01', $year, $month);
    $end = date('Y-m-t', strtotime($start));

    // Get employee registration date
    $employeeRegDate = getEmployeeRegistrationDate($pdo, $uid);
    $employeeRegDateOnly = $employeeRegDate ? date('Y-m-d', strtotime($employeeRegDate)) : null;

    // Fetch attendance and reports for month (including overtime on weekends/holidays)
    $attStmt = $pdo->prepare('SELECT * FROM attendance WHERE user_id=:uid AND DATE(jam_masuk_iso) BETWEEN :s AND :e');
    $attStmt->execute([':uid' => $uid, ':s' => $start, ':e' => $end]);
    $attRows = $attStmt->fetchAll();
    $attByDate = [];
    foreach ($attRows as $r) {
        $d = date('Y-m-d', strtotime($r['jam_masuk_iso']));
        $attByDate[$d] = $r;
    }

    // Fetch attendance notes for month (check if table exists first)
    $notesByDate = [];
    try {
        $notesStmt = $pdo->prepare('SELECT * FROM attendance_notes WHERE user_id=:uid AND date BETWEEN :s AND :e');
        $notesStmt->execute([':uid' => $uid, ':s' => $start, ':e' => $end]);
        foreach ($notesStmt->fetchAll() as $r) {
            $notesByDate[$r['date']] = $r;
        }
    } catch (PDOException $e) {
        // Table doesn't exist yet, continue with empty array
        error_log('Attendance notes table not found: '.$e->getMessage());
    }

    // Get manual holidays for the month
    $manualHolidays = getManualHolidaysInRange($pdo, $start, $end);
    $manualHolidayDates = [];
    foreach ($manualHolidays as $h) {
        $manualHolidayDates[$h['date']] = true;
    }

    $drStmt = $pdo->prepare('SELECT * FROM daily_reports WHERE user_id=:uid AND report_date BETWEEN :s AND :e');
    $drStmt->execute([':uid' => $uid, ':s' => $start, ':e' => $end]);
    $drByDate = [];
    foreach ($drStmt->fetchAll() as $r) {
        $drByDate[$r['report_date']] = $r;
    }

    // Pre-fetch work schedule once to avoid database queries inside the day-by-day loop
    $preFetchedSchedule = getEmployeeWorkSchedule($pdo, $uid);

    // Build all days in month (including weekends)
    $out = [];
    $cur = new DateTime($start);
    $endDt = new DateTime($end);
    while ($cur <= $endDt) {
        $dstr = $cur->format('Y-m-d');
        $dow = (int) $cur->format('N'); // 1 Mon .. 7 Sun
        $att = $attByDate[$dstr] ?? null;
        $notes = $notesByDate[$dstr] ?? null;
        $dr = $drByDate[$dstr] ?? null;

        // Check if date is before employee registration
        $isBeforeRegistration = $employeeRegDateOnly && $dstr < $employeeRegDateOnly;

        // Check if date is manual holiday
        $isManualHolidayDate = isset($manualHolidayDates[$dstr]);

        // Check if date is national holiday
        $isNationalHolidayDate = $isManualHolidayDate;

        // Check if date is weekend
        $isWeekend = $dow >= 6; // Saturday = 6, Sunday = 7

        // Check if date is working day for this employee (using pre-fetched data)
        $isWorkingDay = isEmployeeWorkingDay($pdo, $uid, $dstr, $preFetchedSchedule, $manualHolidayDates);

        // Determine ket value
        $ket = null;
        if ($att && $att['ket']) {
            $ket = $att['ket'];
        } elseif ($notes && $notes['type']) {
            $ket = $notes['type'];
        } elseif ($isManualHolidayDate) {
            $ket = 'libur';
        } elseif ($isBeforeRegistration) {
            $ket = 'na'; // Not Available
        }

        // For daily report content, use attendance_notes if available
        $reportContent = null;
        if ($dr) {
            $reportContent = [
                'id' => $dr['id'],
                'status' => $dr['status'],
                'has_content' => (bool) $dr['content'],
                'content' => $dr['content'],
                'evaluation' => $dr['evaluation'],
            ];
        } elseif ($notes && $notes['keterangan']) {
            // Use attendance_notes content for daily report
            $reportContent = [
                'id' => null,
                'status' => 'auto',
                'has_content' => true,
                'content' => $notes['keterangan'],
                'evaluation' => null,
            ];
        }

        $out[] = [
            'date' => $dstr,
            'day' => $cur->format('l'),
            'attendance_id' => $att['id'] ?? null,
            'note_id' => $notes['id'] ?? null,
            'jam_masuk' => $att['jam_masuk'] ?? null,
            'jam_pulang' => $att['jam_pulang'] ?? null,
            'status_presensi' => $att['status'] ?? null,
            'ket' => $ket,
            'daily_report' => $reportContent,
            'is_working_day' => $isWorkingDay,
            'is_weekend' => $isWeekend,
            'is_manual_holiday' => $isManualHolidayDate,
            'is_national_holiday' => $isNationalHolidayDate,
            'is_before_registration' => $isBeforeRegistration,
        ];
        $cur->modify('+1 day');
    }
    jsonResponse(['ok' => true, 'data' => $out]);
}

// Get missing daily reports for current user - all dates during period

if ($action === 'get_dashboard_data') {
    if (! isAdmin()) {
        jsonResponse(['error' => 'Forbidden'], 403);
    }

    $today = date('Y-m-d');
    // For monthly performance, always use current month only (not entire period)
    $monthStart = date('Y-m-01');
    $monthEnd = date('Y-m-t');

    // Get today's late employees
    $todayLateStmt = $pdo->prepare("
            SELECT u.id, u.nama, (CASE WHEN u.foto_base64 IS NOT NULL AND u.foto_base64 != '' THEN 1 ELSE 0 END) as has_foto, a.jam_masuk, a.status
            FROM attendance a 
            JOIN users u ON u.id = a.user_id 
            WHERE DATE(a.jam_masuk_iso) = :today 
            AND a.status = 'terlambat'
            AND a.$archivedExcludeQuery
            ORDER BY a.jam_masuk_iso DESC
        ");
    $todayLateStmt->execute([':today' => $today]);
    $todayLate = $todayLateStmt->fetchAll();

    // Get monthly attendance statistics for current month only
    // Only count actual attendance records (ontime/terlambat) within current month
    // Count distinct dates to match KPI calculation logic (one count per day)
    // Also calculate average time for sorting when counts are equal
    $monthlyStatsStmt = $pdo->prepare("
            SELECT 
                u.id,
                u.nama,
                (CASE WHEN u.foto_base64 IS NOT NULL AND u.foto_base64 != '' THEN 1 ELSE 0 END) as has_foto,
                COUNT(DISTINCT CASE WHEN a.status = 'terlambat' THEN DATE(a.jam_masuk_iso) END) as late_count,
                COUNT(DISTINCT CASE WHEN a.status = 'ontime' THEN DATE(a.jam_masuk_iso) END) as ontime_count,
                COUNT(DISTINCT CASE WHEN a.id IS NOT NULL AND (a.ket = 'wfo' OR a.ket = 'wfa') THEN DATE(a.jam_masuk_iso) END) as present_count,
                COUNT(DISTINCT DATE(a.jam_masuk_iso)) as total_days,
                SEC_TO_TIME(AVG(CASE WHEN a.status = 'ontime' THEN TIME_TO_SEC(TIME(a.jam_masuk_iso)) END)) as avg_ontime_time,
                SEC_TO_TIME(AVG(CASE WHEN a.status = 'terlambat' THEN TIME_TO_SEC(TIME(a.jam_masuk_iso)) END)) as avg_late_time
            FROM users u
            LEFT JOIN attendance a ON u.id = a.user_id 
                AND DATE(a.jam_masuk_iso) BETWEEN :month_start AND :month_end
                AND (a.status = 'ontime' OR a.status = 'terlambat')
            WHERE u.role = 'pegawai' AND u.$archivedExcludeUsersQuery
            AND u.$archivedExcludeUsersQuery
            GROUP BY u.id, u.nama, has_foto
            HAVING total_days > 0
            ORDER BY late_count DESC, ontime_count DESC
        ");
    $monthlyStatsStmt->execute([':month_start' => $monthStart, ':month_end' => $monthEnd]);
    $monthlyStats = $monthlyStatsStmt->fetchAll();

    // Get summary statistics
    $totalEmployeesStmt = $pdo->prepare("SELECT COUNT(*) as total FROM users WHERE role = 'pegawai' AND $archivedExcludeUsersQuery");
    $totalEmployeesStmt->execute();
    $totalEmployees = $totalEmployeesStmt->fetch()['total'];

    $presentTodayStmt = $pdo->prepare("
            SELECT COUNT(DISTINCT user_id) as present 
            FROM attendance 
            WHERE DATE(jam_masuk_iso) = :today 
            AND (ket = 'wfo' OR ket = 'wfa')
            AND $archivedExcludeQuery
        ");
    $presentTodayStmt->execute([':today' => $today]);
    $presentToday = $presentTodayStmt->fetch()['present'];

    $lateTodayStmt = $pdo->prepare("
            SELECT COUNT(DISTINCT user_id) as late 
            FROM attendance 
            WHERE DATE(jam_masuk_iso) = :today 
            AND status = 'terlambat'
            AND $archivedExcludeQuery
        ");
    $lateTodayStmt->execute([':today' => $today]);
    $lateToday = $lateTodayStmt->fetch()['late'];

    $absentToday = $totalEmployees - $presentToday;

    // Get attendance trend based on configured period
    $trendData = [];

    // Use earliest employee registration date for trend data
    $trendStart = getEarliestEmployeeRegistrationDate($pdo);
    $trendEnd = getSetting($pdo, 'attendance_period_end', '');

    if ($trendStart && $trendEnd) {
        $startDate = $trendStart;
        $endDate = $trendEnd;
    } else {
        // Fallback to current year if no period configured
        $startDate = date('Y-01-01');
        $endDate = date('Y-12-31');
    }

    $currentDate = new DateTime($startDate);
    $endDateTime = new DateTime($endDate);

    while ($currentDate <= $endDateTime) {
        $year = $currentDate->format('Y');
        $month = $currentDate->format('m');
        $monthName = $currentDate->format('M Y');

        // Skip future months (months that haven't started yet)
        $currentMonth = date('Y-m');
        $currentMonthDate = $currentDate->format('Y-m');
        if ($currentMonthDate > $currentMonth) {
            $currentDate->add(new DateInterval('P1M'));

            continue;
        }

        // Count ontime occurrences (not distinct users)
        $ontimeStmt = $pdo->prepare("
                SELECT COUNT(*) as ontime 
                FROM attendance 
                WHERE YEAR(jam_masuk_iso) = :year 
                AND MONTH(jam_masuk_iso) = :month 
                AND status = 'ontime'
            ");
        $ontimeStmt->execute([':year' => $year, ':month' => $month]);
        $ontime = $ontimeStmt->fetch()['ontime'];

        // Count late occurrences (not distinct users)
        $lateStmt = $pdo->prepare("
                SELECT COUNT(*) as late 
                FROM attendance 
                WHERE YEAR(jam_masuk_iso) = :year 
                AND MONTH(jam_masuk_iso) = :month 
                AND status = 'terlambat'
            ");
        $lateStmt->execute([':year' => $year, ':month' => $month]);
        $late = $lateStmt->fetch()['late'];

        // Count izin and sakit occurrences from both tables
        // First from attendance table
        $izinSakitStmt = $pdo->prepare("
                SELECT COUNT(*) as izin_sakit 
                FROM attendance 
                WHERE YEAR(jam_masuk_iso) = :year 
                AND MONTH(jam_masuk_iso) = :month 
                AND ket IN ('izin', 'sakit')
            ");
        $izinSakitStmt->execute([':year' => $year, ':month' => $month]);
        $izinSakitFromAttendance = $izinSakitStmt->fetch()['izin_sakit'];

        // Then from attendance_notes table
        $izinSakitNotesStmt = $pdo->prepare("
                SELECT COUNT(*) as izin_sakit 
                FROM attendance_notes 
                WHERE YEAR(date) = :year 
                AND MONTH(date) = :month 
                AND type IN ('izin', 'sakit')
            ");
        $izinSakitNotesStmt->execute([':year' => $year, ':month' => $month]);
        $izinSakitFromNotes = $izinSakitNotesStmt->fetch()['izin_sakit'];

        // Total izin/sakit (from both tables)
        $izinSakit = $izinSakitFromAttendance + $izinSakitFromNotes;

        // Calculate alpha occurrences
        // For current month, only count working days up to today
        // For past months, count all working days in the month
        if ($currentMonthDate == $currentMonth) {
            // Current month: only count working days up to today
            $today = new DateTime;
            $totalWorkingDaysInMonth = getWorkingDaysInMonthUpToDate($year, $month, $today->format('d'));

            // Debug for October 2025
            if ($month == 10 && $year == 2025) {
                error_log('Trend Debug - October 2025 working days calculation:');
                error_log('- Today: '.$today->format('Y-m-d'));
                error_log('- Today day: '.$today->format('d'));
                error_log("- Working days up to yesterday: $totalWorkingDaysInMonth");

                // Manual calculation for verification
                $manualCount = 0;
                $start = new DateTime('2025-10-01');
                $end = new DateTime('2025-10-15'); // Yesterday (16-1=15)
                while ($start <= $end) {
                    if ($start->format('N') < 6) { // Skip weekends
                        $manualCount++;
                    }
                    $start->add(new DateInterval('P1D'));
                }
                error_log("- Manual count (Oct 1-15): $manualCount");
            }
        } else {
            // Past months: count all working days in the month
            $totalWorkingDaysInMonth = getWorkingDaysInMonth($year, $month);
        }

        // Get total employees who were registered during this month
        $monthEnd = sprintf('%04d-%02d-%02d', $year, $month, date('t', strtotime(sprintf('%04d-%02d-01', $year, $month))));

        // For current month, use current date as end date
        $todayDate = date('Y-m-d');
        if ($monthEnd > $todayDate) {
            $monthEnd = $todayDate;
        }

        $employeesStmt = $pdo->prepare("
                SELECT COUNT(*) as total_employees_in_month
                FROM users 
                WHERE role = 'pegawai' 
                AND created_at <= :month_end
                AND DATE(created_at) < :month_start
                AND $archivedExcludeUsersQuery
            ");
        $monthStart = sprintf('%04d-%02d-01', $year, $month);
        $employeesStmt->execute([':month_end' => $monthEnd, ':month_start' => $monthStart]);
        $totalEmployeesInMonth = $employeesStmt->fetch()['total_employees_in_month'];

        // Debug: Check individual employee registration dates for October
        if ($month == 10 && $year == 2025) {
            $debugStmt = $pdo->prepare("
                    SELECT id, nama, created_at 
                    FROM users 
                    WHERE role = 'pegawai' 
                    AND created_at <= :month_end
                    ORDER BY created_at
                ");
            $debugStmt->execute([':month_end' => $monthEnd]);
            $allEmployees = $debugStmt->fetchAll();
            error_log('Trend Debug - All employees in October: '.count($allEmployees));
            foreach ($allEmployees as $emp) {
                error_log('- Employee: '.$emp['nama'].' (ID: '.$emp['id'].') registered: '.$emp['created_at']);
            }
        }

        // Calculate total possible attendance for this month
        $totalPossibleAttendance = $totalWorkingDaysInMonth * $totalEmployeesInMonth;

        // Calculate alpha: total possible - (ontime + late + izin/sakit)
        $alpha = $totalPossibleAttendance - ($ontime + $late + $izinSakit);

        // Debug logging for October
        if ($month == 10 && $year == 2025) {
            error_log('Trend Debug October 2025:');
            error_log("- Total working days: $totalWorkingDaysInMonth");
            error_log("- Total employees: $totalEmployeesInMonth");
            error_log("- Total possible attendance: $totalPossibleAttendance");
            error_log("- OnTime: $ontime");
            error_log("- Late: $late");
            error_log("- Izin/Sakit from attendance: $izinSakitFromAttendance");
            error_log("- Izin/Sakit from notes: $izinSakitFromNotes");
            error_log("- Total Izin/Sakit: $izinSakit");
            error_log("- Alpha: $alpha");
            error_log('- Total absent: '.($izinSakit + max(0, $alpha)));
            error_log('- Expected calculation: 16 employees × 11 days = 176, 176 - 44 - 21 = 111, +1 = 112');
        }

        // Total absent = izin + sakit + alpha
        $absent = $izinSakit + max(0, $alpha);

        $trendData[] = [
            'date' => $currentDate->format('Y-m'),
            'day' => $monthName,
            'present' => $ontime,
            'late' => $late,
            'absent' => $absent,
        ];

        $currentDate->add(new DateInterval('P1M'));
    }

    // Get daily report statistics - count missing reports with employee details
    // Count employees who have attendance but no daily report for dates up to today
    $currentDateForReports = date('Y-m-d');

    // Get summary statistics
    $dailyReportSummaryStmt = $pdo->prepare("
            SELECT 
                COUNT(DISTINCT a.user_id) as employees_without_reports,
                COUNT(*) as total_missing_reports
            FROM attendance a
            LEFT JOIN daily_reports dr ON dr.user_id = a.user_id 
                AND dr.report_date = DATE(a.jam_masuk_iso)
            WHERE DATE(a.jam_masuk_iso) <= :current_date
                AND (a.ket = 'wfo' OR a.ket = 'wfa')
                AND dr.id IS NULL
                AND a.$archivedExcludeQuery
        ");
    $dailyReportSummaryStmt->execute([':current_date' => $currentDateForReports]);
    $dailyReportStats = $dailyReportSummaryStmt->fetch();

    // Get detailed list of employees with missing reports, sorted by count
    $dailyReportDetailsStmt = $pdo->prepare("
            SELECT 
                u.id,
                u.nama,
                (CASE WHEN u.foto_base64 IS NOT NULL AND u.foto_base64 != '' THEN 1 ELSE 0 END) as has_foto,
                COUNT(*) as missing_count
            FROM attendance a
            JOIN users u ON u.id = a.user_id
            LEFT JOIN daily_reports dr ON dr.user_id = a.user_id 
                AND dr.report_date = DATE(a.jam_masuk_iso)
            WHERE DATE(a.jam_masuk_iso) <= :current_date
                AND (a.ket = 'wfo' OR a.ket = 'wfa')
                AND dr.id IS NULL
                AND u.role = 'pegawai'
                AND u.$archivedExcludeUsersQuery
            GROUP BY u.id, u.nama, has_foto
            ORDER BY missing_count DESC
            LIMIT 10
        ");
    $dailyReportDetailsStmt->execute([':current_date' => $currentDateForReports]);
    $dailyReportDetails = $dailyReportDetailsStmt->fetchAll();

    jsonResponse([
        'ok' => true,
        'data' => [
            'today_late' => $todayLate,
            'monthly_stats' => $monthlyStats,
            'attendance_trend' => $trendData,
            'daily_report_stats' => [
                'employees_without_reports' => (int) $dailyReportStats['employees_without_reports'],
                'total_missing_reports' => (int) $dailyReportStats['total_missing_reports'],
                'employee_details' => $dailyReportDetails,
            ],
            'summary' => [
                'total_employees' => $totalEmployees,
                'present_today' => $presentToday,
                'late_today' => $lateToday,
                'absent_today' => $absentToday,
            ],
        ],
    ]);
}

// Public endpoint for daily report statistics (no login required)

if ($action === 'get_daily_report_detail' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (! isAdmin()) {
        jsonResponse(['error' => 'Forbidden'], 403);
    }
    $uid = (int) ($_POST['user_id'] ?? 0);
    $date = $_POST['date'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);
    if ($id) {
        $stmt = $pdo->prepare('SELECT dr.*, u.nama FROM daily_reports dr JOIN users u ON u.id=dr.user_id WHERE dr.id=:id');
        $stmt->execute([':id' => $id]);
        jsonResponse(['ok' => true, 'data' => $stmt->fetch()]);
    }
    if (! $uid || ! $date) {
        jsonResponse(['ok' => false, 'message' => 'Param tidak lengkap'], 400);
    }
    $stmt = $pdo->prepare('SELECT dr.*, u.nama FROM daily_reports dr JOIN users u ON u.id=dr.user_id WHERE dr.user_id=:u AND dr.report_date=:d');
    $stmt->execute([':u' => $uid, ':d' => $date]);
    jsonResponse(['ok' => true, 'data' => $stmt->fetch()]);
}

if ($action === 'admin_set_daily_status' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (! isAdmin()) {
        jsonResponse(['error' => 'Forbidden'], 403);
    }
    $id = (int) ($_POST['id'] ?? 0);
    $status = $_POST['status'] ?? '';
    $evaluation = $_POST['evaluation'] ?? null;
    if (! $id || ! in_array($status, ['approved', 'disapproved'], true)) {
        jsonResponse(['ok' => false, 'message' => 'Param tidak valid'], 400);
    }
    $upd = $pdo->prepare('UPDATE daily_reports SET status=:s, evaluation=:e, updated_at=NOW() WHERE id=:id');
    $upd->execute([':s' => $status, ':e' => $evaluation, ':id' => $id]);

    // Trigger backup setelah update daily report status
    triggerDatabaseBackup();

    jsonResponse(['ok' => true]);
}

// Admin: save daily report for employee

if ($action === 'admin_save_daily_report' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (! isAdmin()) {
        jsonResponse(['error' => 'Forbidden'], 403);
    }
    $user_id = (int) ($_POST['user_id'] ?? 0);
    $date = $_POST['date'] ?? '';
    $content = $_POST['content'] ?? '';
    if (! $user_id || ! $date || ! $content) {
        jsonResponse(['ok' => false, 'message' => 'Param tidak lengkap'], 400);
    }

    // Check if report already exists
    $stmt = $pdo->prepare('SELECT id, status FROM daily_reports WHERE user_id=:u AND report_date=:d');
    $stmt->execute([':u' => $user_id, ':d' => $date]);
    $row = $stmt->fetch();

    if ($row) {
        // Update existing report
        $upd = $pdo->prepare('UPDATE daily_reports SET content=:c, updated_at=NOW() WHERE id=:id');
        $upd->execute([':c' => $content, ':id' => $row['id']]);

        // Trigger backup setelah update daily report
        triggerDatabaseBackup();

        jsonResponse(['ok' => true, 'id' => $row['id']]);
    } else {
        // Create new report
        $ins = $pdo->prepare('INSERT INTO daily_reports (user_id, report_date, content) VALUES (:u, :d, :c)');
        $ins->execute([':u' => $user_id, ':d' => $date, ':c' => $content]);

        // Trigger backup setelah insert daily report
        triggerDatabaseBackup();

        jsonResponse(['ok' => true, 'id' => $pdo->lastInsertId()]);
    }
}

// Admin: monthly reports list and approval

if ($action === 'admin_get_monthly_reports') {
    if (! isAdmin()) {
        jsonResponse(['error' => 'Forbidden'], 403);
    }
    $term = strtolower(trim($_REQUEST['term'] ?? ''));
    $startup = trim($_REQUEST['startup'] ?? '');
    $year = (int) ($_REQUEST['year'] ?? 0);
    $month = (int) ($_REQUEST['month'] ?? 0);
    $sql = "SELECT mr.*, u.nim, u.nama, u.startup FROM monthly_reports mr JOIN users u ON u.id=mr.user_id WHERE 1=1 AND mr.$archivedExcludeQuery";
    $params = [];

    if ($term) {
        $sql .= ' AND (LOWER(u.nama) LIKE :t OR LOWER(u.nim) LIKE :t)';
        $params[':t'] = '%'.$term.'%';
    }
    if ($startup) {
        $sql .= ' AND u.startup=:s';
        $params[':s'] = $startup;
    }
    if ($year) {
        $sql .= ' AND mr.year=:y';
        $params[':y'] = $year;
    }
    if ($month) {
        $sql .= ' AND mr.month=:m';
        $params[':m'] = $month;
    }
    $sql .= ' ORDER BY mr.year DESC, mr.month DESC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    jsonResponse(['ok' => true, 'data' => $stmt->fetchAll()]);
}

// Admin: get monthly report detail by ID

if ($action === 'get_monthly_report_detail' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (! isAdmin()) {
        jsonResponse(['error' => 'Forbidden'], 403);
    }
    $id = (int) ($_POST['id'] ?? 0);
    if (! $id) {
        jsonResponse(['ok' => false, 'message' => 'ID tidak valid'], 400);
    }
    $stmt = $pdo->prepare('SELECT mr.*, u.nim, u.nama FROM monthly_reports mr JOIN users u ON u.id=mr.user_id WHERE mr.id=:id');
    $stmt->execute([':id' => $id]);
    $data = $stmt->fetch();
    if (! $data) {
        jsonResponse(['ok' => false, 'message' => 'Laporan tidak ditemukan'], 404);
    }
    jsonResponse(['ok' => true, 'data' => $data]);
}

if ($action === 'admin_set_monthly_status' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (! isAdmin()) {
        jsonResponse(['error' => 'Forbidden'], 403);
    }
    $id = (int) ($_POST['id'] ?? 0);
    $status = $_POST['status'] ?? '';
    if (! $id || ! in_array($status, ['approved', 'disapproved'], true)) {
        jsonResponse(['ok' => false, 'message' => 'Param tidak valid'], 400);
    }
    $pdo->prepare('UPDATE monthly_reports SET status=:s, updated_at=NOW() WHERE id=:id')->execute([':s' => $status, ':id' => $id]);

    // Trigger backup setelah update monthly report status
    triggerDatabaseBackup();

    jsonResponse(['ok' => true]);
}

// Admin: get employee work schedule

if ($action === 'get_public_daily_report_stats' && in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'])) {
    $currentDateForReports = date('Y-m-d');

    // Get all employees with their missing report counts (including those with 0 missing)
    $dailyReportDetailsStmt = $pdo->prepare("
            SELECT 
                u.id,
                u.nama,
                IF(u.foto_base64 IS NOT NULL AND u.foto_base64 != '', 1, 0) as has_foto,
                COALESCE((
                    SELECT COUNT(DISTINCT DATE(a2.jam_masuk_iso))
                    FROM attendance a2
                    LEFT JOIN daily_reports dr2 ON dr2.user_id = a2.user_id 
                        AND dr2.report_date = DATE(a2.jam_masuk_iso)
                    WHERE a2.user_id = u.id
                        AND DATE(a2.jam_masuk_iso) >= DATE(u.created_at)
                        AND DATE(a2.jam_masuk_iso) <= :current_date
                        AND (a2.ket = 'wfo' OR a2.ket = 'wfa')
                        AND dr2.id IS NULL
                ), 0) as missing_count
            FROM users u
            WHERE u.role = 'pegawai' AND u.$archivedExcludeUsersQuery
            ORDER BY missing_count DESC, u.nama ASC
        ");
    $dailyReportDetailsStmt->execute([':current_date' => $currentDateForReports]);
    $dailyReportDetails = $dailyReportDetailsStmt->fetchAll();

    jsonResponse([
        'ok' => true,
        'data' => [
            'employee_details' => $dailyReportDetails,
        ],
    ]);
}

// --- Export Actions ---

// Export KPI to Professional Excel
// Legacy Export actions removed. Migrated to App\Http\Controllers\Web\ExportController

// --- Pegawai Daily Reports API ---

if ($action === 'get_missing_daily_reports' && in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'])) {
    if (! isset($_SESSION['user'])) {
        jsonResponse(['error' => 'Unauthorized'], 401);
    }
    $uid = (int) $_SESSION['user']['id'];

    // Get employee registration date to determine period start
    $employeeRegDate = getEmployeeRegistrationDate($pdo, $uid);
    $employeeRegDateOnly = $employeeRegDate ? date('Y-m-d', strtotime($employeeRegDate)) : null;

    // Use registration date as start, or fallback to start of current year instead of month
    // Broadened to at least 90 days ago if registration is recent/missing to ensure we catch all missing reports
    $startDate = $employeeRegDateOnly ? $employeeRegDateOnly : date('Y-m-d', strtotime('-90 days'));

    // If registration date is today, look back at least 30 days anyway
    // because sometimes the registration date is set to the current date on host
    if ($employeeRegDateOnly === date('Y-m-d')) {
        $startDate = date('Y-m-d', strtotime('-30 days'));
    }

    $endDate = date('Y-m-d');

    // Get attendance records that don't have daily reports for all period
    // Added 'overtime' to the criteria as users should also fill reports for overtime work
    $stmt = $pdo->prepare("
            SELECT DISTINCT DATE(a.jam_masuk_iso) as date
            FROM attendance a
            LEFT JOIN daily_reports dr ON dr.user_id = a.user_id 
                AND dr.report_date = DATE(a.jam_masuk_iso)
            WHERE a.user_id = :uid
                AND DATE(a.jam_masuk_iso) BETWEEN :start_date AND :end_date
                AND DATE(a.jam_masuk_iso) <= :current_date
                AND (a.ket = 'wfo' OR a.ket = 'wfa' OR a.ket = 'overtime')
                AND dr.id IS NULL
            ORDER BY DATE(a.jam_masuk_iso) DESC
        ");
    $stmt->execute([
        ':uid' => $uid,
        ':start_date' => $startDate,
        ':end_date' => $endDate,
        ':current_date' => $endDate,
    ]);
    $missingDates = $stmt->fetchAll(PDO::FETCH_COLUMN);

    jsonResponse(['ok' => true, 'data' => $missingDates]);
}

if ($action === 'save_daily_report' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (! isset($_SESSION['user'])) {
        jsonResponse(['error' => 'Unauthorized'], 401);
    }
    $uid = (int) $_SESSION['user']['id'];
    $date = $_POST['date'] ?? '';
    $content = $_POST['content'] ?? '';
    if (! $date) {
        jsonResponse(['ok' => false, 'message' => 'Tanggal diperlukan'], 400);
    }
    // Upsert
    $stmt = $pdo->prepare('SELECT id, status FROM daily_reports WHERE user_id=:u AND report_date=:d');
    $stmt->execute([':u' => $uid, ':d' => $date]);
    $row = $stmt->fetch();
    if ($row && $row['status'] === 'approved') {
        jsonResponse(['ok' => false, 'message' => 'Sudah di-approve, tidak bisa diedit'], 400);
    }
    if ($row) {
        $upd = $pdo->prepare('UPDATE daily_reports SET content=:c, updated_at=NOW() WHERE id=:id');
        $upd->execute([':c' => $content, ':id' => $row['id']]);

        jsonResponse(['ok' => true, 'id' => $row['id']]);
    } else {
        $ins = $pdo->prepare('INSERT INTO daily_reports (user_id, report_date, content) VALUES (:u,:d,:c)');
        $ins->execute([':u' => $uid, ':d' => $date, ':c' => $content]);

        jsonResponse(['ok' => true, 'id' => $pdo->lastInsertId()]);
    }
}

// --- Pegawai Monthly Reports API ---

if ($action === 'get_monthly_reports') {
    if (! isset($_SESSION['user'])) {
        jsonResponse(['error' => 'Unauthorized'], 401);
    }
    $uid = (int) $_SESSION['user']['id'];
    $stmt = $pdo->prepare('SELECT * FROM monthly_reports WHERE user_id=:u ORDER BY year DESC, month DESC');
    $stmt->execute([':u' => $uid]);
    jsonResponse(['ok' => true, 'data' => $stmt->fetchAll()]);
}

// Fix existing data with year=0 and month=0 (one-time fix)

if ($action === 'fix_monthly_reports' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (! isAdmin()) {
        jsonResponse(['error' => 'Forbidden'], 403);
    }
    $stmt = $pdo->prepare('UPDATE monthly_reports SET year=2025, month=8 WHERE year=0 OR month=0');
    $stmt->execute();

    // Trigger backup setelah fix monthly reports
    triggerDatabaseBackup();

    jsonResponse(['ok' => true, 'message' => 'Data berhasil diperbaiki']);
}

if ($action === 'save_monthly_report' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (! isset($_SESSION['user'])) {
        jsonResponse(['error' => 'Unauthorized'], 401);
    }
    $uid = (int) $_SESSION['user']['id'];
    $year = (int) ($_POST['year'] ?? date('Y'));
    $month = (int) ($_POST['month'] ?? date('n'));
    $summary = $_POST['summary'] ?? '';
    $achievements = $_POST['achievements'] ?? '[]';
    $obstacles = $_POST['obstacles'] ?? '[]';
    $submit = isset($_POST['submit']) ? filter_var($_POST['submit'], FILTER_VALIDATE_BOOLEAN) : false;

    // Debug logging for submit parameter
    error_log('Raw POST submit: '.($_POST['submit'] ?? 'not set'));
    error_log('Filtered submit: '.($submit ? 'true' : 'false'));

    // Validate year and month
    if ($year <= 0 || $month <= 0 || $month > 12) {
        jsonResponse(['ok' => false, 'message' => 'Tahun atau bulan tidak valid'], 400);
    }

    $stmt = $pdo->prepare('SELECT * FROM monthly_reports WHERE user_id=:u AND year=:y AND month=:m');
    $stmt->execute([':u' => $uid, ':y' => $year, ':m' => $month]);
    $row = $stmt->fetch();
    if ($row && in_array($row['status'], ['approved', 'disapproved'], true)) {
        jsonResponse(['ok' => false, 'message' => 'Sudah final, tidak bisa diedit'], 400);
    }
    $newStatus = $submit ? 'belum di approve' : 'draft';

    // Debug logging
    error_log("Monthly Report Save - User: $uid, Year: $year, Month: $month, Submit: ".($submit ? 'true' : 'false').", New Status: $newStatus");
    error_log('POST data submit value: '.($_POST['submit'] ?? 'not set'));
    error_log('Boolean submit value: '.($submit ? 'true' : 'false'));

    if ($row) {
        $upd = $pdo->prepare('UPDATE monthly_reports SET summary=:s, achievements=:a, obstacles=:o, status=:st, updated_at=NOW() WHERE id=:id');
        $result = $upd->execute([':s' => $summary, ':a' => $achievements, ':o' => $obstacles, ':st' => $newStatus, ':id' => $row['id']]);
        error_log('Monthly Report Update - Result: '.($result ? 'success' : 'failed').', Rows affected: '.$upd->rowCount());

        // Trigger backup setelah update monthly report
        triggerDatabaseBackup();

        jsonResponse(['ok' => true, 'id' => $row['id']]);
    } else {
        $ins = $pdo->prepare('INSERT INTO monthly_reports (user_id, year, month, summary, achievements, obstacles, status) VALUES (:u,:y,:m,:s,:a,:o,:st)');
        $result = $ins->execute([':u' => $uid, ':y' => $year, ':m' => $month, ':s' => $summary, ':a' => $achievements, ':o' => $obstacles, ':st' => $newStatus]);
        $newId = $pdo->lastInsertId();
        error_log('Monthly Report Insert - Result: '.($result ? 'success' : 'failed').", New ID: $newId");

        // Trigger backup setelah insert monthly report
        triggerDatabaseBackup();

        jsonResponse(['ok' => true, 'id' => $newId]);
    }
}
