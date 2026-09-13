<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Attendance\AttendanceCorrection;
use Illuminate\Console\Command;

class CorrectAttendance extends Command
{
    protected $signature = 'absen:correct {id} {--actor=} {--reason=} {--clock-in=} {--clock-out=} {--ket=}';

    protected $description = 'Apply an explicit, audited administrative correction';

    public function handle(AttendanceCorrection $corrections): int
    {
        $actor = User::find($this->option('actor'));
        if (! $actor || $actor->role !== 'admin' || ! trim((string) $this->option('reason'))) {
            $this->error('ID admin dan alasan koreksi wajib.');

            return self::FAILURE;
        }
        $data = [];
        foreach (['clock-in' => 'jam_masuk_iso', 'clock-out' => 'jam_pulang_iso', 'ket' => 'ket'] as $option => $column) {
            if ($this->option($option) !== null) {
                $data[$column] = $this->option($option);
            }
        }
        if (! $data) {
            $this->error('Tidak ada nilai koreksi.');

            return self::FAILURE;
        }
        $corrections->update((int) $this->argument('id'), $data, $actor->id, $this->option('reason'));
        $this->info('Koreksi tersimpan dengan audit trail.');

        return self::SUCCESS;
    }
}
