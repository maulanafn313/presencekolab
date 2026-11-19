<?php

namespace Tests\Unit;

use App\Services\ActionCenter;
use App\Services\AdminActivity;
use PHPUnit\Framework\TestCase;

class AdminActivityTest extends TestCase
{
    public function test_sensitive_fields_are_never_logged(): void
    {
        $detail = AdminActivity::safeDetail(['id' => 7, 'nama' => 'Andi', 'password' => 'rahasia', 'foto' => 'data:image', 'content' => 'isi laporan', 'embedding' => '[1,2]']);

        $this->assertSame('id: 7, nama: Andi', $detail);
    }

    public function test_empty_input_has_no_detail(): void
    {
        $this->assertNull(AdminActivity::safeDetail(['password' => 'rahasia', 'status' => '']));
    }

    public function test_action_center_hides_finished_work_and_keeps_urgent_first(): void
    {
        $items = ActionCenter::items(['pending_reports' => 4, 'help_requests' => 2, 'absent' => 0]);

        $this->assertSame(['help_requests', 'pending_reports'], array_column($items, 'key'));
        $this->assertSame('review-laporan', $items[1]['target']);
    }
}
