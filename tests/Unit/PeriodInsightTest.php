<?php

namespace Tests\Unit;

use App\Services\PeriodInsight;
use DomainException;
use PHPUnit\Framework\TestCase;

class PeriodInsightTest extends TestCase
{
    public function test_full_month_compares_with_previous_calendar_month(): void
    {
        $period = PeriodInsight::periods('2026-03-01', '2026-03-31', '2026-11-18');

        $this->assertSame(['2026-02-01', '2026-02-28'], [$period['previous_start'], $period['previous_end']]);
    }

    public function test_week_compares_with_preceding_week(): void
    {
        $period = PeriodInsight::periods('2026-11-09', '2026-11-15', '2026-11-18');

        $this->assertSame(['2026-11-02', '2026-11-08'], [$period['previous_start'], $period['previous_end']]);
    }

    public function test_today_is_never_counted(): void
    {
        $this->assertSame('2026-11-17', PeriodInsight::periods('2026-11-09', '2026-11-20', '2026-11-18')['end']);

        $this->expectException(DomainException::class);
        PeriodInsight::periods('2026-11-18', '2026-11-18', '2026-11-18');
    }

    public function test_attendance_states_follow_kpi_priority(): void
    {
        // Sen 2 hadir, Sel 3 terlambat, Rab 4 izin, Kam 5 hadir + izin, Jum 6 alpha.
        $m = $this->insight()['totals'];

        $this->assertSame(5, $m['expected']);
        $this->assertSame(2, $m['present']);
        $this->assertSame(1, $m['late']);
        $this->assertSame(2, $m['leave']);
        $this->assertSame(1, $m['alpha']);
        $this->assertSame(40.0, $m['attendance_rate']);
        $this->assertSame(50.0, $m['late_rate']);
    }

    public function test_report_timeliness_allows_one_day(): void
    {
        $m = $this->insight()['totals'];

        $this->assertSame(2, $m['report_due']);
        $this->assertSame(2, $m['report_filled']);
        $this->assertSame(1, $m['report_timely']);
    }

    public function test_day_without_any_activity_is_flagged_not_alpha(): void
    {
        $data = $this->data();
        $data['employees'][] = $this->employee(2, 'Budi', 'Uni-Inside');
        $result = PeriodInsight::analyze($data, $this->period(), fn () => true);

        $this->assertContains('2026-11-07', array_column(array_column($result['quality'], 'samples', 'key')['gap_days'], 'nama'));
        $this->assertSame(10, $result['totals']['expected'], 'Tanggal 7 tidak dihitung sebagai hari kerja kedua mahasiswa.');
    }

    public function test_student_who_never_checked_in_is_flagged(): void
    {
        $data = $this->data();
        $data['employees'][] = $this->employee(2, 'Budi', 'Uni Inside');
        $result = PeriodInsight::analyze($data, $this->period(), fn () => true);

        $this->assertSame('Budi', array_column($result['quality'], 'samples', 'key')['never_present'][0]['nama']);
    }

    public function test_startup_variants_are_reported_as_one_team(): void
    {
        $data = $this->data();
        $data['employees'][] = $this->employee(2, 'Budi', 'Uni-Inside');
        $result = PeriodInsight::analyze($data, $this->period(), fn () => true);

        $this->assertSame(['Uni Inside'], array_column($result['startups'], 'name'));
        $this->assertSame(2, $result['startups'][0]['students']);
        $this->assertSame(1, array_column($result['quality'], 'count', 'key')['unregistered_startup']);
    }

    public function test_archived_student_is_not_counted_after_internship_ended(): void
    {
        $data = $this->data();
        $data['employees'][0]['last_day'] = '2026-11-03';
        $result = PeriodInsight::analyze($data, $this->period(), fn () => true);

        $this->assertSame(2, $result['totals']['expected']);
        $this->assertSame([], $result['risks'], 'Mahasiswa arsip tidak perlu ditindaklanjuti.');
    }

    public function test_consecutive_alpha_marks_active_student_as_risk(): void
    {
        $data = $this->data();
        $data['employees'][] = $this->employee(2, 'Budi', 'Uni Inside');
        $result = PeriodInsight::analyze($data, $this->period(), fn () => true);

        $this->assertSame('Budi', $result['risks'][0]['nama']);
        $this->assertStringContainsString('Alpha 5 hari kerja berturut-turut', $result['risks'][0]['reasons'][0]);
    }

    private function insight(): array
    {
        return PeriodInsight::analyze($this->data(), $this->period(), fn () => true);
    }

    private function period(): array
    {
        return ['start' => '2026-11-02', 'end' => '2026-11-07', 'requested_end' => '2026-11-07', 'previous_start' => '2026-10-26', 'previous_end' => '2026-11-01'];
    }

    private function employee(int $id, string $name, string $startup): array
    {
        return ['id' => $id, 'nim' => (string) $id, 'nama' => $name, 'startup' => $startup, 'first_day' => '2026-01-01', 'last_day' => null];
    }

    private function data(): array
    {
        $att = fn (string $day, string $status) => ['user_id' => 1, 'day' => $day, 'hour' => $status === 'terlambat' ? 9 : 7, 'status' => $status, 'ket' => 'wfo', 'jam_pulang' => '17:00'];

        return [
            'employees' => [$this->employee(1, 'Andi', 'Uni Inside')],
            'attendance' => [
                $att('2026-11-02', 'ontime'), $att('2026-11-03', 'terlambat'), $att('2026-11-05', 'ontime'),
                ['user_id' => 9, 'day' => '2026-11-06', 'hour' => 7, 'status' => 'ontime', 'ket' => 'wfo', 'jam_pulang' => '17:00'],
            ],
            'notes' => [['user_id' => 1, 'day' => '2026-11-04'], ['user_id' => 1, 'day' => '2026-11-05']],
            'reports' => [
                ['user_id' => 1, 'day' => '2026-11-02', 'created_day' => '2026-11-03', 'filled' => 1],
                ['user_id' => 1, 'day' => '2026-11-03', 'created_day' => '2026-11-09', 'filled' => 1],
            ],
            'startup_names' => ['uniinside' => 'Uni Inside'],
        ];
    }
}
