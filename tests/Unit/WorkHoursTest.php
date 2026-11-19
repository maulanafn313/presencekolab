<?php

namespace Tests\Unit;

use App\Services\Attendance\FridayStatusCorrection;
use App\Services\Attendance\WorkHours;
use PHPUnit\Framework\TestCase;

class WorkHoursTest extends TestCase
{
    private const FRIDAY = '2026-11-20';

    private const THURSDAY = '2026-11-19';

    private const FRIDAY_BEFORE_RULE = '2026-07-03';

    public function test_friday_has_its_own_hours(): void
    {
        $this->assertSame('07:30', WorkHours::latestOnTime(self::FRIDAY, '08:00'));
        $this->assertSame('16:30', WorkHours::earliestCheckout(self::FRIDAY, '17:00'));
        $this->assertSame('07:30', WorkHours::defaultStart(self::FRIDAY));
        $this->assertSame('16:30', WorkHours::defaultEnd(self::FRIDAY));
    }

    public function test_other_days_keep_the_admin_setting(): void
    {
        $this->assertSame('08:15', WorkHours::latestOnTime(self::THURSDAY, '08:15'));
        $this->assertSame('17:30', WorkHours::earliestCheckout(self::THURSDAY, '17:30'));
        $this->assertSame('08:00', WorkHours::defaultStart(self::THURSDAY));
        $this->assertSame('17:00', WorkHours::defaultEnd(self::THURSDAY));
    }

    public function test_fridays_before_the_internship_start_keep_regular_hours(): void
    {
        $this->assertSame('08:00', WorkHours::latestOnTime(self::FRIDAY_BEFORE_RULE, '08:00'));
        $this->assertSame('17:00', WorkHours::earliestCheckout(self::FRIDAY_BEFORE_RULE, '17:00'));
        $this->assertFalse(WorkHours::hasFridayHours('2026-07-03 07:45:00'));
        $this->assertTrue(WorkHours::hasFridayHours('2026-07-10 07:45:00'));
    }

    public function test_friday_limit_includes_the_whole_0730_minute(): void
    {
        $this->assertSame('ontime', FridayStatusCorrection::expectedStatus('07:30'));
        $this->assertSame('ontime', FridayStatusCorrection::expectedStatus('07:30:59'));
        $this->assertSame('terlambat', FridayStatusCorrection::expectedStatus('07:31'));
    }
}
