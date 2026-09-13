<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AdminAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    public function rules(): array
    {
        $create = $this->isMethod('POST');

        return [
            'user_id' => $create ? 'required|integer|exists:users,id' : 'prohibited',
            'tanggal' => $create ? 'required|date_format:Y-m-d' : 'sometimes|date_format:Y-m-d',
            'ket' => ($create ? 'required' : 'sometimes').'|in:izin,sakit,wfo,wfa,overtime,alpha',
            'status' => ($create ? 'required' : 'sometimes').'|in:ontime,terlambat,-',
            'jam_masuk' => 'nullable|regex:/^([01][0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])?$/',
            'jam_pulang' => 'nullable|regex:/^([01][0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])?$/',
            'jam_masuk_iso' => 'nullable|date', 'jam_pulang_iso' => 'nullable|date',
            'lat_masuk' => 'nullable|numeric|between:-90,90', 'lat_pulang' => 'nullable|numeric|between:-90,90',
            'lng_masuk' => 'nullable|numeric|between:-180,180', 'lng_pulang' => 'nullable|numeric|between:-180,180',
            'lokasi_masuk' => 'nullable|string', 'lokasi_pulang' => 'nullable|string',
            'alasan_wfa' => 'nullable|string', 'alasan_overtime' => 'nullable|string',
            'alasan_pulang_awal' => 'nullable|string', 'alasan_izin_sakit' => 'nullable|string',
            'bukti_izin_sakit' => 'nullable|string', 'alasan_lokasi_berbeda' => 'nullable|string',
            'is_overtime' => 'sometimes|boolean', 'overtime_bonus' => 'sometimes|numeric|min:0',
            'daily_report_id' => 'nullable|integer|exists:daily_reports,id',
            'correction_reason' => 'nullable|string|max:2000',
        ];
    }
}
