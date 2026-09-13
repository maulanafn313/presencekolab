<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminAttendanceRequest;
use App\Models\Attendance;
use App\Services\Attendance\AdminAttendance;
use App\Services\Attendance\AttendanceCorrection;
use App\Services\Attendance\AttendanceSubmission;
use App\Traits\ImageOptimizer;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class AttendanceController extends Controller
{
    use ImageOptimizer;

    /**
     * Ambil semua data presensi (admin only).
     * Menggunakan pagination untuk menghindari memory exhaustion.
     */
    public function index(Request $request)
    {
        try {
            if ($request->user()->role !== 'admin') {
                return response()->json(['ok' => false, 'message' => 'Anda tidak memiliki akses (Unauthorized)'], 403);
            }

            $perPage = min((int) $request->get('per_page', 50), 200); // Max 200 per page

            $attendances = Attendance::with(['user' => function ($query) {
                // Hanya ambil kolom minimal — JANGAN ambil foto/embedding
                $query->select('id', 'nama', 'email', 'nim', 'role', 'prodi', 'startup');
            }])
                ->select(
                    'id', 'user_id',
                    'jam_masuk', 'jam_masuk_iso',
                    'jam_pulang', 'jam_pulang_iso',
                    'lat_masuk', 'lng_masuk', 'lokasi_masuk',
                    'lat_pulang', 'lng_pulang', 'lokasi_pulang',
                    'ekspresi_masuk', 'ekspresi_pulang',
                    'landmark_masuk', 'landmark_pulang', // Pengganti screenshot (jauh lebih kecil)
                    'foto_masuk', 'foto_pulang', // Bukti gambar kompresi (10-20KB)
                    'ket', 'status',
                    'alasan_wfa', 'alasan_overtime', 'alasan_pulang_awal',
                    'is_overtime', 'overtime_bonus',
                    'created_at', 'updated_at'
                )
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            return response()->json([
                'ok' => true,
                'message' => 'Berhasil mengambil data absensi',
                'data' => $attendances->items(),
                'meta' => [
                    'current_page' => $attendances->currentPage(),
                    'last_page' => $attendances->lastPage(),
                    'per_page' => $attendances->perPage(),
                    'total' => $attendances->total(),
                ],
            ]);
        } catch (\Throwable $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            report($e);

            return response()->json([
                'ok' => false,
                'message' => 'Gagal mengambil data absensi',
            ], 500);
        }
    }

    /**
     * Ambil detail satu data presensi (termasuk landmark untuk admin).
     */
    public function show(Request $request, $id)
    {
        try {
            $attendance = Attendance::with(['user' => function ($q) {
                $q->select('id', 'nama', 'email', 'nim', 'role', 'prodi', 'startup');
            }])->find($id);

            if (! $attendance) {
                return response()->json(['ok' => false, 'message' => 'Data absensi tidak ditemukan'], 404);
            }

            // Jika bukan admin, pastikan user hanya bisa melihat absensinya sendiri
            if ($request->user()->role !== 'admin' && $attendance->user_id !== $request->user()->id) {
                return response()->json(['ok' => false, 'message' => 'Anda tidak memiliki akses (Unauthorized)'], 403);
            }

            return response()->json([
                'ok' => true,
                'message' => 'Berhasil mengambil data absensi',
                'data' => $attendance,
            ]);
        } catch (Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            report($e);

            return response()->json([
                'ok' => false,
                'message' => 'Gagal mengambil data absensi',
            ], 500);
        }
    }

    /**
     * Admin buat data presensi manual (izin/sakit/wfo/wfa).
     */
    public function store(AdminAttendanceRequest $request)
    {
        $attendance = app(AdminAttendance::class)
            ->create($request->validated(), $request->user()->id);

        return response()->json(['ok' => true, 'message' => 'Berhasil menambahkan data absensi', 'data' => $attendance], 201);
    }

    public function update(AdminAttendanceRequest $request, $id)
    {
        try {
            if ($request->user()->role !== 'admin') {
                return response()->json(['ok' => false, 'message' => 'Hanya admin yang dapat mengubah data absensi'], 403);
            }

            $attendance = Attendance::find($id);
            if (! $attendance) {
                return response()->json(['ok' => false, 'message' => 'Data absensi tidak ditemukan'], 404);
            }

            $attendance = app(AttendanceCorrection::class)->update(
                $attendance->id, $request->validated(), $request->user()->id,
                $request->input('correction_reason', 'Koreksi melalui API admin')
            );

            return response()->json([
                'ok' => true,
                'message' => 'Berhasil mengubah data absensi',
                'data' => $attendance,
            ]);
        } catch (Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            report($e);

            return response()->json([
                'ok' => false,
                'message' => 'Gagal mengubah data absensi',
            ], 500);
        }
    }

    public function destroy(Request $request, $id)
    {
        try {
            if ($request->user()->role !== 'admin') {
                return response()->json(['ok' => false, 'message' => 'Hanya admin yang dapat menghapus data absensi'], 403);
            }

            $attendance = Attendance::find($id);
            if (! $attendance) {
                return response()->json(['ok' => false, 'message' => 'Data absensi tidak ditemukan'], 404);
            }

            $attendance->delete();

            return response()->json(['ok' => true, 'message' => 'Berhasil menghapus data absensi']);
        } catch (Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            report($e);

            return response()->json([
                'ok' => false,
                'message' => 'Gagal menghapus data absensi',
            ], 500);
        }
    }

    /**
     * Clock In — Absen Masuk.
     * Menerima landmark wajah JSON (68 titik) sebagai pengganti screenshot.
     */
    public function clockIn(Request $request)
    {
        return $this->submitAttendance($request, 'masuk');
    }

    public function clockOut(Request $request)
    {
        return $this->submitAttendance($request, 'pulang');
    }

    private function submitAttendance(Request $request, string $mode)
    {
        $user = $request->user();
        $input = $request->all();
        $input['nim'] = (string) $user->nim;
        $input['mode'] = $mode;
        foreach (['lat', 'lng', 'lokasi', 'ekspresi', 'landmark'] as $field) {
            $input[$field] = $request->input($field.'_'.$mode, $request->input($field));
        }
        $input['screenshot'] = $request->input('image', $request->input('screenshot'));
        $input['request_id'] = $request->header('Idempotency-Key', $request->input('request_id'));
        $result = app(AttendanceSubmission::class)->submit($input, $user->only(['id', 'role', 'nim']));
        $body = $result->getData(true);
        if (! empty($body['ok'])) {
            $body['data'] = Attendance::where('user_id', $user->id)
                ->whereDate('jam_masuk_iso', Carbon::today())->first();
        }

        return response()->json($body, ! empty($body['ok']) && $mode === 'masuk' ? 201 : $result->getStatusCode());
    }

    public function today(Request $request)
    {
        try {
            $attendance = Attendance::select(
                'id', 'user_id',
                'jam_masuk', 'jam_masuk_iso',
                'jam_pulang', 'jam_pulang_iso',
                'lat_masuk', 'lng_masuk', 'lokasi_masuk',
                'lat_pulang', 'lng_pulang', 'lokasi_pulang',
                'ket', 'status', 'is_overtime',
                'ekspresi_masuk', 'ekspresi_pulang',
                'created_at'
            )
                ->where('user_id', $request->user()->id)
                ->whereDate('jam_masuk_iso', Carbon::today())
                ->first();

            return response()->json(['ok' => true, 'message' => 'Berhasil mengambil absensi hari ini', 'data' => $attendance]);
        } catch (Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            report($e);

            return response()->json([
                'ok' => false,
                'message' => 'Gagal mengambil data absensi hari ini',
            ], 500);
        }
    }
}
