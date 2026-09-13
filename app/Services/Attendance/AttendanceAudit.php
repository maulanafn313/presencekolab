<?php

namespace App\Services\Attendance;

use Illuminate\Support\Facades\DB;

class AttendanceAudit
{
    public function summary(): array
    {
        return [
            'invalid_time_order' => DB::table('attendance')->whereColumn('jam_pulang_iso', '<', 'jam_masuk_iso')->count(),
            'open_historical' => DB::table('attendance')->whereDate('jam_masuk_iso', '<', today())
                ->whereIn('ket', ['wfo', 'wfa', 'overtime'])->whereNull('jam_pulang_iso')->count(),
            'empty_ket' => DB::table('attendance')->where(fn ($q) => $q->whereNull('ket')->orWhere('ket', ''))->count(),
            'duplicate_user_day' => DB::query()->fromSub(DB::table('attendance')->selectRaw('user_id, DATE(jam_masuk_iso) day')
                ->whereNotNull('jam_masuk_iso')->groupByRaw('user_id, DATE(jam_masuk_iso)')->havingRaw('COUNT(*) > 1'), 'duplicates')->count(),
        ];
    }
}
