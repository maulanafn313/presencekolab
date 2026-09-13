<?php

namespace App\Services\Attendance;

use App\Models\Attendance;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminAttendance
{
    public function create(array $data, int $actorId): Attendance
    {
        return DB::transaction(function () use ($data, $actorId) {
            DB::table('users')->where('id', $data['user_id'])->lockForUpdate()->first();
            $date = $data['tanggal'];
            $reason = $data['correction_reason'] ?? 'Penambahan presensi manual oleh admin';
            unset($data['tanggal'], $data['correction_reason']);
            if (in_array($data['ket'], ['izin', 'sakit'], true)) {
                $data['jam_masuk_iso'] = $date.' 08:00:00';
                $data['jam_pulang_iso'] = $date.' 17:00:00';
            }
            foreach (['masuk', 'pulang'] as $part) {
                $iso = 'jam_'.$part.'_iso';
                $time = 'jam_'.$part;
                if (! empty($data[$iso])) {
                    $data[$iso] = Carbon::parse($data[$iso])->format('Y-m-d H:i:s');
                } elseif (! empty($data[$time]) || $part === 'masuk') {
                    $data[$iso] = Carbon::parse($date.' '.($data[$time] ?? '08:00'))->format('Y-m-d H:i:s');
                }
                $data[$time] = empty($data[$iso]) ? null : substr($data[$iso], 11);
            }
            if (substr($data['jam_masuk_iso'], 0, 10) !== $date) {
                throw ValidationException::withMessages(['tanggal' => 'Tanggal harus sesuai waktu masuk.']);
            }
            if (! empty($data['jam_pulang_iso']) && $data['jam_pulang_iso'] < $data['jam_masuk_iso']) {
                throw ValidationException::withMessages(['jam_pulang_iso' => 'Waktu pulang tidak boleh sebelum masuk.']);
            }
            if (Attendance::where('user_id', $data['user_id'])->whereDate('jam_masuk_iso', $date)->exists()) {
                abort(409, 'Presensi pada tanggal tersebut sudah tersedia.');
            }
            $row = Attendance::create($data);
            DB::table('attendance_corrections')->insert([
                'attendance_id' => $row->id, 'actor_id' => $actorId, 'reason' => $reason,
                'before' => '{}', 'after' => json_encode($row->getAttributes()), 'created_at' => now(),
            ]);
            DB::table('kpi_monthly_cache')->where('user_id', $row->user_id)
                ->where('year', substr($date, 0, 4))->where('month', (int) substr($date, 5, 2))->delete();

            return $row;
        });
    }
}
