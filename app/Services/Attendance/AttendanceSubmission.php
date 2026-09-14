<?php

namespace App\Services\Attendance;

use App\Legacy\ResponseSignal;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/** One transaction boundary for the existing attendance rules and both transports. */
class AttendanceSubmission
{
    public function __construct(private AttendanceClock $clock) {}

    public function submit(array $input, array $actor): JsonResponse
    {
        $validator = Validator::make($input, [
            'nim' => 'required|string', 'mode' => 'required|in:masuk,pulang',
            'request_id' => 'nullable|string|max:100',
            'lat' => 'nullable|numeric|between:-90,90',
            'lng' => 'nullable|numeric|between:-180,180',
            'gps_accuracy' => 'nullable|numeric|min:0',
        ]);
        if ($validator->fails()) {
            return response()->json(['ok' => false, 'message' => 'Data presensi tidak valid.', 'errors' => $validator->errors()], 422);
        }
        // Kios presensi publik: tamu (tanpa sesi) boleh absen memakai hasil pemindaian wajah,
        // sesuai perilaku lama. Aktor yang sudah login tetap hanya boleh absen untuk dirinya
        // sendiri; admin bebas. Identitas tetap diverifikasi aturan absensi lama (NIM harus
        // cocok dengan user yang benar-benar ada di database).
        if (! empty($actor['id'])
            && ($actor['role'] ?? '') !== 'admin'
            && (string) ($actor['nim'] ?? '') !== $input['nim']) {
            return response()->json(['ok' => false, 'message' => 'Akses tidak diizinkan.'], 403);
        }
        $actorId = (int) ($actor['id'] ?? 0);

        require_once app_path('Legacy/core.php');
        $submissionTime = $this->clock->now();
        try {
            return DB::transaction(function () use ($input, $actorId, $submissionTime) {
                // Lock the stable parent row, including when no attendance exists yet.
                $target = DB::table('users')->where('nim', $input['nim'])->lockForUpdate()->first();
                if (! $target) {
                    return response()->json(['ok' => false, 'message' => 'NIM tidak ditemukan'], 404);
                }
                $key = $input['request_id'] ?? null;
                $hashInput = $input;
                unset($hashInput['_token'], $hashInput['request_id'], $hashInput['face_proof']);
                ksort($hashInput);
                $hash = hash('sha256', json_encode($hashInput, JSON_THROW_ON_ERROR));
                // Tamu tidak punya id sesi: idempotency dikunci ke user pemilik NIM tersebut.
                $idempotencyOwner = $actorId ?: (int) $target->id;
                if ($key) {
                    $previous = DB::table('attendance_submissions')->where('user_id', $idempotencyOwner)->where('request_key', $key)->first();
                    if ($previous) {
                        if (! hash_equals($previous->request_hash, $hash)) {
                            return response()->json(['ok' => false, 'message' => 'ID permintaan sudah digunakan untuk data berbeda.'], 409);
                        }

                        return response()->json(json_decode($previous->response, true), $previous->status);
                    }
                }

                $previousPost = $_POST;
                $proof = config('attendance.require_face_proof') && $actorId
                    ? app(FaceProof::class)->validate($input['face_proof'] ?? null, $actorId, (int) $target->id, $input['mode'])
                    : null;
                global $pdo;
                $previousPdo = $pdo;
                $pdo = getPdo();
                $_POST = $input;
                try {
                    require app_path('Legacy/actions/save_attendance.php');
                    throw new \LogicException('Attendance rules did not produce a response.');
                } catch (ResponseSignal $signal) {
                    if (empty($signal->payload['ok']) || $signal->status >= 400) {
                        throw $signal; // Roll back validation failures, including partial writes.
                    }
                    $result = $signal->response();
                    if ($proof) {
                        app(FaceProof::class)->consume($proof);
                    }
                    if ($key) {
                        DB::table('attendance_submissions')->insert([
                            'user_id' => $idempotencyOwner, 'request_key' => $key,
                            'request_hash' => $hash, 'response' => $result->getContent(),
                            'status' => $result->getStatusCode(), 'created_at' => now(),
                        ]);
                    }

                    return $result; // Commit before responding or running afterCommit jobs.
                } finally {
                    $_POST = $previousPost;
                    $pdo = $previousPdo;
                }
            }, 3);
        } catch (ResponseSignal $signal) {
            return $signal->response();
        }
    }
}
