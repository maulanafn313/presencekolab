<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Attendance\FaceProof;
use App\Services\FaceNetProcess;
use App\Traits\ImageOptimizer;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class FaceNetController extends Controller
{
    use ImageOptimizer;

    public function index(Request $request)
    {
        try {
            if ($request->user()->role !== 'admin') {
                return response()->json(['ok' => false, 'message' => 'Anda tidak memiliki akses (Unauthorized).'], 403);
            }

            // Tampilkan user yang sudah memiliki face embedding
            // Sertakan status landmark juga (tidak sertakan data besar)
            $users = User::select('id', 'nama', 'email', 'nim', 'role', 'face_embedding_updated')
                ->selectRaw('CASE WHEN face_embedding IS NOT NULL THEN 1 ELSE 0 END AS has_embedding')
                ->selectRaw('CASE WHEN face_landmarks IS NOT NULL THEN 1 ELSE 0 END AS has_landmarks')
                ->whereNotNull('face_embedding')
                ->get();

            return response()->json([
                'ok' => true,
                'message' => 'Berhasil mengambil data pengguna dengan wajah terdaftar.',
                'data' => $users,
            ]);
        } catch (Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            report($e);

            return response()->json([
                'ok' => false,
                'message' => 'Gagal mengambil data wajah.',
            ], 500);
        }
    }

    public function show(Request $request, $id)
    {
        try {
            $user = User::find($id);
            if (! $user) {
                return response()->json(['ok' => false, 'message' => 'Pengguna tidak ditemukan.'], 404);
            }

            if ($request->user()->role !== 'admin' && $user->id !== $request->user()->id) {
                return response()->json(['ok' => false, 'message' => 'Anda tidak memiliki akses (Unauthorized).'], 403);
            }

            return response()->json([
                'ok' => true,
                'message' => 'Berhasil mengambil status wajah.',
                'has_embedding' => $user->face_embedding !== null,
                'updated_at' => $user->face_embedding_updated,
            ]);
        } catch (Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            report($e);

            return response()->json([
                'ok' => false,
                'message' => 'Gagal mengambil status wajah.',
            ], 500);
        }
    }

    public function destroy(Request $request, $id)
    {
        try {
            $user = User::find($id);
            if (! $user) {
                return response()->json(['ok' => false, 'message' => 'Pengguna tidak ditemukan.'], 404);
            }

            if ($request->user()->role !== 'admin' && $user->id !== $request->user()->id) {
                return response()->json(['ok' => false, 'message' => 'Anda tidak memiliki akses (Unauthorized).'], 403);
            }

            $user->face_embedding = null;
            $user->face_embedding_updated = null;
            $user->save();

            return response()->json([
                'ok' => true,
                'message' => 'Data wajah berhasil dihapus.',
            ]);
        } catch (Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            report($e);

            return response()->json([
                'ok' => false,
                'message' => 'Gagal menghapus data wajah.',
            ], 500);
        }
    }

    public function verify(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'image' => 'required|string', // base64
                'user_id' => 'required|exists:users,id',
            ], [
                'image.required' => 'Gambar wajah wajib dikirimkan dalam format base64.',
                'user_id.required' => 'ID Pengguna wajib disertakan.',
                'user_id.exists' => 'Pengguna tidak ditemukan dalam sistem.',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Validasi gagal.',
                    'errors' => $validator->errors(),
                ], 400);
            }

            $user = User::findOrFail($request->user_id);
            if (! $user->face_embedding) {
                return response()->json(['ok' => false, 'message' => 'Pengguna ini belum mendaftarkan wajah.'], 400);
            }

            // Simpan gambar sementara (Optimized 640px)
            $imageName = 'temp_verify_'.bin2hex(random_bytes(16)).'.jpg';
            $savedFilename = $this->optimizeAndSaveBase64($request->image, 'temp', $imageName, 640, 80);

            if (! $savedFilename) {
                return response()->json(['ok' => false, 'message' => 'Gagal memproses gambar.'], 500);
            }

            $imagePath = storage_path('app/private/temp/'.$savedFilename);

            $facenetCli = base_path('scripts/facenet_cli.py');

            $jsonArgs = json_encode([
                'action' => 'verify_face',
                'image' => $imagePath,
                'user_id' => $user->id,
                'threshold' => 0.5,
            ]);

            $process = app(FaceNetProcess::class)->make($jsonArgs);

            $process->run();
            $outputStr = $process->getOutput();

            if (file_exists($imagePath)) {
                unlink($imagePath);
            }

            if (! $process->isSuccessful()) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Gagal verifikasi.',
                ], 500);
            }

            $output = json_decode($outputStr, true);
            if (! $output || ! isset($output['success'])) {
                return response()->json(['ok' => false, 'message' => 'Respon AI tidak valid.'], 500);
            }

            if ($output['success']) {
                $matchData = $output['data'];
                $isMatch = $matchData['match'] ?? false;

                return response()->json([
                    'ok' => $isMatch,
                    'match' => $isMatch,
                    'verification_token' => $isMatch ? app(FaceProof::class)->issue((int) $request->user()->id, (int) $user->id, (string) $request->input('mode', 'masuk')) : null,
                    'confidence' => $matchData['confidence'] ?? 0,
                    'distance' => $matchData['distance'] ?? 0,
                    'message' => $isMatch ? 'Verifikasi wajah berhasil.' : 'Wajah tidak cocok dengan data pengguna ini.',
                ], $isMatch ? 200 : 400);
            }

            return response()->json([
                'ok' => false,
                'message' => 'Wajah tidak dikenali.',
            ], 400);

        } catch (Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            report($e);

            return response()->json([
                'ok' => false,
                'message' => 'Terjadi kesalahan sistem.',
            ], 500);
        }
    }

    public function identify(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'image' => 'required|string', // base64
            ], [
                'image.required' => 'Gambar wajah wajib dikirimkan dalam format base64.',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Validasi gagal.',
                    'errors' => $validator->errors(),
                ], 400);
            }

            // Simpan gambar sementara (Optimized 640px)
            $imageName = 'temp_id_'.bin2hex(random_bytes(16)).'.jpg';
            $savedFilename = $this->optimizeAndSaveBase64($request->image, 'temp', $imageName, 640, 80);

            if (! $savedFilename) {
                return response()->json(['ok' => false, 'message' => 'Gagal memproses gambar.'], 500);
            }

            $imagePath = storage_path('app/private/temp/'.$savedFilename);

            if (! file_exists($imagePath)) {
                return response()->json(['ok' => false, 'message' => 'Gagal menyimpan file gambar sementara.'], 500);
            }

            $facenetCli = base_path('scripts/facenet_cli.py');

            $jsonArgs = json_encode([
                'action' => 'recognize_face',
                'image' => $imagePath,
                'threshold' => 0.7, // Increased for better global recognition
            ]);

            $process = app(FaceNetProcess::class)->make($jsonArgs);

            $process->run();
            $outputStr = $process->getOutput();

            if (file_exists($imagePath)) {
                unlink($imagePath);
            }

            if (! $process->isSuccessful()) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Gagal menjalankan identifikasi.',
                ], 500);
            }

            $output = json_decode($outputStr, true);
            if (! $output) {
                return response()->json(['ok' => false, 'message' => 'Respon AI tidak valid.'], 500);
            }

            if (isset($output['success']) && $output['success']) {
                $matchData = $output['data'];
                $user = User::find($matchData['user_id']);

                if (! $user) {
                    return response()->json(['ok' => false, 'message' => 'User tidak ditemukan.'], 404);
                }

                return response()->json([
                    'ok' => true,
                    'message' => 'Wajah berhasil diidentifikasi.',
                    'user' => [
                        'id' => $user->id,
                        'nama' => $user->nama,
                        'email' => $user->email,
                        'startup' => $user->startup,
                    ],
                    'confidence' => $matchData['confidence'] ?? 0,
                    'distance' => $matchData['distance'] ?? 0,
                ]);
            }

            return response()->json([
                'ok' => false,
                'message' => 'Wajah tidak dikenali atau belum terdaftar.',
            ], 404);

        } catch (Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            report($e);

            return response()->json([
                'ok' => false,
                'message' => 'Terjadi kesalahan sistem.',
            ], 500);
        }
    }

    public function registerFace(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'image' => 'required|string',  // base64 gambar
                'landmarks' => 'nullable|string',  // JSON 68 titik landmark (opsional)
            ], [
                'image.required' => 'Gambar wajah wajib disertakan dalam format base64.',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Validasi gagal. Silakan periksa kembali input Anda.',
                    'errors' => $validator->errors(),
                ], 400);
            }

            $user = $request->user();

            if ($user->face_embedding && $user->role !== 'admin') {
                return response()->json([
                    'ok' => false,
                    'message' => 'Anda sudah memiliki data wajah terdaftar. Silakan hubungi Admin untuk memperbarui wajah Anda.',
                ], 403);
            }

            // Simpan gambar sebagai file
            $imageName = 'face_'.$user->id.'_'.time().'.jpg';
            $savedFilename = $this->optimizeAndSaveBase64($request->image, 'users', $imageName, 300, 70);

            if (! $savedFilename) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Gagal memproses atau menyimpan gambar wajah.',
                ], 500);
            }

            $facenetCli = base_path('scripts/facenet_cli.py');
            $imagePath = storage_path('app/private/media/users/'.$savedFilename);

            // Coba gunakan path absolut, jika tidak ada baru gunakan 'python' biasa

            // Format JSON untuk CLI (Embedding)
            $jsonArgs = json_encode([
                'action' => 'generate_embedding',
                'image' => $imagePath,
            ]);

            // Generate Embedding menggunakan Python
            $process = app(FaceNetProcess::class)->make($jsonArgs);

            $process->run();

            $outputStr = $process->getOutput();
            $errorStr = $process->getErrorOutput();
            $exitCode = $process->getExitCode();

            if (! $process->isSuccessful()) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Gagal membuat Face Embedding. Pastikan wajah terlihat jelas.',
                    'exit_code' => $exitCode,
                    'raw_output' => $outputStr,
                ], 500);
            }

            $output = json_decode($outputStr, true);

            if (isset($output['success']) && $output['success'] && isset($output['data']['embedding'])) {
                $embedding = $output['data']['embedding'];
                // Hapus foto lama jika ada
                if ($user->foto_base64 && Storage::exists('public/users/'.$user->foto_base64)) {
                    Storage::delete('public/users/'.$user->foto_base64);
                }

                // Simpan embedding + nama file foto
                $user->face_embedding = json_encode($embedding);
                $user->foto_base64 = $savedFilename;
                $user->face_embedding_updated = now();

                // Simpan landmarks jika dikirim
                if ($request->landmarks) {
                    $user->face_landmarks = $request->landmarks;
                }

                $user->save();

                return response()->json([
                    'ok' => true,
                    'message' => 'Wajah Anda berhasil didaftarkan ke sistem.',
                ]);
            }

            return response()->json([
                'ok' => false,
                'message' => 'Wajah tidak terdeteksi pada gambar. Silakan coba lagi dengan pencahayaan yang baik.',
            ], 400);
        } catch (Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            report($e);

            return response()->json([
                'ok' => false,
                'message' => 'Terjadi kesalahan sistem saat mendaftarkan wajah.',
            ], 500);
        }
    }
}
