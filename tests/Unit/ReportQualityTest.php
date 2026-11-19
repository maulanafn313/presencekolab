<?php

namespace Tests\Unit;

use App\Services\ReportQuality;
use PHPUnit\Framework\TestCase;

class ReportQualityTest extends TestCase
{
    private const LONG = 'Mengerjakan halaman dashboard dan memperbaiki bug login aplikasi';

    public function test_normal_report_is_not_flagged(): void
    {
        $this->assertSame([], ReportQuality::flags([$this->report(1, 1, '2026-11-02', self::LONG)], ['1|2026-11-02' => true]));
    }

    public function test_short_report_ignores_punctuation_and_html(): void
    {
        $flags = ReportQuality::flags([$this->report(1, 1, '2026-11-02', '<p>ok!!! ... ---</p>')], ['1|2026-11-02' => true]);

        $this->assertSame('short', $flags[1][0]['type']);
    }

    public function test_only_later_copy_of_own_report_is_flagged(): void
    {
        $flags = ReportQuality::flags([
            $this->report(1, 1, '2026-11-02', self::LONG),
            $this->report(2, 1, '2026-11-03', strtoupper(self::LONG).'!'),
        ], ['1|2026-11-02' => true, '1|2026-11-03' => true]);

        $this->assertArrayNotHasKey(1, $flags);
        $this->assertSame('Sama dengan laporan 2026-11-02', $flags[2][0]['label']);
    }

    public function test_same_text_from_two_students_flags_both_without_naming_them(): void
    {
        $flags = ReportQuality::flags([
            $this->report(1, 1, '2026-11-02', self::LONG),
            $this->report(2, 2, '2026-11-02', self::LONG),
        ], ['1|2026-11-02' => true, '2|2026-11-02' => true]);

        $this->assertSame('Sama dengan laporan mahasiswa lain', $flags[1][0]['label']);
        $this->assertSame('copied', $flags[2][0]['type']);
    }

    public function test_report_without_attendance_is_flagged(): void
    {
        $flags = ReportQuality::flags([$this->report(1, 1, '2026-11-02', self::LONG)], []);

        $this->assertSame('no_attendance', $flags[1][0]['type']);
    }

    private function report(int $id, int $userId, string $date, string $content): array
    {
        return ['id' => $id, 'user_id' => $userId, 'report_date' => $date, 'content' => $content];
    }
}
