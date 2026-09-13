<?php

namespace App\Services\Attendance;

use DateTime;
use Illuminate\Support\Carbon;

class AttendanceClock
{
    public function now(): DateTime
    {
        // Preserve network time in production; allow deterministic business tests.
        return app()->environment('testing')
            ? DateTime::createFromInterface(Carbon::now('Asia/Jakarta'))
            : getNetworkTime();
    }
}
