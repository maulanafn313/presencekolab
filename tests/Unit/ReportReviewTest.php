<?php

namespace Tests\Unit;

use App\Services\ReportReview;
use PHPUnit\Framework\TestCase;

class ReportReviewTest extends TestCase
{
    public function test_days_late_uses_calendar_days_and_never_goes_negative(): void
    {
        $this->assertSame(0, ReportReview::daysLate('2026-03-10', '2026-03-10 23:59:00'));
        $this->assertSame(1, ReportReview::daysLate('2026-03-10', '2026-03-11 00:01:00'));
        $this->assertSame(92, ReportReview::daysLate('2026-03-10', '2026-06-10 08:00:00'));
        $this->assertSame(0, ReportReview::daysLate('2026-03-10', '2026-03-09 08:00:00'));
    }

    public function test_summary_counts_every_date_and_lists_only_pending_ids(): void
    {
        $employee = ReportReview::summarize([
            $this->row(1, 11, '2025-09-01', 'pending', '2026-02-01 08:00:00'),
            $this->row(1, 12, '2026-10-01', 'pending', '2026-10-01 17:00:00'),
            $this->row(1, 13, '2026-10-02', 'approved', '2026-10-02 17:00:00'),
            $this->row(1, 14, '2026-10-03', 'disapproved', '2026-12-30 17:00:00'),
        ], [1], 5)[0];

        $this->assertSame([11, 12], $employee['pending_ids']);
        $this->assertSame(2, $employee['pending']);
        $this->assertSame(1, $employee['approved']);
        $this->assertSame(1, $employee['disapproved']);
        $this->assertSame(1, $employee['late'], 'Hanya laporan pending yang masuk antrean telat diisi.');
        $this->assertSame('2025-09-01', $employee['oldest_pending']);
    }

    public function test_late_means_beyond_the_allowed_days_back(): void
    {
        $late = fn (string $createdAt) => ReportReview::summarize([$this->row(1, 1, '2026-03-10', 'pending', $createdAt)], [1], 5)[0]['late'];

        $this->assertSame(0, $late('2026-03-15 08:00:00'));
        $this->assertSame(1, $late('2026-03-16 08:00:00'));
    }

    public function test_active_students_come_first_then_the_longest_queue(): void
    {
        $employees = ReportReview::summarize([
            $this->row(3, 1, '2026-01-01', 'pending', '2026-01-01 08:00:00'),
            $this->row(3, 2, '2026-01-02', 'pending', '2026-01-02 08:00:00'),
            $this->row(3, 3, '2026-01-03', 'pending', '2026-01-03 08:00:00'),
            $this->row(1, 4, '2026-01-01', 'pending', '2026-01-01 08:00:00'),
            $this->row(2, 5, '2026-01-01', 'pending', '2026-01-01 08:00:00'),
            $this->row(2, 6, '2026-01-02', 'pending', '2026-01-02 08:00:00'),
        ], [1, 2], 5);

        $this->assertSame([2, 1, 3], array_column($employees, 'id'));
        $this->assertTrue($employees[2]['archived']);
    }

    public function test_attendance_state_prefers_leave_notes(): void
    {
        $present = ['status' => 'terlambat', 'ket' => 'wfo'];

        $this->assertSame('sakit', ReportReview::attendanceState($present, 'sakit'));
        $this->assertSame('terlambat', ReportReview::attendanceState($present, null));
        $this->assertSame('tanpa_presensi', ReportReview::attendanceState(null, null));
    }

    public function test_period_dates_must_be_real_calendar_dates(): void
    {
        $this->assertTrue(ReportReview::isValidDate('2026-02-28'));
        $this->assertFalse(ReportReview::isValidDate('2026-02-30'));
        $this->assertFalse(ReportReview::isValidDate("2026-02-28' OR 1=1"));
    }

    private function row(int $userId, int $id, string $date, string $status, string $createdAt): array
    {
        return ['user_id' => $userId, 'nim' => (string) $userId, 'nama' => "Mahasiswa $userId", 'startup' => 'S', 'id' => $id, 'report_date' => $date, 'status' => $status, 'created_at' => $createdAt];
    }
}
