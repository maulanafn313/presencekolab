<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\FaceRegistration;
use App\Services\SessionLifecycle;
use App\Traits\ImageOptimizer;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    use ImageOptimizer;

    public function login(Request $request)
    {
        // 1. Validasi Input
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required',
        ], [
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'password.required' => 'Password wajib diisi.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'ok' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            // 2. Cari User
            // Pastikan kolom di database benar-benar 'email'
            $user = User::where('email', $request->email)->first();

            // 3. Cek User dan Password
            if (! $user || ! Hash::check($request->password, $user->password)) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Email atau password salah',
                ], 401);
            }

            // 4. Generate Token
            $token = $user->createToken('auth_token')->plainTextToken;

            if ($request->hasSession()) {
                app(SessionLifecycle::class)->login($request, $user);
            }

            return response()->json([
                'ok' => true,
                'message' => 'Login berhasil',
                'role' => $user->role,
                'token' => $token,
                'user' => $user,
            ], 200);

        } catch (Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            report($e);

            // 5. Tangkap Error tak terduga (Internal Server Error)
            return response()->json([
                'ok' => false,
                'message' => 'Terjadi kesalahan pada server',
            ], 500);
        }
    }

    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|unique:users,email',
            'nim' => 'required|unique:users,nim',
            'nama' => 'required|string|max:255',
            'password' => 'required|min:6',
            'foto_base64' => 'required|string', // Wajib sertakan foto saat daftar
            'face_landmarks' => 'nullable|string',
        ], [
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email ini sudah digunakan.',
            'nim.required' => 'NIM wajib diisi.',
            'nim.unique' => 'NIM ini sudah terdaftar.',
            'nama.required' => 'Nama wajib diisi.',
            'password.required' => 'Password wajib diisi.',
            'password.min' => 'Password minimal 6 karakter.',
            'foto_base64.required' => 'Foto wajah wajib disertakan untuk pendaftaran.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'ok' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $user = User::create([
                'role' => 'pegawai',
                'email' => $request->email,
                'nim' => $request->nim,
                'nama' => $request->nama,
                'password' => Hash::make($request->password),
                'face_landmarks' => $request->face_landmarks,
            ]);

            $faceRegistered = false;
            try {
                $imageName = 'face_'.$user->id.'_'.time().'.jpg';
                $savedFilename = $this->optimizeAndSaveBase64($request->foto_base64, 'users', $imageName, 300, 70);
                if ($savedFilename) {
                    $user->foto_base64 = $savedFilename;
                    $user->save();
                    $faceRegistered = app(FaceRegistration::class)->generate($user);
                }
            } catch (\Throwable $e) {
                if ($e instanceof ValidationException) {
                    throw $e;
                }
                report($e);
                report($e);
            }

            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'ok' => true,
                'message' => $faceRegistered ? 'Registrasi berhasil (Akun & Wajah terdaftar)' : 'Akun berhasil dibuat. Pendaftaran wajah belum selesai; silakan ulangi pendaftaran wajah.',
                'face_status' => $faceRegistered ? 'ready' : 'failed',
                'role' => $user->role,
                'token' => $token,
                'user' => $user,
            ], 201);

        } catch (Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            report($e);

            return response()->json([
                'ok' => false,
                'message' => 'Terjadi kesalahan saat registrasi',
            ], 500);
        }
    }

    public function logout(Request $request)
    {
        try {
            app(SessionLifecycle::class)->logout($request);

            return response()->json([
                'ok' => true,
                'message' => 'Berhasil keluar (Logged out)',
            ]);
        } catch (Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            report($e);

            return response()->json([
                'ok' => false,
                'message' => 'Gagal logout',
            ], 500);
        }
    }
}
