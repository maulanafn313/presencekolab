<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use Illuminate\Http\Request;

class AttendanceEvidenceController extends Controller
{
    public function show(Request $request, int $id, string $mode)
    {
        abort_unless(in_array($mode, ['masuk', 'pulang'], true), 404);
        $row = Attendance::findOrFail($id);
        abort_unless($request->user()->role === 'admin' || (int) $row->user_id === (int) $request->user()->id, 403);
        $value = $row->{'foto_'.$mode};
        abort_unless(is_string($value) && $value !== '', 404);
        if (preg_match('#^data:image/(jpeg|png|webp);base64,(.*)$#s', $value, $match)) {
            $bytes = base64_decode($match[2], true);
            abort_if($bytes === false, 404);
        } else {
            // Only evidence generated inside the private evidence directory is readable.
            abort_unless(basename($value) === $value, 404);
            $root = realpath(storage_path('app/private/evidence'));
            $path = $root ? realpath($root.DIRECTORY_SEPARATOR.$value) : false;
            abort_unless($path && str_starts_with($path, $root.DIRECTORY_SEPARATOR) && is_file($path), 404);
            $bytes = file_get_contents($path);
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        abort_unless(in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true), 404);

        return response($bytes, 200, ['Content-Type' => $mime, 'Cache-Control' => 'no-store, private', 'X-Content-Type-Options' => 'nosniff']);
    }
}
