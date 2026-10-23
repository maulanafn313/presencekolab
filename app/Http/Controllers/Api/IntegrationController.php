<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

// Endpoint server-to-server untuk aplikasi Social Credit Ko+Lab.
// Hanya angka rekap; foto, lokasi & data wajah sengaja tidak pernah dikirim.
class IntegrationController extends Controller
{
    public function discipline(Request $request)
    {
        $key = (string) config('services.socialcredit.key');
        if ($key === '' || ! hash_equals($key, (string) $request->header('X-Integration-Key'))) {
            return response()->json(['ok' => false, 'message' => 'Kunci integrasi tidak valid.'], 401);
        }

        // Periode batch magang. Hari sebelum tanggal mulai kerja intern tetap dilewati oleh KPI.
        $period = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);
        $to = min($period['to'], date('Y-m-d'));

        require_once app_path('Legacy/core.php');
        $pdo = getPdo();

        $data = [];
        // ponytail: KPI dihitung per user (N query), cukup untuk puluhan intern; batch-kan kalau ratusan.
        foreach (DB::table('users')->where('role', 'pegawai')->get(['id', 'nama', 'email', 'nim']) as $user) {
            $kpi = calculateKPIForEmployee($pdo, $user->id, $period['from'], $to);
            if (! $kpi) {
                continue;
            }
            $reports = DB::table('daily_reports')
                ->where('user_id', $user->id)
                ->whereBetween('report_date', [$kpi['period_start'], $kpi['period_end']])
                ->selectRaw('status, COUNT(*) AS total')
                ->groupBy('status')
                ->pluck('total', 'status');

            $data[] = [
                'email' => $user->email,
                'nim' => $user->nim,
                'nama' => $user->nama,
                'period_start' => $kpi['period_start'],
                'period_end' => $kpi['period_end'],
                'kpi_score' => (float) $kpi['kpi_score'], // KPI resmi presensi (0–100), sama dengan halaman KPI
                'kpi_status' => $kpi['status'],
                'ontime' => (int) $kpi['ontime_count'],
                'late' => (int) $kpi['late_count'],
                'alpha' => (int) $kpi['alpha_count'],
                'izin_sakit' => (int) $kpi['izin_sakit_count'],
                'total_late_minutes' => (int) $kpi['total_late_minutes'],
                'missing_daily_reports' => (int) $kpi['missing_daily_reports_count'],
                'reports' => [
                    'approved' => (int) ($reports['approved'] ?? 0),
                    'disapproved' => (int) ($reports['disapproved'] ?? 0),
                    'pending' => (int) ($reports['pending'] ?? 0),
                ],
            ];
        }

        return response()->json(['ok' => true, 'data' => $data]);
    }
}
