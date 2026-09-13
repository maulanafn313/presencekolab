<?php

// Extracted from core.php — do not edit the original functions here without updating core.php require.

function getIndonesianNationalHolidays($year)
{
    $holidays = [];

    // Fixed holidays (same date every year)
    $fixedHolidays = [
        '01-01' => 'Tahun Baru',
        '02-14' => 'Valentine Day',
        '03-22' => 'Hari Raya Nyepi',
        '04-18' => 'Wafat Isa Almasih',
        '05-01' => 'Hari Buruh Internasional',
        '05-09' => 'Kenaikan Isa Almasih',
        '05-20' => 'Hari Kebangkitan Nasional',
        '06-01' => 'Hari Lahir Pancasila',
        '06-17' => 'Hari Raya Idul Adha',
        '08-17' => 'Hari Kemerdekaan RI',
        '09-16' => 'Maulid Nabi Muhammad SAW',
        '10-02' => 'Hari Batik Nasional',
        '11-10' => 'Hari Pahlawan',
        '12-25' => 'Hari Raya Natal',
    ];

    // Islamic holidays (calculated based on Islamic calendar - simplified)
    // Note: These dates are approximate and should be updated yearly
    $islamicHolidays = [
        // Idul Fitri (2 days) - dates vary each year
        // Idul Adha - dates vary each year
        // Islamic New Year - dates vary each year
        // Maulid Nabi - dates vary each year
    ];

    // Add fixed holidays
    foreach ($fixedHolidays as $date => $name) {
        $holidays[] = [
            'date' => $year.'-'.$date,
            'name' => $name,
            'type' => 'fixed',
        ];
    }

    // Add Islamic holidays for specific years (2024-2025)
    if ($year == 2024) {
        $islamicHolidays2024 = [
            '2024-04-10' => 'Hari Raya Idul Fitri 1445 H',
            '2024-04-11' => 'Hari Raya Idul Fitri 1445 H (Hari Kedua)',
            '2024-06-16' => 'Hari Raya Idul Adha 1445 H',
            '2024-07-07' => 'Tahun Baru Islam 1446 H',
            '2024-09-15' => 'Maulid Nabi Muhammad SAW 1446 H',
        ];
        foreach ($islamicHolidays2024 as $date => $name) {
            $holidays[] = [
                'date' => $date,
                'name' => $name,
                'type' => 'islamic',
            ];
        }
    } elseif ($year == 2025) {
        $islamicHolidays2025 = [
            '2025-03-30' => 'Hari Raya Idul Fitri 1446 H',
            '2025-03-31' => 'Hari Raya Idul Fitri 1446 H (Hari Kedua)',
            '2025-06-06' => 'Hari Raya Idul Adha 1446 H',
            '2025-06-26' => 'Tahun Baru Islam 1447 H',
            '2025-09-05' => 'Maulid Nabi Muhammad SAW 1447 H',
        ];
        foreach ($islamicHolidays2025 as $date => $name) {
            $holidays[] = [
                'date' => $date,
                'name' => $name,
                'type' => 'islamic',
            ];
        }
    }

    return $holidays;
}

// Function to check if a date is a national holiday
function isNationalHoliday($date)
{
    static $holidaysMap = [];
    $year = substr($date, 0, 4);

    if (! isset($holidaysMap[$year])) {
        $holidays = getIndonesianNationalHolidays((int) $year);
        $holidaysMap[$year] = [];
        foreach ($holidays as $holiday) {
            $holidaysMap[$year][$holiday['date']] = true;
        }
    }

    return isset($holidaysMap[$year][$date]);
}

