<?php

namespace App\Http\Middleware;

use App\Services\SafeResponse;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SafeResponses
{
    public function handle(Request $request, Closure $next)
    {
        $requestId = (string) Str::uuid();
        $request->attributes->set('request_id', $requestId);
        Log::withContext(['request_id' => $requestId]);
        $response = $next($request);
        if ($response instanceof JsonResponse) {
            $data = $response->getData(true);
            if (is_array($data)) {
                if ($response->getStatusCode() >= 500) {
                    Log::error('Application request failed', [
                        'request_id' => $requestId, 'route' => $request->path(),
                        'status' => $response->getStatusCode(),
                    ]);
                }
                $response->setData(SafeResponse::payload($data, $response->getStatusCode()));
            }
        }
        $response->headers->set('X-Request-ID', $requestId);
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'same-origin');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');

        return $response;
    }
}
