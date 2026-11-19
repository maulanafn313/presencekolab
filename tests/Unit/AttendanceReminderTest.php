<?php

namespace Tests\Unit;

use App\Services\AttendanceReminder;
use PHPUnit\Framework\TestCase;

class AttendanceReminderTest extends TestCase
{
    private const TODAY = '2026-11-18'; // Rabu

    public function test_student_without_attendance_today_is_listed(): void
    {
        $student = $this->remind()['students'][0];

        $this->assertSame('Andi', $student['nama']);
        $this->assertTrue($student['absent_today']);
        $this->assertSame(0, $student['missing_reports']);
    }

    public function test_attendance_or_leave_today_clears_the_absence(): void
    {
        $this->assertSame([], $this->remind(attendance: [$this->att(self::TODAY)])['students']);
        $this->assertSame([], $this->remind(notes: [$this->day(self::TODAY)])['students']);
    }

    public function test_non_working_day_and_future_start_are_not_billed(): void
    {
        $this->assertSame([], $this->remind(isWorkingDay: fn () => false)['students']);
        $this->assertSame([], $this->remind(startDates: [1 => '2026-11-19'])['students']);
    }

    public function test_only_present_days_inside_the_window_need_a_report(): void
    {
        $student = $this->remind(attendance: [
            $this->att(self::TODAY),
            $this->att('2026-11-17'),
            $this->att('2026-11-16'),
            $this->att('2026-11-13', 'overtime'),
            $this->att('2026-11-12'),
        ], reports: [$this->day('2026-11-16')], maxDaysBack: 5)['students'][0];

        $this->assertFalse($student['absent_today']);
        $this->assertSame(1, $student['missing_reports'], 'Hanya 17 Nov: 16 Nov terisi, overtime tidak wajib, 12 Nov di luar batas.');
    }

    public function test_todays_report_is_not_billed_yet(): void
    {
        $this->assertSame([], $this->remind(attendance: [$this->att(self::TODAY)])['students']);
    }

    public function test_leave_day_does_not_need_a_report(): void
    {
        $result = $this->remind(attendance: [$this->att(self::TODAY), $this->att('2026-11-17')], notes: [$this->day('2026-11-17')]);

        $this->assertSame([], $result['students']);
    }

    private function remind(array $attendance = [], array $notes = [], array $reports = [], ?callable $isWorkingDay = null, array $startDates = [], int $maxDaysBack = 5): array
    {
        $employees = [['id' => 1, 'nama' => 'Andi', 'startup' => 'Alpha', 'created_at' => '2026-01-05 08:00:00']];

        return AttendanceReminder::summarize($employees, $attendance, $notes, $reports, $isWorkingDay ?? fn () => true, $startDates, self::TODAY, $maxDaysBack);
    }

    private function att(string $day, string $ket = 'wfo'): array
    {
        return ['user_id' => 1, 'day' => $day, 'ket' => $ket];
    }

    private function day(string $day): array
    {
        return ['user_id' => 1, 'day' => $day];
    }
}
