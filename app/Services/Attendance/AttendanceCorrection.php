<?php

namespace App\Services\Attendance;

use App\Models\Attendance;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AttendanceCorrection
{
    public function update(int $id, array $input, int $actorId, string $reason): Attendance
    {
        return DB::transaction(function () use ($id, $input, $actorId, $reason) {
            $row = Attendance::lockForUpdate()->findOrFail($id);
            $rules = [
                'jam_masuk' => 'nullable|regex:/^([01][0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])?$/', 'jam_pulang' => 'nullable|regex:/^([01][0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])?$/',
                'jam_masuk_iso' => 'nullable|date', 'jam_pulang_iso' => 'nullable|date',
                'lat_masuk' => 'nullable|numeric|between:-90,90', 'lat_pulang' => 'nullable|numeric|between:-90,90',
                'lng_masuk' => 'nullable|numeric|between:-180,180', 'lng_pulang' => 'nullable|numeric|between:-180,180',
                'ket' => 'sometimes|in:wfo,wfa,overtime,izin,sakit,alpha', 'status' => 'sometimes|in:ontime,terlambat,-',
                'is_overtime' => 'sometimes|boolean', 'overtime_bonus' => 'sometimes|numeric|min:0',
                'daily_report_id' => 'nullable|integer',
            ];
            foreach (['lokasi_masuk', 'lokasi_pulang', 'alasan_wfa', 'alasan_overtime', 'alasan_pulang_awal', 'alasan_izin_sakit', 'bukti_izin_sakit', 'alasan_lokasi_berbeda'] as $field) {
                $rules[$field] = 'nullable|string';
            }
            $data = Validator::make($input, $rules)->validate();
            $before = $row->only(array_keys($rules));
            foreach (['masuk', 'pulang'] as $part) {
                $iso = 'jam_'.$part.'_iso';
                $time = 'jam_'.$part;
                if (array_key_exists($iso, $data)) {
                    $data[$iso] = $data[$iso] ? Carbon::parse($data[$iso])->format('Y-m-d H:i:s') : null;
                    $data[$time] = $data[$iso] ? substr($data[$iso], 11) : null;
                } elseif (array_key_exists($time, $data) && $row->$iso) {
                    $data[$iso] = $data[$time] ? Carbon::parse($row->$iso)->format('Y-m-d').' '.$data[$time] : null;
                }
            }
            $combined = array_replace($before, $data);
            Validator::make($combined, [
                'jam_pulang_iso' => 'nullable|date|after_or_equal:jam_masuk_iso',
            ])->validate();
            $row->fill($data)->save();
            DB::table('attendance_corrections')->insert([
                'attendance_id' => $row->id, 'actor_id' => $actorId, 'reason' => $reason,
                'before' => json_encode($before), 'after' => json_encode($row->only(array_keys($rules))), 'created_at' => now(),
            ]);
            foreach ([$before['jam_masuk_iso'], $row->jam_masuk_iso] as $date) {
                if ($date) {
                    DB::table('kpi_monthly_cache')->where('user_id', $row->user_id)
                        ->where('year', Carbon::parse($date)->year)->where('month', Carbon::parse($date)->month)->delete();
                }
            }

            return $row;
        });
    }
}
