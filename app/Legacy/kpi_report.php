<?php

// Extracted from core.php — do not edit the original functions here without updating core.php require.

function getAllKPIData(PDO $pdo, $customPeriodStart = null, $customPeriodEnd = null, $includePhotos = false, $filterGroupId = null)
{
    try {
        $periodStart = $customPeriodStart ?? getEarliestEmployeeRegistrationDate($pdo);
        $periodEnd = $customPeriodEnd ?? date('Y-m-d');

        // --- Build archived user set & per-user period override from intern_groups ---
        $archivedUserIds = [];      // user_id => true  (semua kelompok archived, tidak ada yg aktif)
        $userHasActiveGroup = [];     // user_id => true  (punya kelompok non-archived)
        $userPeriodStartOverride = []; // user_id => earliest tanggal_mulai dari kelompok aktif
        $userPeriodEndOverride = []; // user_id => latest  tanggal_selesai dari kelompok yang sudah selesai
        try {
            $groupStmt = $pdo->query('
                SELECT igm.user_id, ig.id as group_id, ig.is_archived, ig.tanggal_mulai, ig.tanggal_selesai
                FROM intern_group_members igm
                JOIN intern_groups ig ON ig.id = igm.group_id
            ');
            $groupRows = $groupStmt->fetchAll();
            foreach ($groupRows as $gr) {
                $uid = (int) $gr['user_id'];
                if (! $gr['is_archived']) {
                    // Punya kelompok aktif
                    $userHasActiveGroup[$uid] = true;
                    // Period start: gunakan tanggal_mulai kelompok paling awal (yang aktif)
                    $grpStart = $gr['tanggal_mulai'];
                    if (! isset($userPeriodStartOverride[$uid]) || $grpStart < $userPeriodStartOverride[$uid]) {
                        $userPeriodStartOverride[$uid] = $grpStart;
                    }
                    // Period end: jika kelompok sudah selesai (tanggal_selesai < hari ini), override end
                    $groupEnd = $gr['tanggal_selesai'];
                    if ($groupEnd < date('Y-m-d')) {
                        if (! isset($userPeriodEndOverride[$uid]) || $groupEnd > $userPeriodEndOverride[$uid]) {
                            $userPeriodEndOverride[$uid] = $groupEnd;
                        }
                    }
                } else {
                    // Kelompok ini archived — tandai user (mungkin juga ada kelompok aktif lain)
                    if (! isset($archivedUserIds[$uid])) {
                        $archivedUserIds[$uid] = true; // Sementara, akan di-override jika ada kelompok aktif
                    }
                }
            }
            // Hapus dari archivedUserIds jika ternyata ada kelompok aktif
            foreach (array_keys($userHasActiveGroup) as $uid) {
                unset($archivedUserIds[$uid]);
            }
        } catch (PDOException $e) {
            // Table may not exist yet — ignore silently
        }

        // Jika filter per-group, ambil tanggal_mulai & tanggal_selesai kelompok itu
        $filterGroupStart = null;
        $filterGroupEnd = null;
        if ($filterGroupId) {
            try {
                $fgStmt = $pdo->prepare('SELECT tanggal_mulai, tanggal_selesai FROM intern_groups WHERE id = :id LIMIT 1');
                $fgStmt->execute([':id' => $filterGroupId]);
                $fg = $fgStmt->fetch();
                if ($fg) {
                    $filterGroupStart = $fg['tanggal_mulai'];
                    $filterGroupEnd = $fg['tanggal_selesai'];
                }
            } catch (PDOException $e) {
            }
        }

        // Get all employees
        $photoField = $includePhotos ? ', foto_base64' : '';

        if ($filterGroupId) {
            // Hanya ambil pegawai dalam kelompok tertentu
            $stmt = $pdo->prepare("SELECT u.id, u.nama, u.created_at, u.nim, u.startup $photoField FROM users u JOIN intern_group_members igm ON igm.user_id = u.id WHERE u.role = 'pegawai' AND igm.group_id = :gid ORDER BY u.nama");
            $stmt->execute([':gid' => $filterGroupId]);
        } else {
            $stmt = $pdo->prepare("SELECT id, nama, created_at, nim, startup $photoField FROM users WHERE role = 'pegawai' ORDER BY nama");
            $stmt->execute();
        }
        $employees = $stmt->fetchAll();

        if (empty($employees)) {
            return [
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'kpi_data' => [],
            ];
        }

        // Break period into months
        $currentYearMonth = date('Y-m');
        $iter = new DateTime($periodStart);
        $iter->modify('first day of this month');
        $endDateTime = new DateTime($periodEnd);

        $cachedMonths = [];
        $liveRanges = [];

        while ($iter <= $endDateTime) {
            $mYear = (int) $iter->format('Y');
            $mMonth = (int) $iter->format('n');
            $mYearMonthStr = $iter->format('Y-m');

            $mStart = max($periodStart, $iter->format('Y-m-01'));
            $mEnd = min($periodEnd, $iter->format('Y-m-t'));

            $isFullMonth = ($mStart === $iter->format('Y-m-01') && $mEnd === $iter->format('Y-m-t'));
            $isPastMonth = false; // Disable cache for real-time KPI

            if ($isFullMonth && $isPastMonth) {
                $cachedMonths[] = ['year' => $mYear, 'month' => $mMonth];
            } else {
                $liveRanges[] = ['start' => $mStart, 'end' => $mEnd];
            }
            $iter->modify('+1 month');
        }

        // 1. Fetch cached months for all employees in bulk
        $cachedDataByUser = [];
        if (! empty($cachedMonths)) {
            $whereParts = [];
            $params = [];
            foreach ($cachedMonths as $idx => $cm) {
                $whereParts[] = "(year = :y$idx AND month = :m$idx)";
                $params[":y$idx"] = $cm['year'];
                $params[":m$idx"] = $cm['month'];
            }
            $whereSql = implode(' OR ', $whereParts);
            $cStmt = $pdo->prepare("SELECT * FROM kpi_monthly_cache WHERE $whereSql");
            $cStmt->execute($params);
            $allCachedRows = $cStmt->fetchAll();

            foreach ($allCachedRows as $row) {
                $uid = $row['user_id'];
                if (! isset($cachedDataByUser[$uid])) {
                    $cachedDataByUser[$uid] = [];
                }
                $cachedDataByUser[$uid][] = $row;
            }
        }

        // 2. Pre-fetch raw data ONLY for the live/ongoing ranges
        $hasLiveRange = ! empty($liveRanges);
        $preFetchedManualHolidays = [];
        $schedulesByUser = [];
        $attendanceByUser = [];
        $notesByUser = [];
        $overtimeByUser = [];
        $reportsByUser = [];

        if ($hasLiveRange) {
            $starts = array_map(function ($r) {
                return $r['start'];
            }, $liveRanges);
            $ends = array_map(function ($r) {
                return $r['end'];
            }, $liveRanges);
            $liveStart = min($starts);
            $liveEnd = max($ends);

            $manualHolidaysList = getManualHolidaysInRange($pdo, $liveStart, $liveEnd);
            foreach ($manualHolidaysList as $mh) {
                $preFetchedManualHolidays[$mh['date']] = true;
            }

            $schedulesStmt = $pdo->prepare('SELECT * FROM employee_work_schedule');
            $schedulesStmt->execute();
            $allSchedules = $schedulesStmt->fetchAll();
            foreach ($allSchedules as $sch) {
                $uid = $sch['user_id'];
                if (! isset($schedulesByUser[$uid])) {
                    $schedulesByUser[$uid] = [];
                }
                $schedulesByUser[$uid][$sch['day_of_week']] = [
                    'is_working_day' => (bool) $sch['is_working_day'],
                    'start_time' => $sch['start_time'],
                    'end_time' => $sch['end_time'],
                ];
            }

            $maxOntimeSetting = getSetting($pdo, 'max_ontime_hour', '08:00');
            if (strpos($maxOntimeSetting, ':') === false) {
                $maxOntimeSetting = sprintf('%02d:00', (int) $maxOntimeSetting);
            }
            $attendanceStmt = $pdo->prepare("
                SELECT 
                    user_id,
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
                WHERE jam_masuk_iso BETWEEN :period_start AND :period_end
                AND ket IN ('wfo', 'wfa', 'overtime')
                ORDER BY jam_masuk_iso ASC
            ");
            $attendanceStmt->execute([
                'period_start' => $liveStart.' 00:00:00',
                'period_end' => $liveEnd.' 23:59:59',
                'max_ontime_hour1' => $maxOntimeSetting,
                'max_ontime_hour2' => $maxOntimeSetting,
            ]);
            $allAttendance = $attendanceStmt->fetchAll();
            foreach ($allAttendance as $att) {
                $uid = $att['user_id'];
                if (! isset($attendanceByUser[$uid])) {
                    $attendanceByUser[$uid] = [];
                }
                $attendanceByUser[$uid][] = $att;
            }

            $notesStmt = $pdo->prepare("
                SELECT user_id, date as izin_date, type as status
                FROM attendance_notes 
                WHERE type IN ('izin', 'sakit')
                AND date BETWEEN :period_start AND :period_end
                ORDER BY date
            ");
            $notesStmt->execute([
                'period_start' => $liveStart,
                'period_end' => $liveEnd,
            ]);
            $allNotes = $notesStmt->fetchAll();
            foreach ($allNotes as $note) {
                $uid = $note['user_id'];
                if (! isset($notesByUser[$uid])) {
                    $notesByUser[$uid] = [];
                }
                $notesByUser[$uid][] = $note;
            }

            $overtimeStmt = $pdo->prepare("
                SELECT user_id, DATE(jam_masuk_iso) as overtime_date, status, jam_masuk_iso, jam_masuk
                FROM attendance 
                WHERE DATE(jam_masuk_iso) BETWEEN :period_start AND :period_end
                AND ket = 'overtime'
                ORDER BY jam_masuk_iso ASC
            ");
            $overtimeStmt->execute([
                'period_start' => $liveStart,
                'period_end' => $liveEnd,
            ]);
            $allOvertime = $overtimeStmt->fetchAll();
            foreach ($allOvertime as $ot) {
                $uid = $ot['user_id'];
                if (! isset($overtimeByUser[$uid])) {
                    $overtimeByUser[$uid] = [];
                }
                $overtimeByUser[$uid][] = $ot;
            }

            $reportsStmt = $pdo->prepare('
                SELECT user_id, report_date 
                FROM daily_reports 
                WHERE report_date BETWEEN :period_start AND :period_end
            ');
            $reportsStmt->execute([
                'period_start' => $liveStart,
                'period_end' => $liveEnd,
            ]);
            $allReports = $reportsStmt->fetchAll();
            foreach ($allReports as $rep) {
                $uid = $rep['user_id'];
                if (! isset($reportsByUser[$uid])) {
                    $reportsByUser[$uid] = [];
                }
                $reportsByUser[$uid][] = $rep;
            }
        }

        // Fetch work start date settings overrides
        $overridesStmt = $pdo->prepare("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'work_start_date_user_%'");
        $overridesStmt->execute();
        $allOverrides = $overridesStmt->fetchAll();
        $workStartDateOverrides = [];
        foreach ($allOverrides as $ov) {
            $uid = (int) str_replace('work_start_date_user_', '', $ov['setting_key']);
            $workStartDateOverrides[$uid] = $ov['setting_value'];
        }

        // Get KPI settings for bulk calculations
        getSetting($pdo, 'kpi_late_penalty_per_minute', '1');
        getSetting($pdo, 'kpi_izin_sakit_score', '85');
        getSetting($pdo, 'kpi_alpha_score', '0');
        getSetting($pdo, 'kpi_overtime_bonus', '5');

        $kpiData = [];
        foreach ($employees as $employee) {
            $uid = $employee['id'];

            // Skip pegawai yang tergabung dalam kelompok archived dan TIDAK punya kelompok aktif
            if (isset($archivedUserIds[$uid]) && ! $filterGroupId) {
                continue;
            }

            // --- Tentukan effective period start & end per employee ---

            // 1. Effective period START:
            //    Prioritas: filterGroup tanggal_mulai > intern_group tanggal_mulai > workStartDateOverride > periodStart global
            $effectivePeriodStart = $periodStart;
            if ($filterGroupId && $filterGroupStart) {
                // Filter per-group: mulai dari tanggal_mulai kelompok itu
                $effectivePeriodStart = $filterGroupStart;
            } elseif (isset($userPeriodStartOverride[$uid])) {
                // User ada di kelompok aktif: gunakan tanggal_mulai kelompok terlama
                // Tapi jangan lebih awal dari periodStart global (jika admin set custom)
                $grpStart = $userPeriodStartOverride[$uid];
                if ($customPeriodStart === null) {
                    // Tidak ada custom period: pakai tanggal_mulai kelompok
                    $effectivePeriodStart = $grpStart;
                } else {
                    // Ada custom period: ambil yang lebih baru antara custom dan tanggal_mulai kelompok
                    $effectivePeriodStart = max($customPeriodStart, $grpStart);
                }
            }

            // 2. Effective period END:
            //    Prioritas: filterGroup tanggal_selesai > userPeriodEndOverride > periodEnd global
            $effectivePeriodEnd = $periodEnd;
            if ($filterGroupId && $filterGroupEnd && $customPeriodEnd === null) {
                // Filter per-group: batas akhir = tanggal_selesai kelompok (jika sudah selesai)
                if ($filterGroupEnd < $periodEnd) {
                    $effectivePeriodEnd = $filterGroupEnd;
                }
            } elseif (isset($userPeriodEndOverride[$uid]) && $userPeriodEndOverride[$uid] < $periodEnd) {
                $effectivePeriodEnd = $userPeriodEndOverride[$uid];
            }

            // workStartDateOverride (dari settings table) digunakan untuk override start di calculateKPIForEmployeeRaw
            // Hanya pakai jika TIDAK ada kelompok override yang lebih spesifik
            $empOverride = false; // Default: biarkan calculateKPIForEmployeeRaw pakai effectivePeriodStart
            if (! isset($userPeriodStartOverride[$uid]) && ! $filterGroupId) {
                // Tidak ada kelompok: pakai override dari settings table jika ada
                $empOverride = isset($workStartDateOverrides[$uid]) ? $workStartDateOverrides[$uid] : false;
            }

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

            // Add cached records
            $cachedRows = $cachedDataByUser[$uid] ?? [];
            foreach ($cachedRows as $row) {
                $aggregated['total_working_days'] += $row['total_working_days'];
                $aggregated['actual_working_days'] += $row['actual_working_days'];
                $aggregated['ontime_count'] += $row['ontime_count'];
                $aggregated['wfo_count'] += $row['wfo_count'];
                $aggregated['wfa_count'] += $row['wfa_count'];
                $aggregated['late_count'] += $row['late_count'];
                $aggregated['izin_sakit_count'] += $row['izin_sakit_count'];
                $aggregated['alpha_count'] += $row['alpha_count'];
                $aggregated['overtime_count'] += $row['overtime_count'];
                $aggregated['missing_daily_reports_count'] += $row['missing_daily_reports_count'];
                $aggregated['total_late_minutes'] += $row['total_late_minutes'];
                $aggregated['kpi_points'] += (float) $row['kpi_points'];
                $aggregated['days_with_data'] += $row['days_with_data'];
                if (! empty($row['wfa_dates'])) {
                    $aggregatedWfaDates = array_merge($aggregatedWfaDates, explode(',', $row['wfa_dates']));
                }
                if (! empty($row['izin_sakit_dates'])) {
                    $aggregatedIzinSakitDates = array_merge($aggregatedIzinSakitDates, explode(',', $row['izin_sakit_dates']));
                }
                if (! empty($row['alpha_dates'])) {
                    $aggregatedAlphaDates = array_merge($aggregatedAlphaDates, explode(',', $row['alpha_dates']));
                }
            }

            $cachedMonthsFound = [];
            foreach ($cachedRows as $row) {
                $cachedMonthsFound[$row['year'].'-'.$row['month']] = true;
            }

            // Loop through all months and process live/missing months
            // Use effectivePeriodStart & effectivePeriodEnd per employee
            $empStartDateTime = new DateTime($effectivePeriodStart);
            $empStartDateTime->modify('first day of this month');
            $empEndDateTime = new DateTime($effectivePeriodEnd);
            $iter = clone $empStartDateTime;
            while ($iter <= $empEndDateTime) {
                $mYear = (int) $iter->format('Y');
                $mMonth = (int) $iter->format('n');
                $mYearMonthStr = $iter->format('Y-m');

                // Batasi mStart ke effectivePeriodStart (bukan periodStart global)
                $mStart = max($effectivePeriodStart, $iter->format('Y-m-01'));
                $mEnd = min($effectivePeriodEnd, $iter->format('Y-m-t'));

                $isFullMonth = ($mStart === $iter->format('Y-m-01') && $mEnd === $iter->format('Y-m-t'));
                $isPastMonth = false; // Disable cache for real-time KPI

                $isCacheable = ($isFullMonth && $isPastMonth);
                $hasCache = isset($cachedMonthsFound[$mYear.'-'.$mMonth]);

                if ($isCacheable && $hasCache) {
                    // Already processed
                } else {
                    $raw = null;
                    if ($isCacheable && ! $hasCache) {
                        // Missing from cache, compute raw for full month and write to cache
                        $raw = calculateKPIForEmployeeRaw(
                            $pdo, $uid, $mStart, $mEnd,
                            null, null, $employee, null, null, null, null, $empOverride
                        );
                        if ($raw) {
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
                                    ':user_id' => $uid,
                                    ':year' => $mYear,
                                    ':month' => $mMonth,
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
                                error_log('Failed to write to KPI cache in bulk: '.$e->getMessage());
                            }
                        }
                    } else {
                        // Current ongoing month or partial range: Calculate from pre-fetched live records
                        $empSchedule = $schedulesByUser[$uid] ?? [];
                        $empAttendance = filterLiveRecords($attendanceByUser[$uid] ?? [], $mStart, $mEnd);
                        $empNotes = filterLiveRecords($notesByUser[$uid] ?? [], $mStart, $mEnd, 'izin_date');
                        $empOvertime = filterLiveRecords($overtimeByUser[$uid] ?? [], $mStart, $mEnd, 'overtime_date');
                        $empReports = filterLiveRecords($reportsByUser[$uid] ?? [], $mStart, $mEnd, 'report_date');

                        $raw = calculateKPIForEmployeeRaw(
                            $pdo, $uid, $mStart, $mEnd,
                            $preFetchedManualHolidays, $empSchedule, $employee,
                            $empAttendance, $empNotes, $empOvertime, $empReports, $empOverride
                        );
                    }

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
                $iter->modify('+1 month');
            }

            // Calculate final score
            $finalScore = 0.00;
            if ($aggregated['days_with_data'] > 0) {
                $finalScore = $aggregated['kpi_points'] / $aggregated['days_with_data'];
            }
            $finalScore = max(0, min(100, $finalScore));

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

            $kpiData[] = [
                'user_id' => $uid,
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

        // Sort by KPI score descending
        usort($kpiData, function ($a, $b) {
            return $b['kpi_score'] <=> $a['kpi_score'];
        });

        return [
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'kpi_data' => $kpiData,
        ];

    } catch (Exception $e) {
        error_log('Get all KPI data error: '.$e->getMessage());

        return null;
    }
}
