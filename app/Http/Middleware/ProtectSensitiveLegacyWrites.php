<?php

namespace App\Http\Middleware;

use App\Services\LegacyRequestPolicy;
use Closure;
use Illuminate\Http\Request;

class ProtectSensitiveLegacyWrites
{
    public function handle(Request $request, Closure $next)
    {
        // Every web mutation requires CSRF, including login and read-like POSTs.
        $actions = [
            $request->query('ajax'), $request->input('ajax'),
            $request->query('action'), $request->input('action'),
        ];
        foreach ($actions as $action) {
            if ($action !== null && ! is_string($action)) {
                return response()->json(['ok' => false, 'message' => 'Action tidak valid'], 400);
            }
            if (is_string($action) && ! LegacyRequestPolicy::isRead($action) && ! $request->isMethod('POST')) {
                return response()->json(['ok' => false, 'message' => 'Method not allowed'], 405);
            }
        }
        if (count(array_unique(array_filter($actions, fn ($value) => $value !== null))) > 1) {
            return response()->json(['ok' => false, 'message' => 'Action tidak konsisten'], 400);
        }
        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $next($request);
        }
        // Kios presensi publik: absen dari halaman pemindaian wajah dipakai tamu yang
        // belum login, jadi tidak punya token CSRF. Kios yang terbuka lama juga membuat
        // token halaman basi. Aksi ini diverifikasi lewat NIM + wajah di aturan absen.
        // get_today_attendance ikut dibebaskan karena ikut dibaca kios tamu (POST baca).
        if (array_intersect(['save_attendance', 'get_today_attendance'], $actions)) {
            return $next($request);
        }
        $token = $request->header('X-CSRF-TOKEN') ?: $request->input('_token');
        if (! is_string($token) || ! $request->hasSession() ||
            ! is_string($request->session()->token()) ||
            ! hash_equals($request->session()->token(), $token)) {
            return response()->json(['ok' => false, 'message' => 'Sesi keamanan tidak valid. Muat ulang halaman dan coba lagi.'], 419);
        }

        return $next($request);
    }
}
