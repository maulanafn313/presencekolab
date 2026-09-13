<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\PrivateMedia;
use Illuminate\Http\Request;

class PrivateMediaController extends Controller
{
    public function show(Request $request, string $name)
    {
        $owner = User::where('foto_base64', $name)
                     ->orWhere('foto_base64', 'LIKE', '%' . $name)
                     ->first();
        abort_unless($owner, 404);
        abort_unless($request->user()->role === 'admin' || (int)$owner->id === (int)$request->user()->id, 403);
        $path = PrivateMedia::path($name);
        abort_unless($path, 404);
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        abort_unless(in_array($mime, ['image/jpeg','image/png','image/webp','image/gif']), 404);
        return response()->file($path, ['Content-Type'=>$mime, 'Cache-Control'=>'no-store, private']);
    }
}
