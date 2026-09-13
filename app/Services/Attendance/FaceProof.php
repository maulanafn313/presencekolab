<?php

namespace App\Services\Attendance;

use App\Legacy\ResponseSignal;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class FaceProof
{
    public function issue(int $actor, int $user, string $mode): string
    {
        return Crypt::encryptString(json_encode(['actor' => $actor, 'user' => $user, 'mode' => $mode, 'nonce' => (string) Str::uuid(), 'expires' => time() + config('attendance.face_proof_seconds')]));
    }

    public function validate(?string $token, int $actor, int $user, string $mode): array
    {
        try {
            $data = json_decode(Crypt::decryptString($token ?? ''), true, 512, JSON_THROW_ON_ERROR);
            if ($data['actor'] !== $actor || $data['user'] !== $user || $data['mode'] !== $mode || $data['expires'] <= time()) {
                throw new \RuntimeException;
            }

            return $data;
        } catch (\Throwable $e) {
            throw new ResponseSignal(['ok' => false, 'message' => 'Verifikasi wajah diperlukan atau sudah kedaluwarsa.'], 422);
        }
    }

    public function consume(array $proof): void
    {
        if (! Cache::add('attendance:face-proof:'.$proof['nonce'], true, config('attendance.face_proof_seconds'))) {
            throw new ResponseSignal(['ok' => false, 'message' => 'Bukti verifikasi wajah sudah digunakan.'], 409);
        }
    }
}
