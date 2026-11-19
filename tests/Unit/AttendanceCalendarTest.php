<?php

namespace Tests\Unit;

use App\Services\AttendanceCalendar;
use DomainException;
use PHPUnit\Framework\TestCase;

class AttendanceCalendarTest extends TestCase
{
    private const TODAY = '2026-11-11'; // Rabu

    public function test_each_state_is_resolved(): void
    {
        $cells = $this->cells();

        $this->assertSame('hadir', $cells['2026-11-02']['state']);
        $this->assertSame('terlambat', $cells['2026-11-03']['state']);
        $this->assertSame('wfa', $cells['2026-11-04']['state']);
        $this->assertTrue($cells['2026-11-04']['late']);
        $this->assertSame('sakit', $cells['2026-11-05']['state']);
        $this->assertSame('alpha', $cells['2026-11-06']['state']);
        $this->assertSame('libur', $cells['2026-11-07']['state']);
        $this->assertSame('belum', $cells[self::TODAY]['state']);
    }

    public function test_leave_note_wins_over_attendance_and_keeps_its_reference(): void
    {
        $this->assertSame('note_7', $this->cells()['2026-11-05']['ref']);
    }

    public function test_future_dates_are_empty(): void
    {
        $this->assertArrayNotHasKey('2026-11-12', $this->cells());
    }

    public function test_missing_report_is_only_billed_for_past_present_days(): void
    {
        $cells = $this->cells();

        $this->assertSame('ok', $cells['2026-11-02']['report']);
        $this->assertSame('missing', $cells['2026-11-03']['report']);
    }

    public function test_day_without_any_activity_is_not_alpha(): void
    {
        $this->assertSame('nodata', $this->cells()['2026-11-10']['state']);
    }

    public function test_totals_add_up(): void
    {
        $totals = $this->calendar()['students'][0]['totals'];

        $this->assertSame(['present' => 3, 'late' => 2, 'leave' => 1, 'alpha' => 1, 'missing_reports' => 2], $totals);
    }

    public function test_student_outside_the_month_is_left_out(): void
    {
        $data = $this->data();
        $data['employees'][0]['last_day'] = '2026-10-31';

        $this->assertSame([], AttendanceCalendar::compose($data, '2026-11', self::TODAY, $this->working())['students']);
    }

    public function test_invalid_month_is_rejected(): void
    {
        $this->expectException(DomainException::class);
        AttendanceCalendar::range('2026-13');
    }

    private function cells(): array
    {
        return (array) $this->calendar()['students'][0]['cells'];
    }

    private function calendar(): array
    {
        return AttendanceCalendar::compose($this->data(), '2026-11', self::TODAY, $this->working());
    }

    private function working(): callable
    {
        return fn (int $id, string $day) => date('N', strtotime($day)) < 6;
    }

    private function data(): array
    {
        $att = fn (int $id, string $day, string $ket, string $status) => ['id' => $id, 'user_id' => 1, 'day' => $day, 'jam_masuk' => '08:00', 'jam_pulang' => '17:00', 'status' => $status, 'ket' => $ket];

        return [
            'employees' => [['id' => 1, 'nim' => '1', 'nama' => 'Andi', 'startup' => 'Uni-Inside', 'first_day' => '2026-11-02', 'last_day' => null]],
            'attendance' => [
                $att(1, '2026-11-02', 'wfo', 'ontime'), $att(2, '2026-11-03', 'wfo', 'terlambat'),
                $att(3, '2026-11-04', 'wfa', 'terlambat'), $att(4, '2026-11-05', 'wfo', 'ontime'),
                ['id' => 5, 'user_id' => 9, 'day' => '2026-11-06', 'jam_masuk' => '08:00', 'jam_pulang' => null, 'status' => 'ontime', 'ket' => 'wfo'],
            ],
            'notes' => [['id' => 7, 'user_id' => 1, 'day' => '2026-11-05', 'type' => 'sakit']],
            'reports' => [['user_id' => 1, 'day' => '2026-11-02']],
            'holidays' => [],
            'startup_names' => ['uniinside' => 'Uni Inside'],
        ];
    }
}
