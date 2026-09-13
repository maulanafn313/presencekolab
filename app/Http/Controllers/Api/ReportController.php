<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReportRequest;
use App\Models\DailyReport;
use App\Models\MonthlyReport;
use App\Services\ApiListing;
use App\Services\ReportSubmission;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ReportController extends Controller
{
    // ==========================================
    // DAILY REPORTS
    // ==========================================

    public function indexDaily(Request $request)
    {
        try {
            $user = $request->user();
            $date = $request->query('date');

            $query = DailyReport::query();
            if ($user->role !== 'admin') {
                $query->where('user_id', $user->id);
            }

            if ($date) {
                $query->where('report_date', $date);
            }

            $reports = ApiListing::get($query->orderBy('id'), $request);

            return response()->json([
                'ok' => true,
                'message' => 'Berhasil mengambil data laporan harian.',
                ...$reports,
            ]);
        } catch (Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            report($e);

            return response()->json([
                'ok' => false,
                'message' => 'Gagal mengambil data laporan harian.',
            ], 500);
        }
    }

    public function showDaily(Request $request, $id)
    {
        try {
            $report = DailyReport::find($id);
            if (! $report) {
                return response()->json(['ok' => false, 'message' => 'Laporan harian tidak ditemukan.'], 404);
            }

            if ($request->user()->role !== 'admin' && $report->user_id !== $request->user()->id) {
                return response()->json(['ok' => false, 'message' => 'Anda tidak memiliki akses (Unauthorized).'], 403);
            }

            return response()->json([
                'ok' => true,
                'message' => 'Berhasil mengambil data laporan harian.',
                'data' => $report,
            ]);
        } catch (Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            report($e);

            return response()->json([
                'ok' => false,
                'message' => 'Gagal mengambil data laporan harian.',
            ], 500);
        }
    }

    // Update pada method storeDaily
    public function storeDaily(ReportRequest $request)
    {
        try {
            $isAdmin = $request->user()->role === 'admin';

            $validator = Validator::make($request->all(), [
                'user_id' => $isAdmin ? 'required|exists:users,id' : 'nullable', // Admin wajib isi user_id
                'date' => 'required|date',
                'content' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json(['ok' => false, 'errors' => $validator->errors()], 400);
            }

            // Tentukan user_id: jika admin gunakan input, jika pegawai gunakan id sendiri
            $targetUserId = $isAdmin ? $request->user_id : $request->user()->id;

            $report = app(ReportSubmission::class)->daily((int) $targetUserId, $request->date, $request->content);

            return response()->json(['ok' => true, 'message' => 'Laporan berhasil disimpan.', 'data' => $report], 201);
        } catch (Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            report($e);

            return response()->json(['ok' => false, 'message' => 'Terjadi kesalahan sistem.'], 500);
        }
    }

    // Update pada method updateDaily agar Admin bisa edit milik siapapun
    public function updateDaily(ReportRequest $request, $id)
    {
        try {
            $report = DailyReport::find($id);
            if (! $report) {
                return response()->json(['ok' => false, 'message' => 'Data tidak ditemukan.'], 404);
            }

            // Izinkan jika dia Admin OR pemilik laporan
            if ($request->user()->role !== 'admin' && $report->user_id !== $request->user()->id) {
                return response()->json(['ok' => false, 'message' => 'Unauthorized.'], 403);
            }

            $report->update(['content' => $request->content]);

            return response()->json(['ok' => true, 'message' => 'Konten laporan diperbarui.', 'data' => $report]);
        } catch (Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            report($e);

            return response()->json(['ok' => false, 'message' => 'Terjadi kesalahan sistem.'], 500);
        }
    }

    public function destroyDaily(Request $request, $id)
    {
        try {
            $report = DailyReport::find($id);
            if (! $report) {
                return response()->json(['ok' => false, 'message' => 'Laporan harian tidak ditemukan.'], 404);
            }

            if ($request->user()->role !== 'admin' && $report->user_id !== $request->user()->id) {
                return response()->json(['ok' => false, 'message' => 'Anda tidak memiliki akses (Unauthorized).'], 403);
            }

            $report->delete();

            return response()->json([
                'ok' => true,
                'message' => 'Laporan harian berhasil dihapus.',
            ]);
        } catch (Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            report($e);

            return response()->json([
                'ok' => false,
                'message' => 'Gagal menghapus laporan harian.',
            ], 500);
        }
    }

    public function updateDailyStatus(ReportRequest $request, $id)
    {
        try {
            if ($request->user()->role !== 'admin') {
                return response()->json(['ok' => false, 'message' => 'Hanya admin yang dapat menyetujui laporan.'], 403);
            }

            $validator = Validator::make($request->all(), [
                'status' => 'required|in:approved,disapproved',
                'evaluation' => 'nullable|string',
                'content' => 'nullable|string', // Pastikan kolom ini ada di $fillable model
            ]);

            if ($validator->fails()) {
                return response()->json(['ok' => false, 'message' => 'Validasi gagal.', 'errors' => $validator->errors()], 400);
            }

            $report = DailyReport::find($id);
            if (! $report) {
                return response()->json(['ok' => false, 'message' => 'Laporan tidak ditemukan.'], 404);
            }

            // Melakukan update dengan menjaga agar data lama tidak hilang jika input kosong
            $report->update([
                'status' => $request->status,
                'evaluation' => $request->evaluation ?? $report->evaluation,
                'content' => $request->content ?? $report->content, // Menjaga konten asli pegawai
            ]);

            // Muat ulang data dari database agar data di response sinkron
            $report->refresh();

            return response()->json([
                'ok' => true,
                'message' => 'Status laporan harian berhasil diperbarui.',
                'data' => $report, // Sekarang menampilkan data versi terbaru
            ]);
        } catch (Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            report($e);

            return response()->json([
                'ok' => false,
                'message' => 'Gagal memperbarui status laporan harian.',
            ], 500);
        }
    }

    // ==========================================
    // MONTHLY REPORTS
    // ==========================================

    public function indexMonthly(Request $request)
    {
        try {
            $user = $request->user();
            $month = $request->query('month');
            $year = $request->query('year');

            $query = MonthlyReport::query();
            if ($user->role !== 'admin') {
                $query->where('user_id', $user->id);
            }

            if ($month) {
                $query->where('month', $month);
            }
            if ($year) {
                $query->where('year', $year);
            }

            $reports = ApiListing::get($query->orderBy('id'), $request);

            return response()->json([
                'ok' => true,
                'message' => 'Berhasil mengambil data laporan bulanan.',
                ...$reports,
            ]);
        } catch (Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            report($e);

            return response()->json([
                'ok' => false,
                'message' => 'Gagal mengambil data laporan bulanan.',
            ], 500);
        }
    }

    public function showMonthly(Request $request, $id)
    {
        try {
            $report = MonthlyReport::find($id);
            if (! $report) {
                return response()->json(['ok' => false, 'message' => 'Laporan bulanan tidak ditemukan.'], 404);
            }

            if ($request->user()->role !== 'admin' && $report->user_id !== $request->user()->id) {
                return response()->json(['ok' => false, 'message' => 'Anda tidak memiliki akses (Unauthorized).'], 403);
            }

            return response()->json([
                'ok' => true,
                'message' => 'Berhasil mengambil data laporan bulanan.',
                'data' => $report,
            ]);
        } catch (Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            report($e);

            return response()->json([
                'ok' => false,
                'message' => 'Gagal mengambil data laporan bulanan.',
            ], 500);
        }
    }

    public function storeMonthly(ReportRequest $request)
    {
        try {
            // Konversi string JSON ke array jika dikirim via form-data atau urlencoded
            if (is_string($request->achievements)) {
                $decoded = json_decode($request->achievements, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $request->merge(['achievements' => $decoded]);
                }
            }
            if (is_string($request->obstacles)) {
                $decoded = json_decode($request->obstacles, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $request->merge(['obstacles' => $decoded]);
                }
            }

            $validator = Validator::make($request->all(), [
                'month' => 'required|integer|between:1,12',
                'year' => 'required|integer',
                'summary' => 'required|string',
                'achievements' => 'nullable|array',
                'obstacles' => 'nullable|array',
            ], [
                'month.required' => 'Bulan laporan wajib diisi.',
                'year.required' => 'Tahun laporan wajib diisi.',
                'summary.required' => 'Ringkasan pekerjaan wajib diisi.',
                'achievements.array' => 'Format pencapaian harus berupa array atau JSON valid.',
                'obstacles.array' => 'Format kendala harus berupa array atau JSON valid.',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Validasi gagal. Silakan periksa kembali data Anda.',
                    'errors' => $validator->errors(),
                ], 400);
            }

            $user = $request->user();
            $report = app(ReportSubmission::class)->monthly($user->id, $request->validated());

            return response()->json([
                'ok' => true,
                'message' => 'Laporan bulanan berhasil disimpan.',
                'data' => $report,
            ], 201);
        } catch (Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            report($e);

            return response()->json([
                'ok' => false,
                'message' => 'Gagal menyimpan laporan bulanan.',
            ], 500);
        }
    }

    public function updateMonthly(ReportRequest $request, $id)
    {
        try {
            $report = MonthlyReport::find($id);
            if (! $report) {
                return response()->json(['ok' => false, 'message' => 'Laporan bulanan tidak ditemukan.'], 404);
            }

            if ($request->user()->role !== 'admin' && $report->user_id !== $request->user()->id) {
                return response()->json(['ok' => false, 'message' => 'Anda tidak memiliki akses (Unauthorized).'], 403);
            }

            // Konversi string JSON ke array jika dikirim via form-data atau urlencoded
            if (is_string($request->achievements)) {
                $decoded = json_decode($request->achievements, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $request->merge(['achievements' => $decoded]);
                }
            }
            if (is_string($request->obstacles)) {
                $decoded = json_decode($request->obstacles, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $request->merge(['obstacles' => $decoded]);
                }
            }

            $validator = Validator::make($request->all(), [
                'summary' => 'required|string',
                'achievements' => 'nullable|array',
                'obstacles' => 'nullable|array',
            ], [
                'summary.required' => 'Ringkasan pekerjaan wajib diisi.',
                'achievements.array' => 'Format pencapaian harus berupa array atau JSON valid.',
                'obstacles.array' => 'Format kendala harus berupa array atau JSON valid.',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Validasi gagal. Silakan periksa kembali data Anda.',
                    'errors' => $validator->errors(),
                ], 400);
            }

            $report->update([
                'summary' => $request->summary,
                'achievements' => $request->achievements ?? $report->achievements,
                'obstacles' => $request->obstacles ?? $report->obstacles,
            ]);

            return response()->json([
                'ok' => true,
                'message' => 'Laporan bulanan berhasil diperbarui.',
                'data' => $report,
            ]);
        } catch (Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            report($e);

            return response()->json([
                'ok' => false,
                'message' => 'Gagal memperbarui laporan bulanan.',
            ], 500);
        }
    }

    public function updateMonthlyStatus(ReportRequest $request, $id)
    {
        try {
            // 1. Cek Otoritas Admin
            if ($request->user()->role !== 'admin') {
                return response()->json([
                    'ok' => false,
                    'message' => 'Akses ditolak. Hanya admin yang diperbolehkan mengubah status laporan.',
                ], 403);
            }

            // 2. Validasi Input dengan Pesan Bahasa Indonesia
            $validator = Validator::make($request->all(), [
                'status' => 'required|in:approved,disapproved',
            ], [
                'status.required' => 'Status laporan wajib diisi.',
                'status.in' => 'Status harus bernilai "approved" (disetujui) atau "disapproved" (ditolak).',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Validasi gagal. Silakan periksa kembali input Anda.',
                    'errors' => $validator->errors(),
                ], 400);
            }

            // 3. Cari Data Laporan
            $report = MonthlyReport::find($id);
            if (! $report) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Laporan bulanan tidak ditemukan dalam database.',
                ], 404);
            }

            // 4. Update Hanya Status
            $report->update([
                'status' => $request->status,
            ]);

            // Muat ulang data terbaru
            $report->refresh();

            // 5. Response Berhasil (Pastikan mengembalikan JSON lengkap)
            return response()->json([
                'ok' => true,
                'message' => 'Status laporan bulanan berhasil diperbarui menjadi '.$request->status.'.',
                'data' => $report,
            ], 200);

        } catch (Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            report($e);

            // Error Handling Server
            return response()->json([
                'ok' => false,
                'message' => 'Terjadi kesalahan sistem saat memperbarui status laporan.',
            ], 500);
        }
    }

    public function destroyMonthly(Request $request, $id)
    {
        try {
            $report = MonthlyReport::find($id);
            if (! $report) {
                return response()->json(['ok' => false, 'message' => 'Laporan bulanan tidak ditemukan.'], 404);
            }

            if ($request->user()->role !== 'admin' && $report->user_id !== $request->user()->id) {
                return response()->json(['ok' => false, 'message' => 'Anda tidak memiliki akses (Unauthorized).'], 403);
            }

            $report->delete();

            return response()->json([
                'ok' => true,
                'message' => 'Laporan bulanan berhasil dihapus.',
            ]);
        } catch (Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            report($e);

            return response()->json([
                'ok' => false,
                'message' => 'Gagal menghapus laporan bulanan.',
            ], 500);
        }
    }
}
