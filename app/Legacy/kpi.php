<?php

// Extracted from core.php — do not edit the original functions here without updating core.php require.

function calculateKPIForEmployeeRaw(
    PDO $pdo,
    $userId,
    $periodStart = null,
    $periodEnd = null,
    $preFetchedManualHolidays = null,
    $preFetchedSchedule = null,
    $preFetchedEmployee = null,
    $preFetchedAttendance = null,
    $preFetchedIzinNotes = null,
    $preFetchedOvertime = null,
    $preFetchedDailyReports = null,
    $workStartDateOverride = null
) {
    try {
        // Get KPI settings
        $latePenaltyPerMinute = (float) getSetting($pdo, 'kpi_late_penalty_per_minute', '1');
        $lateMaxDeduction = (float) getSetting($pdo, 'kpi_late_max_deduction', '100');     // Maks pengurangan per hari (default 100 = tidak dibatasi)
        $lateToleranceMinutes = (int) getSetting($pdo, 'kpi_late_tolerance_minutes', '0');  // Toleransi menit sebelum dikurangi KPI
        $izinSakitScore = (float) getSetting($pdo, 'kpi_izin_sakit_score', '85');
        $alphaScore = (float) getSetting($pdo, 'kpi_alpha_score', '0');
        $overtimeBonus = (float) getSetting($pdo, 'kpi_overtime_bonus', '5');
        $maxOntimeSetting = getSetting($pdo, 'max_ontime_hour', '08:00');
        if (strpos($maxOntimeSetting, ':') === false) {
            $maxOntimeSetting = sprintf('%02d:00', (int) $maxOntimeSetting);
        }

        // Get employee data
        if ($preFetchedEmployee !== null) {
            $employee = $preFetchedEmployee;
        } else {
            $stmt = $pdo->prepare('SELECT nama, created_at, nim, startup, foto_base64 FROM users WHERE id = ?');
            $stmt->execute([$userId]);
            $employee = $stmt->fetch();
        }
        if (! $employee) {
            return null;
        }

        // Get employee registration date
        $employeeRegDate = $employee['created_at'];

        // Determine effective start date (custom work start date override or registration date)
        $effectiveStartDate = date('Y-m-d', strtotime($employeeRegDate));
        if ($workStartDateOverride !== null) {
            if ($workStartDateOverride !== false) {
                $effectiveStartDate = $workStartDateOverride;
            }
        } else {
            try {
                $k = 'work_start_date_user_'.$userId;
                $val = getSetting($pdo, $k);
                if ($val) {
                    $effectiveStartDate = $val;
                }
            } catch (Exception $e) {
            }
        }

        // Determine KPI start: use per-employee start setting if available, else registration date
        if (! $periodStart) {
            $periodStart = $effectiveStartDate;
        }
        if (! $periodEnd) {
            $periodEnd = date('Y-m-d');
        }

        // Fetch manual holidays if not passed
        if ($preFetchedManualHolidays === null) {
            $manualHolidaysList = getManualHolidaysInRange($pdo, $periodStart, $periodEnd);
            $preFetchedManualHolidays = [];
            foreach ($manualHolidaysList as $mh) {
                $preFetchedManualHolidays[$mh['date']] = true;
            }
        }

        // Fetch schedule if not passed
        if ($preFetchedSchedule === null) {
            $preFetchedSchedule = getEmployeeWorkSchedule($pdo, $userId);
        }

        // Get attendance records for the period (WFO, WFA, Overtime only)
        if ($preFetchedAttendance !== null) {
            $attendanceRecords = $preFetchedAttendance;
        } else {
            $st = $pdo->prepare("
                SELECT 
                    DATE(jam_masuk_iso) as attendance_date,
                    jam_masuk_iso,
                    jam_masuk,
                    status,
                    ket,
                    CASE 
                        WHEN status = 'terlambat' AND jam_masuk IS NOT NULL THEN 
                            GREATEST(0, 
                                FLOOR(
                                    TIMESTAMPDIFF(MINUTE, 
                                        CONCAT('2000-01-01 ', :max_ontime_hour1, ':00'),
                                        CONCAT('2000-01-01 ', 
                                            CASE 
                                                WHEN LENGTH(jam_masuk) = 5 THEN CONCAT(jam_masuk, ':00')
                                                ELSE jam_masuk
                                            END
                                        )
                                    )
                                )
                            )
                        WHEN status = 'terlambat' AND jam_masuk IS NULL THEN 
                            GREATEST(0, TIMESTAMPDIFF(MINUTE, 
                                CONCAT(DATE(jam_masuk_iso), ' ', :max_ontime_hour2, ':00'), 
                                jam_masuk_iso
                            ))
                        ELSE 0 
                    END as late_minutes
                FROM attendance 
                WHERE user_id = :user_id 
                AND jam_masuk_iso IS NOT NULL 
                AND DATE(jam_masuk_iso) BETWEEN DATE(:period_start) AND DATE(:period_end)
                AND ket IN ('wfo', 'wfa', 'overtime')
                ORDER BY attendance_date
            ");
            $st->execute([
                'user_id' => $userId,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'max_ontime_hour1' => $maxOntimeSetting,
                'max_ontime_hour2' => $maxOntimeSetting,
            ]);
            $attendanceRecords = $st->fetchAll();
        }

        // Get izin/sakit records from attendance_notes table
        if ($preFetchedIzinNotes !== null) {
            $izinNotesRecords = $preFetchedIzinNotes;
        } else {
            $stmt = $pdo->prepare("
                SELECT date as izin_date, type as status
                FROM attendance_notes 
                WHERE user_id = :user_id 
                AND type IN ('izin', 'sakit')
                AND date BETWEEN :period_start AND :period_end
                ORDER BY izin_date
            ");
            $stmt->execute([
                'user_id' => $userId,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
            ]);
            $izinNotesRecords = $stmt->fetchAll();
        }

        // Get overtime records (attendance marked as 'overtime')
        if ($preFetchedOvertime !== null) {
            $overtimeRecords = $preFetchedOvertime;
        } else {
            $stmt = $pdo->prepare("
                SELECT DATE(jam_masuk_iso) as overtime_date, status, jam_masuk_iso, jam_masuk
                FROM attendance 
                WHERE user_id = :user_id 
                AND DATE(jam_masuk_iso) BETWEEN :period_start AND :period_end
                AND ket = 'overtime'
                ORDER BY jam_masuk_iso ASC
            ");
            $stmt->execute([
                'user_id' => $userId,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
            ]);
            $overtimeRecords = $stmt->fetchAll();
        }

        // Get daily reports for the period
        if ($preFetchedDailyReports !== null) {
            $dailyReportsRecords = $preFetchedDailyReports;
        } else {
            $dailyReportsStmt = $pdo->prepare('
                SELECT report_date 
                FROM daily_reports 
                WHERE user_id = :user_id 
                AND report_date BETWEEN :period_start AND :period_end
            ');
            $dailyReportsStmt->execute([
                'user_id' => $userId,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
            ]);
            $dailyReportsRecords = $dailyReportsStmt->fetchAll();
        }

        // Create maps for quick lookup
        $attendanceMap = [];
        foreach ($attendanceRecords as $record) {
            $attendanceMap[$record['attendance_date']] = $record;
        }

        $dailyReportsMap = [];
        foreach ($dailyReportsRecords as $record) {
            $dailyReportsMap[$record['report_date']] = true;
        }

        $izinDates = [];
        foreach ($izinNotesRecords as $record) {
            // Only add if date is after or on effective start date AND within the period
            if ($record['izin_date'] >= $effectiveStartDate && $record['izin_date'] >= $periodStart && $record['izin_date'] <= $periodEnd) {
                $izinDates[$record['izin_date']] = $record['status'];
            }
        }

        $overtimeDates = [];
        foreach ($overtimeRecords as $record) {
            $overtimeDates[$record['overtime_date']] = $record;
        }

        // Generate working days for this specific employee in the period
        $workingDays = getEmployeeWorkingDaysInPeriod($pdo, $userId, $periodStart, $periodEnd, $preFetchedSchedule, $preFetchedManualHolidays);

        // Get current date for comparison
        $currentDate = date('Y-m-d');

        $ontimeCount = 0;
        $lateCount = 0;
        $wfoCount = 0; // NEW: Count WFO attendance
        $wfaCount = 0; // NEW: Count WFA attendance
        $totalLateMinutes = 0; // Keep for backward compatibility/reporting
        $lateRecords = []; // Store late records with minutes for per-occurrence calculation
        $izinSakitCount = 0;
        $alphaCount = 0;
        $overtimeCount = 0;
        $actualWorkingDays = 0; // Count actual working days for this employee (only past dates)
        $totalWorkingDaysInPeriod = 0; // Count all working days in period for this employee
        $missingDailyReportsCount = 0; // Count days with attendance but no daily report
        $daysWithoutReport = []; // Store dates that need daily report penalty
        $wfaDatesList = [];
        $izinSakitDatesList = [];
        $alphaDatesList = [];

        // Process each working day
        foreach ($workingDays as $dateStr) {

            // Skip dates before employee effective start date
            if ($dateStr < $effectiveStartDate) {
                continue;
            }

            // Count this as a working day for this employee (regardless of whether it's past or future)
            $totalWorkingDaysInPeriod++;

            // Only count as actual working day if the date has already passed
            if ($dateStr <= $currentDate) {
                $actualWorkingDays++;
            }

            // Check if there's an attendance record for this date
            // Use attendanceMap for faster lookup instead of looping
            $attendanceRecord = isset($attendanceMap[$dateStr]) ? $attendanceMap[$dateStr] : null;

            // Only process dates that have already passed for KPI calculation
            if ($dateStr <= $currentDate) {
                // Check if it's izin/sakit first (from attendance_notes table)
                if (isset($izinDates[$dateStr])) {
                    $izinSakitCount++;
                } elseif ($attendanceRecord) {
                    // Check if daily report exists for this date
                    $hasDailyReport = isset($dailyReportsMap[$dateStr]);

                    // If attendance exists but no daily report, mark for penalty
                    if (! $hasDailyReport && ($attendanceRecord['ket'] === 'wfo' || $attendanceRecord['ket'] === 'wfa')) {
                        $missingDailyReportsCount++;
                        $daysWithoutReport[] = $dateStr;
                    }

                    // Check attendance status (only WFO, WFA, Overtime)
                    if ($attendanceRecord['status'] === 'ontime') {
                        $ontimeCount++;
                        // Count WFO and WFA separately
                        if ($attendanceRecord['ket'] === 'wfo') {
                            $wfoCount++;
                        } elseif ($attendanceRecord['ket'] === 'wfa') {
                            $wfaCount++;
                            $wfaDatesList[] = $dateStr;
                        }
                    } else {
                        $lateCount++;
                        // Count WFO and WFA even if late
                        if ($attendanceRecord['ket'] === 'wfo') {
                            $wfoCount++;
                        } elseif ($attendanceRecord['ket'] === 'wfa') {
                            $wfaCount++;
                            $wfaDatesList[] = $dateStr;
                        }
                        $lateMinutes = (int) $attendanceRecord['late_minutes'];
                        $totalLateMinutes += $lateMinutes;
                        // Store late record with minutes for per-occurrence calculation
                        $lateRecords[] = $lateMinutes;
                    }
                } else {
                    // No attendance and no izin/sakit = alpha (only for past dates)
                    // If this date is a manual holiday, do not penalize as alpha
                    if (! isset($preFetchedManualHolidays[$dateStr])) {
                        $alphaCount++;
                        $alphaDatesList[] = $dateStr;
                    }
                }
            }
        }

        // Count overtime days (including weekends and holidays)
        foreach ($overtimeDates as $overtimeDate => $overtimeRecord) {
            $overtimeCount++;
        }

        // Count izin/sakit directly from the records (more reliable)
        $currentDate = date('Y-m-d');
        $directIzinSakitCount = 0;
        foreach ($izinNotesRecords as $record) {
            if ($record['izin_date'] >= $effectiveStartDate &&
                $record['izin_date'] >= $periodStart &&
                $record['izin_date'] <= $periodEnd &&
                $record['izin_date'] <= $currentDate) {
                $directIzinSakitCount++;
                $izinSakitDatesList[] = $record['izin_date'];
            }
        }

        // Use the direct count if it's different from the loop count
        if ($directIzinSakitCount != $izinSakitCount) {
            $izinSakitCount = $directIzinSakitCount;
        }

        $wfaDatesList = array_unique($wfaDatesList);
        sort($wfaDatesList);
        $izinSakitDatesList = array_unique($izinSakitDatesList);
        sort($izinSakitDatesList);
        $alphaDatesList = array_unique($alphaDatesList);
        sort($alphaDatesList);

        // Calculate actual working days based on days with actual data
        // This should be the sum of all days with attendance records (ontime, late, alpha, izin/sakit)
        // NOT the total working days in period, because we only calculate KPI for days with data
        $daysWithData = (int) $ontimeCount + (int) $lateCount + (int) $izinSakitCount + (int) $alphaCount;

        // IMPORTANT: Always use daysWithData as divisor if it's greater than 0
        // This ensures KPI is calculated correctly: total score / days with data
        // Only fallback to actualWorkingDays if daysWithData is 0 (shouldn't happen in normal cases)
        if ($daysWithData > 0) {
            $actualDaysForKPI = $daysWithData;
        } else {
            // Fallback: use actualWorkingDays only if no data at all
            $actualDaysForKPI = $actualWorkingDays > 0 ? $actualWorkingDays : 1; // Prevent division by zero
        }

        // Calculate KPI score using new per-occurrence method
        // Formula:
        // - On-time: 100% each
        // - Late: 100% - (minutes late) for each occurrence
        // - Alpha: 0% each
        // - Izin/Sakit: use setting score (default 85%)
        // - Overtime: bonus (default 5%)
        // Total = sum of all scores / days with actual data
        $kpiScore = 0;

        // On-time: 100% each
        $ontimeScore = $ontimeCount * 100;
        $kpiScore += $ontimeScore;

        // Late: calculate per occurrence with tolerance and max deduction
        $lateTotalScore = 0;
        foreach ($lateRecords as $lateMinutes) {
            // Apply tolerance: kurangi menit toleransi dari menit keterlambatan
            // Jika masih dalam toleransi, tidak ada pengurangan KPI
            $effectiveLateMinutes = max(0, $lateMinutes - $lateToleranceMinutes);

            // Formula: 100% - (menit terlambat efektif setelah toleransi)
            // Contoh toleransi 15 menit, terlambat 10 menit -> efektif 0 menit -> skor = 100
            // Contoh toleransi 15 menit, terlambat 25 menit -> efektif 10 menit -> skor = 90
            $rawDeduction = $effectiveLateMinutes; // 1 menit = 1% pengurangan (default)

            // Apply max deduction cap: pengurangan tidak boleh melebihi batas maksimal
            // Contoh: lateMaxDeduction=30 artinya minimal skor = 70% meski terlambat berapa pun
            $actualDeduction = min($rawDeduction, $lateMaxDeduction);

            $lateScore = 100 - $actualDeduction;
            $lateScore = max(0, $lateScore); // Pastikan tidak negatif
            $lateTotalScore += $lateScore;
        }
        $kpiScore += $lateTotalScore;

        // Alpha: 0% each (no need to add, already 0)
        // $kpiScore += ($alphaCount * 0); // Not needed

        // Izin/Sakit: use setting score (default 85%)
        $izinSakitScoreTotal = $izinSakitCount * $izinSakitScore;
        $kpiScore += $izinSakitScoreTotal;

        // Overtime: bonus (default 5% per occurrence)
        $overtimeScoreTotal = $overtimeCount * $overtimeBonus;
        $kpiScore += $overtimeScoreTotal;

        // Apply daily report penalty: reduce 50% per day without report
        // This penalty is applied per day, not from total score
        $dailyReportPenalty = 0;
        if (isset($daysWithoutReport) && is_array($daysWithoutReport)) {
            foreach ($daysWithoutReport as $dateWithoutReport) {
                // Find the score for that day
                $dayScore = 0;
                if (isset($attendanceMap[$dateWithoutReport])) {
                    $dayRecord = $attendanceMap[$dateWithoutReport];
                    if ($dayRecord['status'] === 'ontime') {
                        $dayScore = 100;
                    } else {
                        // Late: 100 - minutes late
                        $lateMinutes = (int) $dayRecord['late_minutes'];
                        $dayScore = max(0, 100 - $lateMinutes);
                    }
                }
                // Reduce 50% of that day's score
                $penaltyForDay = $dayScore * 0.5;
                $dailyReportPenalty += $penaltyForDay;
            }
        }
        $kpiScore -= $dailyReportPenalty;

        // Store raw points before dividing
        $kpiPoints = $kpiScore;

        // Calculate average based on days with actual data
        $kpiScore = $kpiScore / $actualDaysForKPI;

        // Ensure score is between 0 and 100
        $kpiScore = max(0, min(100, $kpiScore));

        // Determine KPI status
        $status = 'Very Poor';
        if ($kpiScore >= 90) {
            $status = 'Excellent';
        } elseif ($kpiScore >= 80) {
            $status = 'Good';
        } elseif ($kpiScore >= 70) {
            $status = 'Fair';
        } elseif ($kpiScore >= 60) {
            $status = 'Poor';
        }

        return [
            'user_id' => $userId,
            'nama' => $employee['nama'],
            'nim' => $employee['nim'] ?? '-',
            'startup' => $employee['startup'] ?? '-',
            'foto_base64' => $employee['foto_base64'] ?? '',
            'total_working_days' => $totalWorkingDaysInPeriod, // Total working days in period
            'actual_working_days' => $actualWorkingDays, // Days that have passed for KPI calculation
            'ontime_count' => $ontimeCount,
            'wfo_count' => $wfoCount, // NEW: Add WFO count
            'wfa_count' => $wfaCount, // NEW: Add WFA count
            'late_count' => $lateCount,
            'izin_sakit_count' => $izinSakitCount,
            'alpha_count' => $alphaCount,
            'overtime_count' => $overtimeCount,
            'missing_daily_reports_count' => $missingDailyReportsCount,
            'total_late_minutes' => $totalLateMinutes,
            'kpi_points' => $kpiPoints,
            'days_with_data' => $actualDaysForKPI,
            'kpi_score' => round($kpiScore, 2),
            'status' => $status,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'employee_registration_date' => $employeeRegDate,
            'wfa_dates' => implode(',', $wfaDatesList),
            'izin_sakit_dates' => implode(',', $izinSakitDatesList),
            'alpha_dates' => implode(',', $alphaDatesList),
        ];

    } catch (Exception $e) {
        error_log('KPI calculation error: '.$e->getMessage());

        return null;
    }
}

function calculateKPIForEmployee(
    PDO $pdo,
    $userId,
    $periodStart = null,
    $periodEnd = null,
    $preFetchedManualHolidays = null,
    $preFetchedSchedule = null,
    $preFetchedEmployee = null,
    $preFetchedAttendance = null,
    $preFetchedIzinNotes = null,
    $preFetchedOvertime = null,
    $preFetchedDailyReports = null,
    $workStartDateOverride = null
) {
    // If any pre-fetched parameters are provided, bypass cache and calculate raw
    if ($preFetchedManualHolidays !== null || $preFetchedSchedule !== null ||
        $preFetchedEmployee !== null || $preFetchedAttendance !== null ||
        $preFetchedIzinNotes !== null || $preFetchedOvertime !== null ||
        $preFetchedDailyReports !== null) {
        return calculateKPIForEmployeeRaw(
            $pdo, $userId, $periodStart, $periodEnd,
            $preFetchedManualHolidays, $preFetchedSchedule, $preFetchedEmployee,
            $preFetchedAttendance, $preFetchedIzinNotes, $preFetchedOvertime,
            $preFetchedDailyReports, $workStartDateOverride
        );
    }

    // Get employee details
    if ($preFetchedEmployee !== null) {
        $employee = $preFetchedEmployee;
    } else {
        $stmt = $pdo->prepare('SELECT nama, created_at, nim, startup, foto_base64 FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $employee = $stmt->fetch();
    }
    if (! $employee) {
        return null;
    }

    // Normalize period start and end
    if (! $periodStart) {
        $employeeReg = $employee['created_at'];
        $periodStart = date('Y-m-d', strtotime($employeeReg));
        // Check for override
        try {
            $k = 'work_start_date_user_'.$userId;
            $val = getSetting($pdo, $k);
            if ($val) {
                $periodStart = $val;
            }
        } catch (Exception $e) {
        }
    }
    if (! $periodEnd) {
        $periodEnd = date('Y-m-d');
    }

    // Calculate month intervals
    $startDateTime = new DateTime($periodStart);
    $endDateTime = new DateTime($periodEnd);
    $currentYearMonth = date('Y-m');

    $months = [];
    $iter = new DateTime($periodStart);
    $iter->modify('first day of this month');
    while ($iter <= $endDateTime) {
        $mYear = (int) $iter->format('Y');
        $mMonth = (int) $iter->format('n');
        $mYearMonthStr = $iter->format('Y-m');

        $mStart = max($periodStart, $iter->format('Y-m-01'));
        $mEnd = min($periodEnd, $iter->format('Y-m-t'));

        $isFullMonth = ($mStart === $iter->format('Y-m-01') && $mEnd === $iter->format('Y-m-t'));
        $isPastMonth = ($mYearMonthStr < $currentYearMonth);

        $months[] = [
            'year' => $mYear,
            'month' => $mMonth,
            'start' => $mStart,
            'end' => $mEnd,
            'cacheable' => ($isFullMonth && $isPastMonth),
        ];

        $iter->modify('+1 month');
    }

    // Aggregate data
    $aggregated = [
        'total_working_days' => 0,
        'actual_working_days' => 0,
        'ontime_count' => 0,
        'wfo_count' => 0,
        'wfa_count' => 0,
        'late_count' => 0,
        'izin_sakit_count' => 0,
        'alpha_count' => 0,
        'overtime_count' => 0,
        'missing_daily_reports_count' => 0,
        'total_late_minutes' => 0,
        'kpi_points' => 0.00,
        'days_with_data' => 0,
    ];

    $aggregatedWfaDates = [];
    $aggregatedIzinSakitDates = [];
    $aggregatedAlphaDates = [];

    foreach ($months as $m) {
        $cachedRow = null;
        if ($m['cacheable']) {
            // Check cache
            $cStmt = $pdo->prepare('SELECT * FROM kpi_monthly_cache WHERE user_id = :user_id AND year = :year AND month = :month LIMIT 1');
            $cStmt->execute([':user_id' => $userId, ':year' => $m['year'], ':month' => $m['month']]);
            $cachedRow = $cStmt->fetch();

            if (! $cachedRow) {
                // Not cached, calculate raw for this full month
                $raw = calculateKPIForEmployeeRaw(
                    $pdo, $userId, $m['start'], $m['end'],
                    null, null, $employee, null, null, null, null, $workStartDateOverride
                );
                if ($raw) {
                    // Save to cache
                    try {
                        $ins = $pdo->prepare('
                            INSERT INTO kpi_monthly_cache (
                                user_id, year, month, ontime_count, wfo_count, wfa_count, 
                                late_count, izin_sakit_count, alpha_count, overtime_count, 
                                missing_daily_reports_count, total_late_minutes, total_working_days, 
                                actual_working_days, kpi_points, days_with_data,
                                wfa_dates, izin_sakit_dates, alpha_dates
                            ) VALUES (
                                :user_id, :year, :month, :ontime_count, :wfo_count, :wfa_count, 
                                :late_count, :izin_sakit_count, :alpha_count, :overtime_count, 
                                :missing_daily_reports_count, :total_late_minutes, :total_working_days, 
                                :actual_working_days, :kpi_points, :days_with_data,
                                :wfa_dates, :izin_sakit_dates, :alpha_dates
                            ) ON DUPLICATE KEY UPDATE 
                                ontime_count = VALUES(ontime_count),
                                wfo_count = VALUES(wfo_count),
                                wfa_count = VALUES(wfa_count),
                                late_count = VALUES(late_count),
                                izin_sakit_count = VALUES(izin_sakit_count),
                                alpha_count = VALUES(alpha_count),
                                overtime_count = VALUES(overtime_count),
                                missing_daily_reports_count = VALUES(missing_daily_reports_count),
                                total_late_minutes = VALUES(total_late_minutes),
                                total_working_days = VALUES(total_working_days),
                                actual_working_days = VALUES(actual_working_days),
                                kpi_points = VALUES(kpi_points),
                                days_with_data = VALUES(days_with_data),
                                wfa_dates = VALUES(wfa_dates),
                                izin_sakit_dates = VALUES(izin_sakit_dates),
                                alpha_dates = VALUES(alpha_dates)
                        ');
                        $ins->execute([
                            ':user_id' => $userId,
                            ':year' => $m['year'],
                            ':month' => $m['month'],
                            ':ontime_count' => $raw['ontime_count'],
                            ':wfo_count' => $raw['wfo_count'],
                            ':wfa_count' => $raw['wfa_count'],
                            ':late_count' => $raw['late_count'],
                            ':izin_sakit_count' => $raw['izin_sakit_count'],
                            ':alpha_count' => $raw['alpha_count'],
                            ':overtime_count' => $raw['overtime_count'],
                            ':missing_daily_reports_count' => $raw['missing_daily_reports_count'],
                            ':total_late_minutes' => $raw['total_late_minutes'],
                            ':total_working_days' => $raw['total_working_days'],
                            ':actual_working_days' => $raw['actual_working_days'],
                            ':kpi_points' => $raw['kpi_points'] ?? 0.00,
                            ':days_with_data' => $raw['days_with_data'] ?? 0,
                            ':wfa_dates' => $raw['wfa_dates'] ?? '',
                            ':izin_sakit_dates' => $raw['izin_sakit_dates'] ?? '',
                            ':alpha_dates' => $raw['alpha_dates'] ?? '',
                        ]);
                    } catch (Exception $e) {
                        error_log('Failed to write to KPI cache: '.$e->getMessage());
                    }
                    $cachedRow = $raw;
                }
            }
        }

        if ($cachedRow) {
            $aggregated['total_working_days'] += $cachedRow['total_working_days'];
            $aggregated['actual_working_days'] += $cachedRow['actual_working_days'];
            $aggregated['ontime_count'] += $cachedRow['ontime_count'];
            $aggregated['wfo_count'] += $cachedRow['wfo_count'];
            $aggregated['wfa_count'] += $cachedRow['wfa_count'];
            $aggregated['late_count'] += $cachedRow['late_count'];
            $aggregated['izin_sakit_count'] += $cachedRow['izin_sakit_count'];
            $aggregated['alpha_count'] += $cachedRow['alpha_count'];
            $aggregated['overtime_count'] += $cachedRow['overtime_count'];
            $aggregated['missing_daily_reports_count'] += $cachedRow['missing_daily_reports_count'];
            $aggregated['total_late_minutes'] += $cachedRow['total_late_minutes'];
            $aggregated['kpi_points'] += ($cachedRow['kpi_points'] ?? 0.00);
            $aggregated['days_with_data'] += ($cachedRow['days_with_data'] ?? 0);
            if (! empty($cachedRow['wfa_dates'])) {
                $aggregatedWfaDates = array_merge($aggregatedWfaDates, explode(',', $cachedRow['wfa_dates']));
            }
            if (! empty($cachedRow['izin_sakit_dates'])) {
                $aggregatedIzinSakitDates = array_merge($aggregatedIzinSakitDates, explode(',', $cachedRow['izin_sakit_dates']));
            }
            if (! empty($cachedRow['alpha_dates'])) {
                $aggregatedAlphaDates = array_merge($aggregatedAlphaDates, explode(',', $cachedRow['alpha_dates']));
            }
        } else {
            // Calculate on the fly for non-cacheable range
            $raw = calculateKPIForEmployeeRaw(
                $pdo, $userId, $m['start'], $m['end'],
                null, null, $employee, null, null, null, null, $workStartDateOverride
            );
            if ($raw) {
                $aggregated['total_working_days'] += $raw['total_working_days'];
                $aggregated['actual_working_days'] += $raw['actual_working_days'];
                $aggregated['ontime_count'] += $raw['ontime_count'];
                $aggregated['wfo_count'] += $raw['wfo_count'];
                $aggregated['wfa_count'] += $raw['wfa_count'];
                $aggregated['late_count'] += $raw['late_count'];
                $aggregated['izin_sakit_count'] += $raw['izin_sakit_count'];
                $aggregated['alpha_count'] += $raw['alpha_count'];
                $aggregated['overtime_count'] += $raw['overtime_count'];
                $aggregated['missing_daily_reports_count'] += $raw['missing_daily_reports_count'];
                $aggregated['total_late_minutes'] += $raw['total_late_minutes'];
                $aggregated['kpi_points'] += ($raw['kpi_points'] ?? 0.00);
                $aggregated['days_with_data'] += ($raw['days_with_data'] ?? 0);
                if (! empty($raw['wfa_dates'])) {
                    $aggregatedWfaDates = array_merge($aggregatedWfaDates, explode(',', $raw['wfa_dates']));
                }
                if (! empty($raw['izin_sakit_dates'])) {
                    $aggregatedIzinSakitDates = array_merge($aggregatedIzinSakitDates, explode(',', $raw['izin_sakit_dates']));
                }
                if (! empty($raw['alpha_dates'])) {
                    $aggregatedAlphaDates = array_merge($aggregatedAlphaDates, explode(',', $raw['alpha_dates']));
                }
            }
        }
    }

    // Calculate final KPI score
    $finalScore = 0.00;
    if ($aggregated['days_with_data'] > 0) {
        $finalScore = $aggregated['kpi_points'] / $aggregated['days_with_data'];
    }
    $finalScore = max(0, min(100, $finalScore));

    // Determine status
    $status = 'Very Poor';
    if ($finalScore >= 90) {
        $status = 'Excellent';
    } elseif ($finalScore >= 80) {
        $status = 'Good';
    } elseif ($finalScore >= 70) {
        $status = 'Fair';
    } elseif ($finalScore >= 60) {
        $status = 'Poor';
    }

    $cleanDates = function ($datesArray) {
        $filtered = array_filter(array_map('trim', $datesArray));
        $unique = array_unique($filtered);
        sort($unique);

        return implode(',', $unique);
    };

    return [
        'user_id' => $userId,
        'nama' => $employee['nama'],
        'nim' => $employee['nim'] ?? '-',
        'startup' => $employee['startup'] ?? '-',
        'foto_base64' => $employee['foto_base64'] ?? '',
        'total_working_days' => $aggregated['total_working_days'],
        'actual_working_days' => $aggregated['actual_working_days'],
        'ontime_count' => $aggregated['ontime_count'],
        'wfo_count' => $aggregated['wfo_count'],
        'wfa_count' => $aggregated['wfa_count'],
        'late_count' => $aggregated['late_count'],
        'izin_sakit_count' => $aggregated['izin_sakit_count'],
        'alpha_count' => $aggregated['alpha_count'],
        'overtime_count' => $aggregated['overtime_count'],
        'missing_daily_reports_count' => $aggregated['missing_daily_reports_count'],
        'total_late_minutes' => $aggregated['total_late_minutes'],
        'kpi_score' => round($finalScore, 2),
        'status' => $status,
        'period_start' => $periodStart,
        'period_end' => $periodEnd,
        'employee_registration_date' => $employee['created_at'],
        'wfa_dates' => $cleanDates($aggregatedWfaDates),
        'izin_sakit_dates' => $cleanDates($aggregatedIzinSakitDates),
        'alpha_dates' => $cleanDates($aggregatedAlphaDates),
    ];
}

function clearKpiCache(PDO $pdo, $userId, $date)
{
    if (! $userId || ! $date) {
        return;
    }
    try {
        $year = (int) date('Y', strtotime($date));
        $month = (int) date('m', strtotime($date));
        $stmt = $pdo->prepare('DELETE FROM kpi_monthly_cache WHERE user_id = :user_id AND year = :year AND month = :month');
        $stmt->execute([':user_id' => $userId, ':year' => $year, ':month' => $month]);
    } catch (Exception $e) {
        error_log('Failed to clear KPI cache: '.$e->getMessage());
    }
}

function clearAllKpiCache(PDO $pdo)
{
    try {
        $pdo->exec('TRUNCATE TABLE kpi_monthly_cache');
    } catch (Exception $e) {
        error_log('Failed to truncate KPI cache: '.$e->getMessage());
    }
}

function filterLiveRecords(array $records, $startDate, $endDate, $dateField = 'attendance_date')
{
    $filtered = [];
    foreach ($records as $r) {
        $dateVal = $r[$dateField] ?? null;
        if (! $dateVal && $dateField === 'attendance_date') {
            $dateVal = isset($r['jam_masuk_iso']) ? date('Y-m-d', strtotime($r['jam_masuk_iso'])) : null;
        }
        if ($dateVal && $dateVal >= $startDate && $dateVal <= $endDate) {
            $filtered[] = $r;
        }
    }

    return $filtered;
}

// Function to get Indonesian national holidays for a given year
