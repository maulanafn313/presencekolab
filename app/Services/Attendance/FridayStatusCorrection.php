<?php

namespace App\Services\Attendance;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Menyamakan status presensi Jumat lama dengan batas masuk 07:30. */
final class FridayStatusCorrection
{
    private const REASON = 'Koreksi status Jumat: batas masuk 07:30 sejak 7 Juli 2026';

    public static function expectedStatus(string $time): string
    {
        return substr($time, 0, 5) > WorkHours::FRIDAY_START ? 'terlambat' : 'ontime';
    }

    public static function pending(): Collection
    {
        return DB::table('attendance as a')->join('users as u', 'u.id', '=', 'a.user_id')
            ->whereRaw(WorkHours::fridaySql('a.jam_masuk_iso'))
            ->whereIn('a.ket', ['wfo', 'wfa'])
            ->whereIn('a.status', ['ontime', 'terlambat'])
            ->orderBy('a.jam_masuk_iso')
            ->get(['a.id', 'a.user_id', 'a.jam_masuk_iso', 'a.status', 'u.nama'])
            ->map(function ($row) {
                $row->expected = self::expectedStatus(substr($row->jam_masuk_iso, 11, 5));

                return $row;
            })
            ->filter(fn ($row) => $row->status !== $row->expected)
            ->values();
    }

    /** Status lama tersimpan di attendance_corrections sebagai jejak perubahan. */
    public static function apply(Collection $rows): int
    {
        return DB::transaction(function () use ($rows) {
            foreach ($rows as $row) {
                DB::table('attendance')->where('id', $row->id)->update(['status' => $row->expected]);
                DB::table('attendance_corrections')->insert([
                    'attendance_id' => $row->id, 'actor_id' => null, 'reason' => self::REASON,
                    'before' => json_encode(['status' => $row->status]), 'after' => json_encode(['status' => $row->expected]),
                    'created_at' => now(),
                ]);
                DB::table('kpi_monthly_cache')->where('user_id', $row->user_id)
                    ->where('year', (int) substr($row->jam_masuk_iso, 0, 4))
                    ->where('month', (int) substr($row->jam_masuk_iso, 5, 2))->delete();
            }

            return $rows->count();
        });
    }
}
