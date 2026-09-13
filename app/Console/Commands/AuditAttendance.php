<?php

namespace App\Console\Commands;

use App\Services\Attendance\AttendanceAudit;
use Illuminate\Console\Command;

class AuditAttendance extends Command
{
    protected $signature = 'absen:audit {--json}';

    protected $description = 'Read-only attendance integrity audit; no automatic historical corrections';

    public function handle(AttendanceAudit $audit): int
    {
        $data = $audit->summary();
        if ($this->option('json')) {
            $this->line(json_encode($data, JSON_PRETTY_PRINT));
        } else {
            $this->table(['Check', 'Count'], collect($data)->map(fn ($count, $check) => [$check, $count]));
        }

        return self::SUCCESS;
    }
}
