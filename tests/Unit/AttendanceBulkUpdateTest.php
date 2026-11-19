<?php

namespace Tests\Unit;

use App\Services\AttendanceBulkUpdate;
use PHPUnit\Framework\TestCase;

class AttendanceBulkUpdateTest extends TestCase
{
    public function test_ids_separate_attendance_rows_from_leave_notes(): void
    {
        $this->assertSame(['attendance' => [12, 7], 'notes' => [3]], AttendanceBulkUpdate::parseIds('12,note_3,7,12'));
    }

    public function test_invalid_id_lists_are_rejected(): void
    {
        $this->assertNull(AttendanceBulkUpdate::parseIds(''));
        $this->assertNull(AttendanceBulkUpdate::parseIds('1,2 OR 1=1'));
        $this->assertNull(AttendanceBulkUpdate::parseIds('note_0'));
        $this->assertNull(AttendanceBulkUpdate::parseIds(implode(',', range(1, AttendanceBulkUpdate::MAX_ROWS + 1))));
    }

    public function test_change_must_name_an_allowed_value(): void
    {
        $this->assertTrue(AttendanceBulkUpdate::isValidChange('terlambat', null));
        $this->assertTrue(AttendanceBulkUpdate::isValidChange(null, 'wfa'));
        $this->assertFalse(AttendanceBulkUpdate::isValidChange(null, null));
        $this->assertFalse(AttendanceBulkUpdate::isValidChange('-', null));
        $this->assertFalse(AttendanceBulkUpdate::isValidChange(null, 'alpha'));
    }

    public function test_attendance_rows_move_to_notes_only_for_leave(): void
    {
        $this->assertSame('to_note', AttendanceBulkUpdate::planAttendance('ontime', 'wfo', null, 'sakit'));
        $this->assertSame('update', AttendanceBulkUpdate::planAttendance('ontime', 'wfo', null, 'wfa'));
        $this->assertSame('update', AttendanceBulkUpdate::planAttendance('ontime', 'wfo', 'terlambat', null));
        $this->assertSame('none', AttendanceBulkUpdate::planAttendance('ontime', 'wfo', 'ontime', 'wfo'));
    }

    public function test_leave_notes_ignore_status_only_changes(): void
    {
        $this->assertSame('none', AttendanceBulkUpdate::planNote('izin', null));
        $this->assertSame('none', AttendanceBulkUpdate::planNote('izin', 'izin'));
        $this->assertSame('retype', AttendanceBulkUpdate::planNote('izin', 'sakit'));
        $this->assertSame('to_attendance', AttendanceBulkUpdate::planNote('izin', 'wfo'));
    }
}