// Function to seed national holidays to manual_holidays table
function seedNationalHolidays(PDO $pdo)
{
    $seeded = getSetting($pdo, 'national_holidays_seeded');
    if ($seeded === '1') {
        return;
    }

    $years = [2024, 2025, 2026];
    $fixedHolidays = [
        '01-01' => 'Tahun Baru',
        '02-14' => 'Valentine Day',
        '03-22' => 'Hari Raya Nyepi',
        '04-18' => 'Wafat Isa Almasih',
        '05-01' => 'Hari Buruh Internasional',
        '05-09' => 'Kenaikan Isa Almasih',
        '05-20' => 'Hari Kebangkitan Nasional',
        '06-01' => 'Hari Lahir Pancasila',
        '06-17' => 'Hari Raya Idul Adha',
        '08-17' => 'Hari Kemerdekaan RI',
        '09-16' => 'Maulid Nabi Muhammad SAW',
        '10-02' => 'Hari Batik Nasional',
        '11-10' => 'Hari Pahlawan',
        '12-25' => 'Hari Raya Natal',
    ];

    $islamic2024 = [
        '2024-04-10' => 'Hari Raya Idul Fitri 1445 H',
        '2024-04-11' => 'Hari Raya Idul Fitri 1445 H (Hari Kedua)',
        '2024-06-16' => 'Hari Raya Idul Adha 1445 H',
        '2024-07-07' => 'Tahun Baru Islam 1446 H',
        '2024-09-15' => 'Maulid Nabi Muhammad SAW 1446 H',
    ];

    $islamic2025 = [
        '2025-03-30' => 'Hari Raya Idul Fitri 1446 H',
        '2025-03-31' => 'Hari Raya Idul Fitri 1446 H (Hari Kedua)',
        '2025-06-06' => 'Hari Raya Idul Adha 1446 H',
        '2025-06-26' => 'Tahun Baru Islam 1447 H',
        '2025-09-05' => 'Maulid Nabi Muhammad SAW 1447 H',
    ];

    $islamic2026 = [
        '2026-03-20' => 'Hari Raya Idul Fitri 1447 H',
        '2026-03-21' => 'Hari Raya Idul Fitri 1447 H (Hari Kedua)',
        '2026-05-27' => 'Hari Raya Idul Adha 1447 H',
        '2026-06-16' => 'Tahun Baru Islam 1448 H',
        '2026-08-25' => 'Maulid Nabi Muhammad SAW 1448 H',
    ];

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('INSERT IGNORE INTO manual_holidays (date, name, created_by) VALUES (:date, :name, :created_by)');

        $adminIdStmt = $pdo->query("SELECT id FROM users WHERE role = 'admin' LIMIT 1");
        $adminId = $adminIdStmt->fetchColumn() ?: null;

        foreach ($years as $year) {
            foreach ($fixedHolidays as $datePart => $name) {
                $stmt->execute([
                    ':date' => "$year-$datePart",
                    ':name' => $name,
                    ':created_by' => $adminId,
                ]);
            }
        }

        foreach ($islamic2024 as $date => $name) {
            $stmt->execute([
                ':date' => $date,
                ':name' => $name,
                ':created_by' => $adminId,
            ]);
        }

        foreach ($islamic2025 as $date => $name) {
            $stmt->execute([
                ':date' => $date,
                ':name' => $name,
                ':created_by' => $adminId,
            ]);
        }

        foreach ($islamic2026 as $date => $name) {
            $stmt->execute([
                ':date' => $date,
                ':name' => $name,
                ':created_by' => $adminId,
            ]);
        }

        $setStmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value, description) VALUES ('national_holidays_seeded', '1', 'Flag indicating national holidays have been seeded to manual_holidays table') ON DUPLICATE KEY UPDATE setting_value = '1'");
        $setStmt->execute();

        $pdo->commit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Failed to seed national holidays: '.$e->getMessage());
    }
}

// Manual holiday helpers
function isManualHoliday(PDO $pdo, $date)
{
    try {
        $stmt = $pdo->prepare('SELECT 1 FROM manual_holidays WHERE date = :d LIMIT 1');
        $stmt->execute([':d' => $date]);

        return (bool) $stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log('isManualHoliday error: '.$e->getMessage());

        return false;
    }
}

function getManualHolidaysInRange(PDO $pdo, $startDate, $endDate)
{
    try {
        $stmt = $pdo->prepare('SELECT * FROM manual_holidays WHERE date BETWEEN :s AND :e ORDER BY date');
        $stmt->execute([':s' => $startDate, ':e' => $endDate]);

        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('getManualHolidaysInRange error: '.$e->getMessage());

        return [];
    }
}

// Function to get employee's work schedule
