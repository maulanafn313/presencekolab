<?php

// Extracted from core.php — do not edit the original functions here without updating core.php require.

function getEmployeeWorkSchedule(PDO $pdo, $userId)
{
    try {
        $stmt = $pdo->prepare('SELECT * FROM employee_work_schedule WHERE user_id = :user_id');
        $stmt->execute([':user_id' => $userId]);
        $schedules = $stmt->fetchAll();

        $scheduleMap = [];
        foreach ($schedules as $schedule) {
            $scheduleMap[$schedule['day_of_week']] = [
                'is_working_day' => (bool) $schedule['is_working_day'],
                'start_time' => $schedule['start_time'],
                'end_time' => $schedule['end_time'],
            ];
        }

        return $scheduleMap;
    } catch (PDOException $e) {
        error_log('Error getting employee work schedule: '.$e->getMessage());

        return [];
    }
}

// Function to check if a specific date is a working day for an employee
function isEmployeeWorkingDay(PDO $pdo, $userId, $date, $preFetchedSchedule = null, $preFetchedManualHolidays = null)
{
    $dateObj = new DateTime($date);
    $dayOfWeek = strtolower($dateObj->format('l')); // monday, tuesday, etc.

    $schedule = $preFetchedSchedule !== null ? $preFetchedSchedule : getEmployeeWorkSchedule($pdo, $userId);

    $isHoliday = ($preFetchedManualHolidays !== null ? isset($preFetchedManualHolidays[$date]) : isManualHoliday($pdo, $date));

    // If no specific schedule found, use default (Monday-Friday)
    if (empty($schedule)) {
        $dayNumber = $dateObj->format('N');

        return $dayNumber < 6 && ! $isHoliday;
    }

    // Check if employee works on this day
    if (isset($schedule[$dayOfWeek])) {
        return $schedule[$dayOfWeek]['is_working_day'] && ! $isHoliday;
    }

    return false;
}

// Function to get working days for a specific employee in a period
function getEmployeeWorkingDaysInPeriod(PDO $pdo, $userId, $startDate, $endDate, $preFetchedSchedule = null, $preFetchedManualHolidays = null)
{
    static $calendarCache = [];
    $cacheKey = $startDate.'_'.$endDate;

    if (! isset($calendarCache[$cacheKey])) {
        $days = [];
        $current = new DateTime($startDate);
        $end = new DateTime($endDate);

        $dayNames = [
            1 => 'monday',
            2 => 'tuesday',
            3 => 'wednesday',
            4 => 'thursday',
            5 => 'friday',
            6 => 'saturday',
            7 => 'sunday',
        ];

        while ($current <= $end) {
            $dateStr = $current->format('Y-m-d');
            $dayNum = (int) $current->format('N');
            $isHoliday = ($preFetchedManualHolidays !== null ? isset($preFetchedManualHolidays[$dateStr]) : isManualHoliday($pdo, $dateStr));

            $days[] = [
                'date' => $dateStr,
                'dayNum' => $dayNum,
                'dayName' => $dayNames[$dayNum],
                'isHoliday' => $isHoliday,
            ];
            $current->modify('+1 day');
        }
        $calendarCache[$cacheKey] = $days;
    }

    $calendarDays = $calendarCache[$cacheKey];
    $schedule = $preFetchedSchedule !== null ? $preFetchedSchedule : getEmployeeWorkSchedule($pdo, $userId);
    $hasSchedule = ! empty($schedule);

    $workingDays = [];
    foreach ($calendarDays as $day) {
        $dateStr = $day['date'];
        $dayNum = $day['dayNum'];
        $dayOfWeek = $day['dayName'];
        $isHoliday = $day['isHoliday'];

        $isWorkingDay = false;
        if (! $hasSchedule) {
            $isWorkingDay = $dayNum < 6 && ! $isHoliday;
        } else {
            if (isset($schedule[$dayOfWeek])) {
                $isWorkingDay = $schedule[$dayOfWeek]['is_working_day'] && ! $isHoliday;
            }
        }

        if ($isWorkingDay) {
            $workingDays[] = $dateStr;
        }
    }

    return $workingDays;
}

function getWorkingDaysInPeriod($startDate, $endDate)
{
    $workingDays = [];
    $start = new DateTime($startDate);
    $end = new DateTime($endDate);

    while ($start <= $end) {
        $dateStr = $start->format('Y-m-d');
        $dayOfWeek = $start->format('N');

        // Skip weekends (Saturday = 6, Sunday = 0)
        if ($dayOfWeek < 6) {
            // Check if it's not a national or manual holiday
            if (! (isset($GLOBALS['pdo']) ? isManualHoliday($GLOBALS['pdo'], $dateStr) : false)) {
                $workingDays[] = clone $start;
            }
        }
        $start->add(new DateInterval('P1D'));
    }

    return $workingDays;
}

function getWorkingDaysInMonth($year, $month)
{
    $workingDays = 0;
    $start = new DateTime("$year-$month-01");
    $end = new DateTime("$year-$month-".$start->format('t')); // Last day of month

    while ($start <= $end) {
        $dateStr = $start->format('Y-m-d');
        $dayOfWeek = $start->format('N');

        // Skip weekends (Saturday = 6, Sunday = 0)
        if ($dayOfWeek < 6) {
            // Check if it's not a national or manual holiday
            if (! (isset($GLOBALS['pdo']) ? isManualHoliday($GLOBALS['pdo'], $dateStr) : false)) {
                $workingDays++;
            }
        }
        $start->add(new DateInterval('P1D'));
    }

    return $workingDays;
}

function getWorkingDaysInMonthUpToDate($year, $month, $day)
{
    $workingDays = 0;
    $start = new DateTime("$year-$month-01");
    $end = new DateTime("$year-$month-".str_pad($day, 2, '0', STR_PAD_LEFT));

    // Subtract 1 day from end to exclude today (don't count today for alpha calculation)
    $end->sub(new DateInterval('P1D'));

    while ($start <= $end) {
        $dateStr = $start->format('Y-m-d');
        $dayOfWeek = $start->format('N');

        // Skip weekends (Saturday = 6, Sunday = 0)
        if ($dayOfWeek < 6) {
            // Check if it's not a national or manual holiday
            if (! (isset($GLOBALS['pdo']) ? isManualHoliday($GLOBALS['pdo'], $dateStr) : false)) {
                $workingDays++;
            }
        }
        $start->add(new DateInterval('P1D'));
    }

    return $workingDays;
}

function getEarliestEmployeeRegistrationDate(PDO $pdo)
{
    try {
        $stmt = $pdo->prepare("SELECT MIN(created_at) as earliest_date FROM users WHERE role = 'pegawai'");
        $stmt->execute();
        $result = $stmt->fetch();

        return $result ? $result['earliest_date'] : date('Y-01-01');
    } catch (PDOException $e) {
        error_log('Error getting earliest employee registration date: '.$e->getMessage());

        return date('Y-01-01');
    }
}

function getEmployeeRegistrationDate(PDO $pdo, $userId)
{
    try {
        $stmt = $pdo->prepare("SELECT created_at FROM users WHERE id = :user_id AND role = 'pegawai'");
        $stmt->execute([':user_id' => $userId]);
        $result = $stmt->fetch();

        return $result ? $result['created_at'] : null;
    } catch (PDOException $e) {
        error_log('Error getting employee registration date: '.$e->getMessage());

        return null;
    }
}
