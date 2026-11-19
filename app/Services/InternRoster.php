<?php

namespace App\Services;

use PDO;

/** Daftar mahasiswa beserta masa magang dan hari kerjanya. */
final class InternRoster
{
    /** Mahasiswa arsip hanya dihitung sampai magangnya selesai. */
    public static function employees(PDO $pdo): array
    {
        $startDates = [];
        foreach ($pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'work_start_date_user_%'")->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $startDates[(int) substr($row['setting_key'], strlen('work_start_date_user_'))] = $row['setting_value'];
        }

        $groups = [];
        foreach ($pdo->query('SELECT m.user_id, g.is_archived, COALESCE(g.tanggal_selesai, DATE(g.archived_at)) AS last_day FROM intern_group_members m JOIN intern_groups g ON g.id = m.group_id')->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $groups[$row['user_id']][] = $row;
        }

        $users = $pdo->query("SELECT id, nim, nama, startup, created_at FROM users WHERE role = 'pegawai' ORDER BY nama")->fetchAll(PDO::FETCH_ASSOC);

        return array_map(function (array $user) use ($groups, $startDates) {
            $memberships = $groups[$user['id']] ?? [];
            $archived = $memberships !== [] && ! in_array(0, array_map('intval', array_column($memberships, 'is_archived')), true);

            return [
                'id' => (int) $user['id'],
                'nim' => $user['nim'],
                'nama' => $user['nama'],
                'startup' => $user['startup'],
                'first_day' => $startDates[(int) $user['id']] ?? substr((string) $user['created_at'], 0, 10),
                'last_day' => $archived ? max(array_column($memberships, 'last_day')) : null,
            ];
        }, $users);
    }

    /** @return callable(int, string): bool */
    public static function workingDayChecker(PDO $pdo, string $from, string $to): callable
    {
        $stmt = $pdo->prepare('SELECT `date` FROM manual_holidays WHERE `date` BETWEEN ? AND ?');
        $stmt->execute([$from, $to]);
        $holidays = array_fill_keys($stmt->fetchAll(PDO::FETCH_COLUMN), true);

        $schedules = [];
        foreach ($pdo->query('SELECT user_id, day_of_week, is_working_day FROM employee_work_schedule')->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $schedules[$row['user_id']][$row['day_of_week']] = ['is_working_day' => (bool) $row['is_working_day']];
        }

        return fn (int $userId, string $date) => isEmployeeWorkingDay($pdo, $userId, $date, $schedules[$userId] ?? [], $holidays);
    }
}
